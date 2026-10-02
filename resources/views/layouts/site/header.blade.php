@props ([
    "site" => null,
])

@php
    $treeService = app(\App\Modules\Structure\Services\TreeService::class);
    $navData = ($site ? $treeService->load("navigation", "header", $site->handle) : []) ?: $treeService->load("navigation", "header");

    $logo = ($navData["site_logo"] ?? null) ? \App\Modules\Media\Models\AssetMeta::find(\Illuminate\Support\Arr::first((array) $navData["site_logo"])) : null;
    $sites = \App\Modules\Sites\Models\Site::orderBy("order")->get();

    $resolveLink = function (?string $link) use ($site): string {
        if (! str_starts_with((string) $link, "entry::")) {
            return $link ?: "#";
        }

        $entry = \App\Modules\Collections\Models\Entry::entryForSite(substr($link, 7), $site?->handle ?? "");
        $collection = $entry ? \App\Modules\Collections\Models\Collection::where("handle", $entry->collection)->first() : null;

        return $entry?->published && $collection ? app(\App\Modules\Collections\Services\EntryUrlResolver::class)->url($collection, $entry) ?? "#" : "#";
    };
@endphp

<header class="sticky top-0 z-50 bg-cbGreen-1 text-white">
    <div class="container mx-auto flex items-center justify-between py-4 px-4 sm:px-6 lg:px-8">
        <a href="/" class="heading-20-semibold text-white">
            @if ($logo)
                <img src="{{ $logo->url }}" alt="{{ $site?->name ?? config("app.name") }}" class="h-16 lg:h-20 w-auto" />
            @else
                {{ $site?->name ?? config("app.name") }}
            @endif
        </a>

        <div class="hidden lg:flex items-center gap-6">
            @if (! empty($navData["navbar_loop_copy"]))
                <nav aria-label="{{ __('Main navigation') }}">
                    <ul class="flex items-center gap-6">
                        @foreach ($navData["navbar_loop_copy"] as $menu)
                            @php $children = $menu["child_menu_loop"] ?? []; @endphp
                            <li class="group relative py-2">
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

        <button id="hamburger-btn" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-white hover:text-cbYellow-2 lg:hidden focus:outline-none" aria-label="Toggle Menu">
            <svg id="hamburger-icon-open" class="h-7 w-7 block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
            <svg id="hamburger-icon-close" class="h-7 w-7 hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        {{ $slot ?? "" }}
    </div>

    <div id="mobile-menu" class="hidden lg:hidden bg-cbGreen-1 border-t border-white/10 px-4 sm:px-6 pt-2 pb-6 space-y-4">
        @if (! empty($navData["navbar_loop_copy"]))
            <nav aria-label="{{ __('Mobile navigation') }}">
                <ul class="flex flex-col space-y-2">
                    @foreach ($navData["navbar_loop_copy"] as $menu)
                        @php $children = $menu["child_menu_loop"] ?? []; @endphp
                        <li>
                            <div class="flex items-center justify-between py-2 border-b border-white/10">
                                <a href="{{ $resolveLink($menu["menu_link"] ?? null) }}" class="paragraph-16-medium text-white hover:text-cbYellow-2">
                                    {{ $menu["menu_label"] ?? "" }}
                                </a>
                                @if ($children)
                                    <button type="button" class="mobile-dropdown-btn p-1 text-white hover:text-cbYellow-2 focus:outline-none">
                                        <svg class="dropdown-icon h-5 w-5 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                @endif
                            </div>

                            @if ($children)
                                <ul class="mobile-dropdown-content hidden pl-4 py-2 space-y-2 bg-black/10 rounded-md mt-1">
                                    @foreach ($children as $child)
                                        <li>
                                            <a href="{{ $resolveLink($child["child_link"] ?? null) }}" class="block py-1 paragraph-14 text-white/80 hover:text-cbYellow-2">
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
            <div class="pt-2">
                <select aria-label="{{ __('Language') }}" onchange="window.location.href = this.value" class="w-full paragraph-14-medium rounded-md border border-white/40 bg-cbGreen-1 px-3 py-2 text-white">
                    @foreach ($sites as $otherSite)
                        <option value="{{ $otherSite->url ?: '/' }}" @selected($otherSite->handle === $site?->handle)>{{ $otherSite->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>
</header>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const hamburgerBtn = document.getElementById("hamburger-btn");
        const mobileMenu = document.getElementById("mobile-menu");
        const iconOpen = document.getElementById("hamburger-icon-open");
        const iconClose = document.getElementById("hamburger-icon-close");

        if (hamburgerBtn && mobileMenu) {
            hamburgerBtn.addEventListener("click", function () {
                const isHidden = mobileMenu.classList.contains("hidden");
                
                if (isHidden) {
                    mobileMenu.classList.remove("hidden");
                    iconOpen.classList.add("hidden");
                    iconOpen.classList.remove("block");
                    iconClose.classList.remove("hidden");
                    iconClose.classList.add("block");
                } else {
                    mobileMenu.classList.add("hidden");
                    iconOpen.classList.remove("hidden");
                    iconOpen.classList.add("block");
                    iconClose.classList.add("hidden");
                    iconClose.classList.remove("block");
                }
            });
        }

        const dropdownBtns = document.querySelectorAll(".mobile-dropdown-btn");
        
        dropdownBtns.forEach(function (btn) {
            btn.addEventListener("click", function () {
                const parentLi = btn.closest("li");
                const dropdownContent = parentLi.querySelector(".mobile-dropdown-content");
                const icon = btn.querySelector(".dropdown-icon");

                if (dropdownContent) {
                    const isHidden = dropdownContent.classList.contains("hidden");
                    
                    if (isHidden) {
                        dropdownContent.classList.remove("hidden");
                        if (icon) icon.classList.add("rotate-180");
                    } else {
                        dropdownContent.classList.add("hidden");
                        if (icon) icon.classList.remove("rotate-180");
                    }
                }
            });
        });
    });
</script>