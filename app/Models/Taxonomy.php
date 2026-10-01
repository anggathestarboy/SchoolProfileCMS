<?php

namespace App\Models;

use App\Modules\ActivityLog\Concerns\LogsActivity;
use App\Modules\Blueprints\Models\Blueprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Taxonomy extends Model
{
    use LogsActivity;

    protected $fillable = ['handle', 'title', 'sites', 'settings'];

    protected $casts = [
        'sites' => 'array',
        'settings' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    public function terms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class, 'taxonomy', 'handle');
    }

    /**
     * The blueprint this taxonomy's terms use. Prefers the explicit pick
     * from the Taxonomy form (settings.blueprint), and falls back to a
     * blueprint namespaced to this taxonomy (e.g. "taxonomies.news_categories"),
     * mirroring Collection::effectiveBlueprint().
     */
    public function effectiveBlueprint(): ?Blueprint
    {
        $explicitHandle = $this->settings['blueprint'] ?? null;

        if ($explicitHandle) {
            return Blueprint::where('handle', $explicitHandle)->first();
        }

        return Blueprint::where('namespace', "taxonomies.{$this->handle}")->first();
    }

    /**
     * Where the term form sends you after saving one of this taxonomy's
     * terms: 'listing' (back to the term list — the default), 'editing'
     * (stay on the term), or 'create_another' (a fresh create form for the
     * same taxonomy/site). Mirrors Collection::saveAction().
     */
    public function saveAction(): string
    {
        return $this->settings['save_action'] ?? 'listing';
    }

    public function activityLabel(): string
    {
        return "taxonomy '{$this->title}'";
    }
}
