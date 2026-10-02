<x-layouts::site.layout :title="$data['title'] ?? null" :site="$site ?? null">
    @php
        if (is_array($data->hero_image ?? null)) {
            $data->hero_image = \App\Modules\Media\Models\AssetMeta::find(
                \Illuminate\Support\Arr::first($data->hero_image),
            );
        }
    @endphp
    <section class="relative w-full h-[320px] md:h-[400px] lg:h-[480px] flex items-center bg-zinc-900 overflow-hidden">
        <img src="{{ $data->hero_image->url }}" alt="Hero Background"
            class="absolute inset-0 w-full h-full object-cover object-center" />

        <div class="absolute inset-0 bg-black/60"></div>

        <div class="container relative z-10 mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6 lg:gap-8 items-center">

                <div class="md:col-span-5 lg:col-span-5">
                    <h1 class="heading-44-bold text-white tracking-wide uppercase">
                        {{ $data->hero_title }}
                    </h1>
                </div>

                <div class="md:col-span-7 lg:col-span-7">
                    <div class="paragraph-16 text-white/90 leading-relaxed max-w-2xl">
                        {{ $data->hero_desc }}
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section class="py-12 lg:py-20 bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">

                <div class="lg:col-span-4">
                    @php
                        $sideMenu =
                            \App\Models\Tree::forNavigations()
                                ->where('handle', 'side-menu')
                                ->where('locale', $site->handle ?? null)
                                ->first()?->tree['menu_loop'] ?? [];
                    @endphp
                    <div class="sticky top-28 space-y-2">
                        @foreach ($sideMenu as $item)
                            @php
                                $link = $item['menu_link'] ?? '#';
                                $linkedEntry = str_starts_with($link, 'entry::')
                                    ? \App\Modules\Collections\Models\Entry::find(substr($link, 7))
                                    : null;
                                $isActive = $linkedEntry
                                    ? $linkedEntry->id === $entry->id
                                    : ($link !== '#' && url($link) === url()->current());
                            @endphp
                            <a href="{{ $linkedEntry ? url($linkedEntry->uri) : $link }}"
                                class="block w-full px-5 py-3 rounded-md heading-16-medium transition-colors {{ $isActive ? 'bg-[#266210] text-white' : 'text-zinc-600 hover:text-[#90b800    ] hover:bg-zinc-50' }}">
                                {{ $item['menu_label'] ?? '' }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div
                    class="lg:col-span-8 space-y-4 text-gray-800 [&_h1]:text-3xl [&_h1]:font-bold [&_h2]:text-2xl [&_h2]:font-bold [&_h3]:text-xl [&_h3]:font-semibold [&_p]:leading-relaxed [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:text-blue-600 [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-gray-300 [&_blockquote]:pl-4 [&_blockquote]:italic [&_img]:rounded-lg [&_img]:max-w-full">
                    {!! $data->about_content !!}
                </div>

            </div>
        </div>
    </section>
</x-layouts::site.layout>
