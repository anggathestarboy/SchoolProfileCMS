<section class="w-full space-y-6" x-data="{ viewing: null }">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <flux:icon.clock class="w-6 h-6 text-gray-800 dark:text-white" />
            <flux:heading size="xl">{{ __("Activity Log") }}</flux:heading>
        </div>
    </div>

    @if ($this->canExport || $this->canImport)
        <div class="flex flex-wrap items-end gap-4 border py-3 px-4 rounded-xl dark:border-zinc-500">
            @if ($this->canExport)
                <div class="flex items-end gap-2">
                    <flux:select wire:model.live="exportPeriod" size="sm">
                        <flux:select.option value="month">{{ __("Per month") }}</flux:select.option>
                        <flux:select.option value="year">{{ __("Per year") }}</flux:select.option>
                    </flux:select>

                    @if ($exportPeriod === "year")
                        <flux:input type="number" wire:model="exportYear" size="sm" class="w-24" />
                    @else
                        <flux:input type="month" wire:model="exportMonth" size="sm" />
                    @endif

                    <flux:button size="sm" wire:click="export" icon="arrow-down-tray">{{ __("Export") }}</flux:button>
                </div>
            @endif

            @if ($this->canImport)
                <form wire:submit="restore" class="flex items-end gap-2">
                    <flux:input type="file" wire:model="restoreFile" accept="application/json" size="sm" />
                    <flux:button type="submit" size="sm" variant="ghost" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="restoreFile,restore">{{ __("Restore") }}</flux:button>
                </form>
                @error ("restoreFile")
                    <span class="text-sm text-red-500">{{ $message }}</span>
                @enderror
            @endif
        </div>
    @endif

    @if ($this->isSuperAdmin)
        <div class="space-y-3 border border-red-300 dark:border-red-900 py-3 px-4 rounded-xl" x-data="{ confirming: false }">
            <flux:heading size="sm" class="text-red-600 dark:text-red-400">{{ __("Danger zone — permanently delete a year") }}</flux:heading>
            <flux:text size="sm">{{ __("Superadmin only. Deleting is permanent (hard delete, no undo) and requires confirming a link sent to your email before it actually runs, at 00:00 the following night.") }}</flux:text>

            <div class="flex items-end gap-2">
                <flux:input type="number" wire:model="deleteYear" size="sm" class="w-28" />
                <flux:button size="sm" variant="danger" x-on:click="confirming = true">{{ __("Request Delete") }}</flux:button>
            </div>
            @error ("deleteYear")
                <span class="text-sm text-red-500">{{ $message }}</span>
            @enderror

            <div x-show="confirming" x-cloak x-on:click.self="confirming = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-sm rounded-xl bg-white p-4 dark:bg-zinc-800" x-on:click.stop>
                    <flux:heading size="lg">{{ __("Are you sure?") }}</flux:heading>
                    <flux:text class="mt-2">{{ __("This will send you a confirmation email for permanently deleting all :year activity log entries. Nothing happens until you click the link in that email.", ["year" => $deleteYear]) }}</flux:text>
                    <div class="mt-4 flex justify-end gap-2">
                        <flux:button size="sm" variant="ghost" x-on:click="confirming = false">{{ __("Cancel") }}</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="requestDeletion" x-on:click="confirming = false">{{ __("Send confirmation email") }}</flux:button>
                    </div>
                </div>
            </div>

            @if ($this->deletionRequests->isNotEmpty())
                <div class="space-y-1 pt-2">
                    @foreach ($this->deletionRequests as $request)
                        <div class="flex items-center gap-2 text-sm">
                            <flux:badge size="sm" :color="$request->status === 'confirmed' ? 'amber' : 'zinc'">{{ $request->status }}</flux:badge>
                            <span>{{ __(":year — requested :time", ["year" => $request->year, "time" => $request->created_at->diffForHumans()]) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="flex gap-3 flex-wrap justify-between">
        <flux:input wire:model.live.debounce.400ms="search" :placeholder="__('Search description…')" icon="magnifying-glass" size="sm" class="lg:w-[50%]" />

        <flux:select wire:model.live="action" size="sm" class="lg:w-[24%]">
            <flux:select.option value="">{{ __("All actions") }}</flux:select.option>
            @foreach ($this->actionOptions as $option)
                <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="userId" size="sm" class="lg:w-[24%]">
            <flux:select.option value="">{{ __("All users") }}</flux:select.option>
            @foreach ($this->userOptions as $option)
                <flux:select.option value="{{ $option->id }}">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-center gap-2">
            <flux:input type="date" wire:model.live="dateFrom" size="sm" />
            <flux:input type="date" wire:model.live="dateTo" size="sm" />
        </div>
    </div>

    @if ($search !== "" || $action !== "" || $userId !== "" || $dateFrom !== "" || $dateTo !== "")
        <flux:button size="sm" variant="ghost" wire:click="resetFilters">{{ __("Clear filters") }}</flux:button>
    @endif

    <div class="bg-gray-200 dark:bg-zinc-900 p-1.5 rounded-xl">
        <div class="bg-white dark:bg-zinc-800 rounded-xl overflow-hidden">
            <flux:table>
                <flux:table.columns class="bg-gray-200 dark:bg-zinc-900 [&>*]:text-white">
                    <flux:table.column>{{ __("When") }}</flux:table.column>
                    <flux:table.column>{{ __("User") }}</flux:table.column>
                    <flux:table.column>{{ __("Action") }}</flux:table.column>
                    <flux:table.column>{{ __("Description") }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->entries as $entry)
                        <flux:table.row wire:key="activity-{{ $entry->id }}">
                            <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $entry->created_at?->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->user?->name ?? __("System") }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="match (true) {
        str_contains($entry->action, 'delete') => 'red',
        str_contains($entry->action, 'fail') => 'red',
        str_contains($entry->action, 'ban') => 'amber',
        str_contains($entry->action, 'creat') => 'green',
        default => 'zinc',
    }">{{ $entry->action }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $entry->description }}</flux:table.cell>
                            <flux:table.cell class="flex justify-end">
                                @if ($this->canViewDetail && !empty($entry->changes))
                                    <flux:button size="xs" variant="ghost" icon="eye" x-on:click="viewing = @js($entry->changes)" title="{{ __('View changes') }}" />
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">{{ __("No activity recorded yet.") }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    {{ $this->entries->links() }}

    <div x-show="viewing" x-cloak x-on:click.self="viewing = null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="max-h-[80vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-4 dark:bg-zinc-800" x-on:click.stop>
            <div class="mb-2 flex items-center justify-between">
                <flux:heading size="lg">{{ __("Changes") }}</flux:heading>
                <flux:button size="sm" variant="ghost" icon="x-mark" x-on:click="viewing = null" />
            </div>
            <pre class="overflow-x-auto rounded-lg bg-zinc-100 p-3 text-xs dark:bg-zinc-900" x-text="JSON.stringify(viewing, null, 2)"></pre>
        </div>
    </div>
</section>
