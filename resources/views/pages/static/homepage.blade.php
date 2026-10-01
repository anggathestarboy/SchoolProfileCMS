@php
    if (is_array($data->hero_image ?? null)) {
        $data->hero_image = \App\Modules\Media\Models\AssetMeta::find(\Illuminate\Support\Arr::first($data->hero_image));
    }
@endphp

<x-layouts::site.layout :title="$data->heading ?? $data->title" :site="$site ?? null">
    <section class="relative min-h-[500px] lg:min-h-[600px] w-full flex items-center bg-gray-900 text-white overflow-hidden">
  <div class="absolute inset-0 z-0">
    @if ($data->hero_image)
      <img
        src="{{ $data->hero_image->url }}"
        alt="{{ $data->hero_image->alt }}"
        class="w-full h-full object-cover object-center"
      />
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
</x-layouts::site.layout>
