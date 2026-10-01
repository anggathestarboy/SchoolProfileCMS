@php
    /** @var array<string, mixed> $field */
    /** @var mixed $value */

    if (($field['type'] ?? null) === 'assets') {
        $ids = array_values(array_filter(is_array($value) ? $value : [$value]));
        $assets = \App\Modules\Media\Models\AssetMeta::whereIn('id', $ids)->get()->keyBy('id');

        $links = collect($ids)
            ->map(fn (int $id) => $assets->get($id))
            ->filter()
            ->map(fn (\App\Modules\Media\Models\AssetMeta $asset): string => '<a href="'.e($asset->url).'" target="_blank" rel="noopener" class="underline decoration-zinc-300 underline-offset-4 hover:decoration-current">'.e($asset->title).'</a>')
            ->implode(', ');

        echo $links !== '' ? $links : '—';

        return;
    }

    $value = is_array($value) ? implode(', ', $value) : $value;

    echo e($value ?? '—');
@endphp