@props ([
    "site" => null,
])

<footer class="">
    <div class="container mx-auto py-4">&copy; {{ now()->year }} {{ $site?->name ?? config("app.name") }}. {{ __("All rights reserved.") }}</div>
</footer>
