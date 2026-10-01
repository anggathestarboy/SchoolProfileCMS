@if (str_starts_with(trim(request()->path(), '/'), config('cms.route')))
    @include ('errors.admin-404')
@else
    @php
        [$site] = app(\App\Modules\Collections\Http\Controllers\PageController::class)->resolveSite(request(), trim(request()->path(), '/'));
    @endphp
    <x-layouts::site.layout :title="__('Page not found')" :site="$site">
        <div class="mx-auto flex max-w-5xl flex-col items-center gap-4 px-6 py-24 text-center">
            <span class="text-sm font-semibold tracking-widest text-zinc-400 uppercase">{{ __('Error 404') }}</span>
            <h1 class="text-3xl font-bold text-zinc-900 sm:text-4xl">{{ __("We couldn't find that page") }}</h1>
            <p class="max-w-md text-zinc-500">{{ __("The page you're looking for may have been moved, renamed, or no longer exists.") }}</p>
            <a href="{{ $site?->url ?: '/' }}" class="mt-4 rounded-lg bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-zinc-700">
                {{ __('Back to homepage') }}
            </a>
        </div>
    </x-layouts::site.layout>
@endif
