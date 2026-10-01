<div class="flex min-h-dvh flex-col items-center justify-center gap-4 px-6 text-center">
    <flux:heading size="xl">{{ __("This admin page doesn't exist") }}</flux:heading>
    <flux:text class="max-w-md">{{ __("Check the URL, or it may have moved. If a link brought you here, let whoever set it up know.") }}</flux:text>
    <flux:button href="{{ route('dashboard') }}" variant="primary" size="sm" wire:navigate class="mt-2">{{ __("Take Me Back") }}</flux:button>
</div>
