<section class="w-full space-y-6" x-data="{ deleteSubmissionId: null, contextMenu: null, contextMenuX: 0, contextMenuY: 0 }" @click="contextMenu = null">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <flux:heading size="xl">{{ __(":title submissions", ["title" => $form->title]) }}</flux:heading>
    </div>

    <flux:table>
        <flux:table.columns>
            @foreach ($this->fields as $field)
                <flux:table.column>{{ $field["display"] ?: $field["handle"] }}</flux:table.column>
            @endforeach
            <flux:table.column>{{ __("Submitted") }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->submissions as $submission)
                <flux:table.row wire:key="submission-{{ $submission->id }}" x-on:contextmenu.prevent.stop="contextMenu = 'submission-{{ $submission->id }}'; contextMenuX = $event.clientX - 20; contextMenuY = $event.clientY + 10">
                    @foreach ($this->fields as $field)
                        @php $value = $submission->data[$field["handle"]] ?? null; @endphp
                        <flux:table.cell>@include ("forms::partials.submission-value", ["field" => $field, "value" => $value])</flux:table.cell>
                    @endforeach

                    <flux:table.cell>
                        <a href="{{ route('forms.submissions.show', ['form' => $form->handle, 'submission' => $submission->id]) }}" class="hover:text-orange-700" wire:navigate> {{ $submission->created_at?->format("Y-m-d | H:i") }} </a>
                    </flux:table.cell>

                    <flux:table.cell class="flex justify-end gap-2">
                        <flux:dropdown>
                            <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                            <flux:menu>
                                <flux:menu.item icon="eye" :href="route('forms.submissions.show', ['form' => $form->handle, 'submission' => $submission->id])" wire:navigate>{{ __("View") }}</flux:menu.item>
                                <flux:modal.trigger name="confirm-delete-submission">
                                    <flux:menu.item icon="trash" variant="danger" x-on:click="deleteSubmissionId = '{{ $submission->id }}'">{{ __("Delete") }}</flux:menu.item>
                                </flux:modal.trigger>
                            </flux:menu>
                        </flux:dropdown>

                        <div x-show="contextMenu === 'submission-{{ $submission->id }}'" x-cloak x-on:click.outside="contextMenu = null" :style="{ left: contextMenuX + 'px', top: contextMenuY + 'px' }" class="fixed z-50 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                            <a href="{{ route('forms.submissions.show', ['form' => $form->handle, 'submission' => $submission->id]) }}" wire:navigate x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("View") }}</a>
                            <flux:modal.trigger name="confirm-delete-submission">
                                <button type="button" x-on:click="deleteSubmissionId = '{{ $submission->id }}'; contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Delete") }}</button>
                            </flux:modal.trigger>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="{{ count($this->fields) + 2 }}">{{ __("No submissions yet.") }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="confirm-delete-submission" class="min-w-[24rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this submission?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteSubmissionId)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
