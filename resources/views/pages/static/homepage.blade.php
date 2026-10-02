@php
    if (is_array($data->hero_image ?? null)) {
        $data->hero_image = \App\Modules\Media\Models\AssetMeta::find(
            \Illuminate\Support\Arr::first($data->hero_image),
        );
    }
    if (is_array($data->overview_image ?? null)) {
        $data->overview_image = \App\Modules\Media\Models\AssetMeta::find(
            \Illuminate\Support\Arr::first($data->overview_image),
        );
    }
@endphp

<x-layouts::site.layout :title="$data->heading ?? $data->title" :site="$site ?? null">
    <section
        class="relative min-h-[500px] lg:min-h-[600px] w-full flex items-center bg-gray-900 text-white overflow-hidden">
        <div class="absolute inset-0 z-0">
            @if ($data->hero_image)
                <img src="{{ $data->hero_image->url }}" alt="{{ $data->hero_image->alt }}"
                    class="w-full h-full object-cover object-center" />
            @endif
            <div class="absolute inset-0 bg-black/40 bg-gradient-to-r from-black/70 via-black/40 to-transparent"></div>
        </div>

        <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10 py-16 lg:py-24">
            <div class="max-w-xl lg:max-w-2xl">
                <h1 class="heading-44-bold uppercase text-white mb-4 sm:mb-6 leading-tight">
                    {{ $data->hero_title }}
                </h1>

                <p class="paragraph-16 text-gray-200 opacity-90 max-w-lg">
                    {{ $data->hero_desc }}
                </p>
            </div>
        </div>
    </section>

    <section class="py-12 lg:py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">

                <div class="lg:col-span-7 space-y-2">
                    <span class="inline-block text-[#266210] heading-18-bold tracking-wider uppercase">
                        {{ $data->overview_label }}
                    </span>

                    <h2 class="heading-28-bold text-gray-800 leading-tight pb-4">
                        {{ $data->overview_heading }}
                    </h2>

                    <p class="paragraph-16 mt-2 text-[#6b7280]">
                        {{ $data->overview_desc }}
                    </p>
                </div>

                <div class="lg:col-span-5 flex justify-center">
                    <img src="{{ $data->overview_image->url }}" alt="Curriculum Illustration"
                        class="w-full max-w-lg h-auto object-contain" />
                </div>

            </div>
        </div>
    </section>

    <section class="py-12 lg:py-20 bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-8 lg:mb-12">
                <span class="inline-block text-[#266210] heading-16-bold tracking-wider uppercase">
                    {{ $data->news_title_label }}
                </span>
                <h2 class="heading-32-bold text-[#1f2937] mt-1">
                    {{ $data->news_title_label_copy }}
                </h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">

                @collection('news', ['site' => $site->handle, 'sort' => 'date:desc', 'limit' => 3])
                    @php
                        $newsImage = $data->news_image;
                        $newsImage = is_array($newsImage) ? ($newsImage[0] ?? null) : $newsImage;
                    @endphp
                    <a href="{{ url($entry->uri) }}" class="flex flex-col group">
                        <article class="flex flex-col h-full">
                            <div class="overflow-hidden rounded-2xl mb-4 aspect-4/3 bg-zinc-100">
                                @if ($newsImage)
                                    <img src="{{ $newsImage->url }}" alt="{{ $data->title }}"
                                        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
                                @endif
                            </div>
                            <div class="flex flex-col flex-grow justify-between space-y-3">
                                <h3 class="heading-20-bold text-[#1f2937] group-hover:text-cbGreen-2 transition-colors">
                                    {{ $data->title }}
                                </h3>
                                <div class="flex items-center gap-2 paragraph-14 text-zinc-400">
                                    <span>{{ $entry->date}}</span>
                                    <span>|</span>
                                    <span>{{ $data->news_author?->name }}</span>
                                </div>
                            </div>
                        </article>
                    </a>
                @endcollection



            </div>
        </div>
    </section>
</x-layouts::site.layout>
