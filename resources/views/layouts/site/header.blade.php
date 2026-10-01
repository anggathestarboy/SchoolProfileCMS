@props ([
    "site" => null,
])

@php
    $treeService = app(\App\Modules\Structure\Services\TreeService::class);
    $navData = ($site ? $treeService->load("navigation", "header", $site->handle) : []) ?: $treeService->load("navigation", "header");

    $logo = ($navData["site_logo"] ?? null) ? \App\Modules\Media\Models\AssetMeta::find(\Illuminate\Support\Arr::first((array) $navData["site_logo"])) : null;
    $sites =\App\Modules\Sites\Models\Site::orderBy("order")->get();

    // Link fields store either a plain URL or "entry::{id}"; resolve the latter to that entry's public URL on this site.
    $resolveLink = function (?string $link) use ($site): string {
        if (! str_starts_with((string) $link, "entry::")) {
            return $link ?: "#";
        }

        $entry = \App\Modules\Collections\Models\Entry::entryForSite(substr($link, 7), $site?->handle ?? "");
        $collection = $entry ? \App\Modules\Collections\Models\Collection::where("handle", $entry->collection)->first() : null;

        return $entry?->published && $collection ? app(\App\Modules\Collections\Services\EntryUrlResolver::class)->url($collection, $entry) ?? "#" : "#";
    };
@endphp

<header class="sticky top-0 z-99 bg-cbGreen-1 text-white">
    <div class="container mx-auto flex items-center justify-between py-4">
        <a href="/" class="heading-20-semibold text-white">
            @if ($logo)
                <img src="{{ $logo->url }}" alt="{{ $site?->name ?? config("app.name") }}" class="h-20 w-auto" />
            @else
                {{ $site?->name ?? config("app.name") }}
            @endif
        </a>

        <div class="flex items-center gap-6">
        @if (! empty($navData["navbar_loop_copy"]))
            <nav aria-label="{{ __('Main navigation') }}">
                <ul class="flex items-center gap-6">
                    @foreach ($navData["navbar_loop_copy"] as $menu)
                        @php $children = $menu["child_menu_loop"] ?? []; @endphp
                        <li class="group relative">
                            <a href="{{ $resolveLink($menu["menu_link"] ?? null) }}" class="paragraph-14-medium text-white hover:text-cbYellow-2">
                                {{ $menu["menu_label"] ?? "" }}
                            </a>

                            @if ($children)
                                <ul class="invisible absolute left-0 top-full min-w-48 rounded-md border border-zinc-200 bg-white py-1 opacity-0 shadow-lg transition group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
                                    @foreach ($children as $child)
                                        <li>
                                            <a href="{{ $resolveLink($child["child_link"] ?? null) }}" class="block px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-100">
                                                {{ $child["child_title"] ?? "" }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($sites->count() > 1)
            <select aria-label="{{ __('Language') }}" onchange="window.location.href = this.value" class="paragraph-14-medium rounded-md border border-white/40 bg-cbGreen-1 px-2 py-1 text-white">
                @foreach ($sites as $otherSite)
                    <option value="{{ $otherSite->url ?: '/' }}" @selected($otherSite->handle === $site?->handle)>{{ $otherSite->name }}</option>
                @endforeach
            </select>
        @endif
        </div>

        {{ $slot ?? "" }}
    </div>
</header>
