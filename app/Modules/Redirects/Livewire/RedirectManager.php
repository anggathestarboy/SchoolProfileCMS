<?php

namespace App\Modules\Redirects\Livewire;

use App\Modules\Redirects\Models\Redirect;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Redirect Manager')]
class RedirectManager extends Component
{
    public string $search = '';

    public ?int $editingId = null;

    public string $source = '';

    public string $target = '';

    public int $status_code = 301;

    public string $match_type = 'exact';

    public bool $is_active = true;

    public ?string $start_at = null;

    public ?string $end_at = null;

    public bool $is_external = false;

    public bool $keep_path = true;

    public int $hits = 0;

    #[Computed]
    public function redirects()
    {
        return Redirect::when($this->search !== '', function ($query) {
            $query->where(function ($query) {
                $query->where('source', 'like', "%{$this->search}%")
                    ->orWhere('target', 'like', "%{$this->search}%");
            });
        })
            ->orderBy('source')
            ->get();
    }

    public function create(): void
    {
        $this->reset(['editingId', 'source', 'target', 'status_code', 'match_type', 'is_active', 'start_at', 'end_at', 'is_external', 'keep_path', 'hits']);
        $this->status_code = 301;
        $this->match_type = 'exact';
        $this->is_active = true;
        $this->keep_path = true;

        Flux::modal('redirect-form')->show();
    }

    public function view(int $id): void
    {
        $this->fillFrom(Redirect::findOrFail($id));
        Flux::modal('redirect-form')->show();
    }

    /**
     * "Keep path" only means something for a prefix/regex match — an exact
     * match has no leftover suffix to append, so force it off (and the
     * toggle hides itself in the view) the moment match type becomes exact,
     * rather than silently saving a stale true from before the switch.
     */
    public function updatedMatchType(): void
    {
        if ($this->match_type === 'exact') {
            $this->keep_path = false;
        }
    }

    public function delete(int $id): void
    {
        Redirect::findOrFail($id)->delete();

        Flux::toast(variant: 'success', text: __('Redirect deleted.'));

        unset($this->redirects);
    }

    public function save(): void
    {
        // An emptied datetime-local input submits "" rather than null, which
        // MySQL rejects as an invalid datetime — normalize before validating
        // so clearing the field actually clears it instead of failing the
        // whole save (silently leaving every other field unsaved too, since
        // it's all one UPDATE statement).
        $this->start_at = $this->start_at ?: null;
        $this->end_at = $this->end_at ?: null;

        // Belt-and-braces alongside updatedMatchType(): an exact match has
        // no leftover path to keep, regardless of how match_type got here.
        if ($this->match_type === 'exact') {
            $this->keep_path = false;
        }

        $validated = $this->validate([
            'source' => ['required', 'string', 'max:255', Rule::unique('redirects', 'source')->ignore($this->editingId)],
            'target' => ['required', 'string', 'max:2048'],
            'status_code' => ['required', 'integer', 'in:301,302,307,308,410,451'],
            'match_type' => ['required', 'string', 'in:exact,prefix,regex'],
            'is_active' => ['boolean'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_external' => ['boolean'],
            'keep_path' => ['boolean'],
        ]);

        Redirect::updateOrCreate(['id' => $this->editingId], $validated);

        Flux::modal('redirect-form')->close();
        unset($this->redirects);

        Flux::toast(variant: 'success', text: __('Redirect saved.'));
    }

    protected function fillFrom(Redirect $redirect): void
    {
        $this->editingId = $redirect->id;
        $this->source = $redirect->source;
        $this->target = $redirect->target;
        $this->status_code = $redirect->status_code;
        $this->match_type = $redirect->match_type;
        $this->is_active = $redirect->is_active;
        $this->start_at = $redirect->start_at?->format('Y-m-d\TH:i');
        $this->end_at = $redirect->end_at?->format('Y-m-d\TH:i');
        $this->is_external = $redirect->is_external;
        $this->keep_path = $redirect->keep_path;
        $this->hits = $redirect->hits;
    }

    public function render(): View
    {
        return view('redirects::livewire.redirect-manager');
    }
}
