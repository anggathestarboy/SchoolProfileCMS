<x-layouts::app :title="__('Submission')">
    <section class="w-full max-w-3xl space-y-6">
        <div class="print:hidden flex items-center flex-col sm:flex-row justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
            <flux:heading size="xl" class="mb-0!">{{ __("Submission") }}</flux:heading>
            <flux:subheading>{{ $submission->created_at?->format("Y-m-d H:i") }}</flux:subheading>
        </div>

        <div class="hidden print:block">
            <flux:heading size="lg">{{ $form->title }} — {{ __("Submission") }}</flux:heading>
            <flux:subheading>{{ $submission->created_at?->format("Y-m-d H:i") }}</flux:subheading>
        </div>

        <div class="flex gap-2 print:hidden overflow-x-auto">
            <flux:button size="sm" type="button" icon="printer" onclick="window.print()">{{ __("Export PDF") }}</flux:button>
            <flux:button size="sm" :href="route('forms.submissions.export.txt', ['form' => $form->handle, 'submission' => $submission->id])">{{ __("Export TXT") }}</flux:button>
            <flux:button size="sm" :href="route('forms.submissions.export.json', ['form' => $form->handle, 'submission' => $submission->id])">{{ __("Export JSON") }}</flux:button>
        </div>

        <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            @forelse ($fields as $field)
                @php $value = $submission->data[$field["handle"]] ?? null; @endphp
                <div>
                    <flux:text class="text-xs font-medium text-zinc-500">{{ $field["display"] ?: $field["handle"] }}</flux:text>
                    <flux:text class="block whitespace-pre-wrap">@include ("forms::partials.submission-value", ["field" => $field, "value" => $value])</flux:text>
                </div>
            @empty
                <flux:text class="text-zinc-500">{{ __("This form has no fields.") }}</flux:text>
            @endforelse
        </div>
    </section>
</x-layouts::app>
