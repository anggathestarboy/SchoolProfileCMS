<?php

namespace App\Modules\ActivityLog\Livewire;

use App\Models\User;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Models\ActivityLogDeletionRequest;
use App\Modules\ActivityLog\Notifications\ConfirmActivityLogDeletion;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Activity Log')]
class ActivityLogManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $action = '';

    public string $userId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $exportPeriod = 'month';

    public string $exportMonth = '';

    public string $exportYear = '';

    public $restoreFile = null;

    public string $deleteYear = '';

    public function mount(): void
    {
        abort_unless(config('activity-log.enabled'), 404);
        auth()->user()->authorizePermission('view activity log');

        $this->exportMonth = now()->format('Y-m');
        $this->exportYear = now()->format('Y');
        $this->deleteYear = now()->format('Y');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAction(): void
    {
        $this->resetPage();
    }

    public function updatingUserId(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'action', 'userId', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->with('user')
            ->when($this->search !== '', fn ($query) => $query->where('description', 'like', "%{$this->search}%"))
            ->when($this->action !== '', fn ($query) => $query->where('action', $this->action))
            ->when($this->userId !== '', fn ($query) => $query->where('user_id', $this->userId))
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function actionOptions(): array
    {
        return ActivityLog::query()->distinct()->orderBy('action')->pluck('action')->all();
    }

    #[Computed]
    public function userOptions()
    {
        return User::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function canViewDetail(): bool
    {
        return auth()->user()->hasPermission('view activity log detail');
    }

    #[Computed]
    public function canExport(): bool
    {
        return auth()->user()->hasPermission('export activity log');
    }

    #[Computed]
    public function canImport(): bool
    {
        return auth()->user()->hasPermission('import activity log');
    }

    #[Computed]
    public function isSuperAdmin(): bool
    {
        return auth()->user()->isSuper();
    }

    #[Computed]
    public function deletionRequests()
    {
        return ActivityLogDeletionRequest::whereIn('status', ['pending', 'confirmed'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Step 1 of the deliberately slow, superadmin-only hard delete flow:
     * creates a pending request and emails a confirmation link. Nothing is
     * deleted here — see ConfirmDeletionController (step 2, flips the
     * request to "confirmed") and HardDeleteActivityLogYear (step 3, the
     * actual delete, run by the nightly activity-log:process-deletions
     * command).
     */
    public function requestDeletion(): void
    {
        abort_unless(auth()->user()->isSuper(), 403);

        $this->validate(['deleteYear' => ['required', 'integer', 'min:2000', 'max:'.now()->format('Y')]]);

        $year = (int) $this->deleteYear;
        $token = Str::random(64);

        $request = ActivityLogDeletionRequest::create([
            'year' => $year,
            'requested_by' => auth()->id(),
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        Notification::send(auth()->user(), new ConfirmActivityLogDeletion($request->year, $token));

        unset($this->deletionRequests);

        Flux::toast(variant: 'success', text: __('A confirmation email was sent to your address. Nothing will be deleted until you click the link.'));
    }

    /**
     * Downloads every logged entry within the selected month/year as JSON —
     * the exact shape restore() expects back, so an export doubles as a
     * backup that can be re-imported later.
     */
    public function export(): StreamedResponse
    {
        auth()->user()->authorizePermission('export activity log');

        if ($this->exportPeriod === 'year') {
            $period = Carbon::createFromFormat('Y', $this->exportYear)->startOfYear();
            $from = $period->copy()->startOfYear();
            $to = $period->copy()->endOfYear();
            $filename = "activity-log-{$period->format('Y')}.json";
        } else {
            $period = Carbon::createFromFormat('Y-m', $this->exportMonth)->startOfMonth();
            $from = $period->copy()->startOfMonth();
            $to = $period->copy()->endOfMonth();
            $filename = "activity-log-{$period->format('Y-m')}.json";
        }

        $rows = ActivityLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get()
            ->toArray();

        return response()->streamDownload(
            fn () => print (json_encode($rows, JSON_PRETTY_PRINT)),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    /**
     * Re-imports entries from a file previously produced by export(). Rows
     * are upserted on their original id, so restoring the same export twice
     * (or restoring over entries that are still present) is harmless.
     */
    public function restore(): void
    {
        auth()->user()->authorizePermission('import activity log');

        $this->validate(['restoreFile' => ['required', 'file', 'max:20480']]);

        if ($this->restoreFile->getClientOriginalExtension() !== 'json') {
            Flux::toast(variant: 'danger', text: __('Please upload a .json export file.'));

            return;
        }

        $rows = json_decode($this->restoreFile->get(), true);

        if (! is_array($rows)) {
            Flux::toast(variant: 'danger', text: __('That file is not a valid activity log export.'));

            return;
        }

        $restored = 0;

        foreach ($rows as $row) {
            if (! isset($row['id'])) {
                continue;
            }

            ActivityLog::query()->upsert(
                [[
                    'id' => $row['id'],
                    'user_id' => $row['user_id'] ?? null,
                    'action' => $row['action'] ?? 'unknown',
                    'subject_type' => $row['subject_type'] ?? null,
                    'subject_id' => $row['subject_id'] ?? null,
                    'description' => $row['description'] ?? '',
                    'changes' => isset($row['changes']) ? json_encode($row['changes']) : null,
                    'properties' => isset($row['properties']) ? json_encode($row['properties']) : null,
                    'created_at' => $row['created_at'] ?? now(),
                ]],
                ['id'],
                ['user_id', 'action', 'subject_type', 'subject_id', 'description', 'changes', 'properties', 'created_at'],
            );

            $restored++;
        }

        $this->reset('restoreFile');
        unset($this->entries, $this->actionOptions, $this->userOptions);

        Flux::toast(variant: 'success', text: trans_choice(':count entry restored.|:count entries restored.', $restored, ['count' => $restored]));
    }

    public function render(): View
    {
        return view('activity-log::livewire.activity-log-manager');
    }
}
