<?php

namespace App\Models;

use App\Models\Concerns\HasFlexibleData;
use App\Modules\ActivityLog\Concerns\LogsActivity;
use App\Support\Templating\FluentWrapper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Fluent;

class TaxonomyTerm extends Model
{
    use HasFlexibleData, LogsActivity;

    protected $fillable = ['site', 'origin_id', 'slug', 'uri', 'taxonomy', 'blueprint', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'uri';
    }

    /**
     * This term's field data for public templates, Fluent-wrapped like
     * Entry::fluentData() so a template can read it as $data->title — see
     * the @taxonomy Blade directive (App\Providers\TemplatingServiceProvider).
     */
    public function fluentData(): Fluent
    {
        return FluentWrapper::wrap(array_merge($this->data ?? [], [
            'id' => $this->id,
            'slug' => $this->slug,
        ]));
    }

    public function taxonomyModel(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'taxonomy', 'handle');
    }

    /** The "parent" term this one was localized from (Statamic localization). */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'origin_id', 'id');
    }

    /** Localized copies of this term across other sites. */
    public function localizations(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class, 'origin_id', 'id');
    }

    public function isLocalized(): bool
    {
        return ! is_null($this->origin_id);
    }

    public function getOriginSite(): ?self
    {
        if (! $this->origin_id) {
            return null;
        }

        return self::where('id', $this->origin_id)->first();
    }

    public function getLocalizedSites(): Collection
    {
        if ($this->isLocalized()) {
            return self::where('origin_id', $this->origin_id)
                ->where('site', '!=', $this->site)
                ->get();
        }

        return $this->localizations;
    }

    public function getLocalizedCount(): int
    {
        return $this->getLocalizedSites()->count();
    }

    public function activityLabel(): string
    {
        $title = $this->data['title'] ?? $this->slug ?? $this->id;

        return "term '{$title}' ({$this->taxonomy})";
    }
}
