@php $content = view()->make('errors.partials.admin-404-content'); @endphp

@if (auth()->check())
    <x-layouts::app :title="__('Page not found')">
        {{ $content }}
    </x-layouts::app>
@else
    <!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include ("components.partials.head", ["title" => __("Page not found")])
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900">
        {{ $content }}
    </body>
    </html>
@endif
