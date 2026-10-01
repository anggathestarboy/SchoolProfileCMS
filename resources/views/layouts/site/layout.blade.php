@props ([
    "title" => null,
    "site" => null,
    "entry" => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $site?->lang ?? app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ filled($title) ? $title . " - " . config("app.name") : config("app.name") }}</title>
    <link rel="icon" href="/favicon.png" sizes="any" />
    @fonts
    @vite (["resources/css/site.css"])

    @php $siteSettings = \App\Modules\SiteSettings\Models\SiteSetting::current(); @endphp

    @if ($siteSettings->googleSearchConsoleEnabled() && $siteSettings->googleSearchConsoleVerificationCode())
        <meta name="google-site-verification" content="{{ $siteSettings->googleSearchConsoleVerificationCode() }}" />
    @endif

    @if ($siteSettings->googleAnalyticsEnabled() && $siteSettings->googleAnalyticsMeasurementId())
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $siteSettings->googleAnalyticsMeasurementId() }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() {
                dataLayer.push(arguments);
            }
            gtag("js", new Date());
            gtag("config", "{{ $siteSettings->googleAnalyticsMeasurementId() }}");
        </script>
    @endif

    @foreach ($siteSettings->structuredDataBlocks() as $structuredData)
        <script type="application/ld+json">
            {!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endforeach

    @if ($entry)
        @foreach ($entry->structuredDataBlocks() as $structuredData)
            <script type="application/ld+json">
                {!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
            </script>
        @endforeach
    @endif
</head>
<body class="">
    <div id="cms-preview-frame" style="overflow-x: hidden">
        <x-layouts::site.header :site="$site" />
        <main class="">{{ $slot }}</main>
        <x-layouts::site.footer :site="$site" />
    </div>

    @if (request()->route()?->named("collections.preview"))
        <style>
            @keyframes cms-preview-pulse {
                0%,
                100% {
                    opacity: 1;
                }
                50% {
                    opacity: 0.35;
                }
            }
            @keyframes cms-preview-spin {
                to {
                    transform: rotate(360deg);
                }
            }
            #cms-preview-status-dot {
                display: inline-block;
                flex-shrink: 0;
                border-radius: 9999px;
            }
            #cms-preview-status-dot.is-live {
                width: 8px;
                height: 8px;
                background: #22c55e;
                animation: cms-preview-pulse 1.6s ease-in-out infinite;
            }
            #cms-preview-status-dot.is-updating {
                width: 10px;
                height: 10px;
                background: transparent;
                border: 2px solid #e4e4e7;
                border-top-color: #f59e0b;
                animation: cms-preview-spin 0.6s linear infinite;
            }
        </style>

        {{-- Floating status + device-width toggle — this preview always opens as its own page (not embedded in the CMS), so both live here. The status dot/label reflects the auto-refresh poll below: pulsing green "Live" while idle, spinning amber "Updating…" while a change made in the editor (not yet saved) is being pulled in. --}}
        <div style="position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.95); border: 1px solid #e4e4e7; border-radius: 9999px; padding: 4px 4px 4px 12px; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, sans-serif">
            <span id="cms-preview-status-dot" class="is-live"></span>
            <span id="cms-preview-status-label" style="font-size: 12px; color: #71717a; white-space: nowrap; min-width: 52px">{{ __("Live") }}</span>

            <span style="width: 1px; height: 24px; background: #e4e4e7; flex-shrink: 0"></span>

            <button type="button" data-preview-device="desktop" title="Desktop" style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 9999px; border: none; cursor: pointer; background: #18181b; color: #fff">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" /></svg>
            </button>
            <button type="button" data-preview-device="tablet" title="Tablet" style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 9999px; border: none; cursor: pointer; background: transparent; color: #71717a">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-15a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
            </button>
            <button type="button" data-preview-device="mobile" title="Mobile" style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 9999px; border: none; cursor: pointer; background: transparent; color: #71717a">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>
            </button>
        </div>
        <script>
            (() => {
                const url = "{{ request()->url() }}";
                let version = @js ((int) ($cms_preview_version ?? 0));

                const statusDot = document.getElementById("cms-preview-status-dot");
                const statusLabel = document.getElementById("cms-preview-status-label");

                const setStatus = (state) => {
                    statusDot.className = state === "updating" ? "is-updating" : "is-live";
                    statusLabel.textContent = state === "updating" ? @js (__("Updating…")) : @js (__("Live"));
                };

                setInterval(() => {
                    fetch(url + "?version=1", { headers: { accept: "application/json" } })
                        .then((r) => (r.ok ? r.json() : null))
                        .then((p) => {
                            if (!p || p.version === version) return;
                            version = p.version;
                            setStatus("updating");

                            fetch(url, { headers: { accept: "text/html" } })
                                .then((r) => r.text())
                                .then((html) => {
                                    const main = new DOMParser().parseFromString(html, "text/html").querySelector("main");
                                    const target = document.querySelector("main");
                                    if (main && target) target.innerHTML = main.innerHTML;
                                })
                                .catch(() => {})
                                .finally(() => setStatus("live"));
                        })
                        .catch(() => {});
                }, 2000);
            })();
        </script>
        <script>
            (() => {
                const frame = document.getElementById("cms-preview-frame");
                const widths = { desktop: "", tablet: "768px", mobile: "390px" };
                const buttons = document.querySelectorAll("[data-preview-device]");

                buttons.forEach((btn) => {
                    btn.addEventListener("click", () => {
                        const device = btn.getAttribute("data-preview-device");
                        frame.style.maxWidth = widths[device];
                        frame.style.margin = device === "desktop" ? "" : "0 auto";
                        frame.style.transition = "max-width .2s ease";
                        frame.style.outline = device === "desktop" ? "" : "1px solid #e4e4e7";
                        frame.style.boxShadow = device === "desktop" ? "" : "0 0 0 9999px rgba(0,0,0,.08)";

                        buttons.forEach((b) => {
                            b.style.background = "transparent";
                            b.style.color = "#71717a";
                        });
                        btn.style.background = "#18181b";
                        btn.style.color = "#fff";
                    });
                });
            })();
        </script>
    @endif
</body>
</html>
