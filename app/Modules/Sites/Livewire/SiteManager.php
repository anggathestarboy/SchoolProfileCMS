<?php

namespace App\Modules\Sites\Livewire;

use App\Modules\Sites\Models\Site;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\Intl\Languages;

#[Title('Sites')]
class SiteManager extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $handle = '';

    public string $url = '';

    public string $locale = '';

    public string $lang = '';

    /**
     * Custom key/value attributes, mirroring Statamic's "Additional
     * Attributes" per site (https://statamic.dev/multi-site#additional-attributes).
     *
     * @var array<int, array{key: string, value: string}>
     */
    public array $customAttributes = [];

    #[Computed]
    public function sites()
    {
        return Site::orderBy('order')->get();
    }

    /**
     * Whether the current user can add/edit/delete/reorder sites — every
     * mutating method below already enforces this itself
     * (authorizePermission()), this just lets the view hide the buttons
     * instead of letting them 403 when clicked.
     */
    public function canConfigureSites(): bool
    {
        return auth()->user()->hasPermission('configure sites');
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function languageOptions(): array
    {
        $names = Languages::getNames(app()->getLocale());
        asort($names);

        return $names;
    }

    public function create(): void
    {
        auth()->user()->authorizePermission('configure sites');

        $this->reset(['editingId', 'name', 'handle', 'url', 'locale', 'lang', 'customAttributes']);
        Flux::modal('site-form')->show();
    }

    public function edit(int $id): void
    {
        auth()->user()->authorizePermission('configure sites');

        $site = Site::findOrFail($id);

        $this->editingId = $site->id;
        $this->name = $site->name;
        $this->handle = $site->handle;
        $this->url = $site->url;
        $this->locale = $site->locale;
        $this->lang = $site->lang;
        $this->customAttributes = collect($site->attributes ?? [])
            ->map(fn (mixed $value, string $key): array => ['key' => $key, 'value' => (string) $value])
            ->values()
            ->all();

        Flux::modal('site-form')->show();
    }

    public function regenerateHandle(): void
    {
        $this->handle = Str::slug($this->name, '_');
    }

    public function addAttribute(): void
    {
        $this->customAttributes[] = ['key' => '', 'value' => ''];
    }

    public function removeAttribute(int $index): void
    {
        unset($this->customAttributes[$index]);
        $this->customAttributes = array_values($this->customAttributes);
    }

    public function save(): void
    {
        auth()->user()->authorizePermission('configure sites');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:255', Rule::unique('sites', 'handle')->ignore($this->editingId)],
            'url' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:255'],
            'lang' => ['required', 'string', 'max:255'],
            'customAttributes.*.key' => ['nullable', 'string', 'max:255'],
            'customAttributes.*.value' => ['nullable', 'string', 'max:255'],
        ]);

        $attributes = collect($validated['customAttributes'] ?? [])
            ->filter(fn (array $row): bool => $row['key'] !== '' && $row['key'] !== null)
            ->pluck('value', 'key')
            ->all();

        $site = Site::updateOrCreate(['id' => $this->editingId], [
            'name' => $validated['name'],
            'handle' => $validated['handle'],
            'url' => $validated['url'],
            'locale' => $validated['locale'],
            'lang' => $validated['lang'],
            'attributes' => $attributes,
            'order' => $this->editingId ? Site::findOrFail($this->editingId)->order : ((int) Site::max('order') + 1),
        ]);

        Flux::modal('site-form')->close();
        Flux::toast(variant: 'success', text: __('Site saved.'));

        unset($this->sites);
    }

    public function delete(int $id): void
    {
        auth()->user()->authorizePermission('configure sites');

        $site = Site::findOrFail($id);

        if ($site->id === Site::orderBy('order')->first()?->id) {
            Flux::toast(variant: 'danger', text: __('The origin site cannot be deleted.'));

            return;
        }

        $site->delete();

        Flux::toast(variant: 'success', text: __('Site deleted.'));

        unset($this->sites);
    }

    public function moveUp(int $id): void
    {
        $this->swapOrder($id, -1);
    }

    public function moveDown(int $id): void
    {
        $this->swapOrder($id, 1);
    }

    protected function swapOrder(int $id, int $direction): void
    {
        auth()->user()->authorizePermission('configure sites');

        $site = Site::findOrFail($id);

        $neighbor = Site::where('order', $direction < 0 ? '<' : '>', $site->order)
            ->orderBy('order', $direction < 0 ? 'desc' : 'asc')
            ->first();

        if (! $neighbor) {
            return;
        }

        [$siteOrder, $neighborOrder] = [$site->order, $neighbor->order];

        $site->update(['order' => $neighborOrder]);
        $neighbor->update(['order' => $siteOrder]);

        unset($this->sites);
    }

    public function render(): View
    {
        return view('sites::livewire.site-manager');
    }
}
