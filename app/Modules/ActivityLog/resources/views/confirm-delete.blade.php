<x-layouts::app :title="__('Confirm deletion')">
    <div class="mx-auto max-w-lg space-y-4 py-10 text-center">
        @if ($status === 'confirmed')
            <flux:icon.check-circle class="mx-auto h-10 w-10 text-green-500" />
            <flux:heading size="lg">{{ __('Deletion confirmed') }}</flux:heading>
            <flux:text>{{ __('Activity log entries from :year are scheduled for permanent deletion tonight at 00:00.', ['year' => $year]) }}</flux:text>
        @elseif ($status === 'already_confirmed')
            <flux:icon.check-circle class="mx-auto h-10 w-10 text-amber-500" />
            <flux:heading size="lg">{{ __('Already confirmed') }}</flux:heading>
            <flux:text>{{ __('This request was already confirmed and is waiting for tonight\'s scheduled run.') }}</flux:text>
        @elseif ($status === 'already_executed')
            <flux:icon.check-circle class="mx-auto h-10 w-10 text-zinc-500" />
            <flux:heading size="lg">{{ __('Already executed') }}</flux:heading>
            <flux:text>{{ __('Those entries have already been permanently deleted.') }}</flux:text>
        @elseif ($status === 'expired')
            <flux:icon.x-circle class="mx-auto h-10 w-10 text-red-500" />
            <flux:heading size="lg">{{ __('Link expired') }}</flux:heading>
            <flux:text>{{ __('This confirmation link expired 24 hours after it was sent. Request the deletion again from the Activity Log page.') }}</flux:text>
        @elseif ($status === 'forbidden')
            <flux:icon.x-circle class="mx-auto h-10 w-10 text-red-500" />
            <flux:heading size="lg">{{ __('Not authorized') }}</flux:heading>
            <flux:text>{{ __('Only a superadmin can confirm this.') }}</flux:text>
        @else
            <flux:icon.x-circle class="mx-auto h-10 w-10 text-red-500" />
            <flux:heading size="lg">{{ __('Request not found') }}</flux:heading>
        @endif

        <flux:button href="{{ route('activity-log.activity-log.index') }}" wire:navigate>{{ __('Back to Activity Log') }}</flux:button>
    </div>
</x-layouts::app>
