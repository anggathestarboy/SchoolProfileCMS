<x-layouts::site.layout :title="$data->heading ?? $data->title" :site="$site ?? null">
<div class="px-5">
{!! $data->news_desc !!}

</div>

</x-layouts::site.layout>
