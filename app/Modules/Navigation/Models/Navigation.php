<?php

namespace App\Modules\Navigation\Models;

use App\Modules\ActivityLog\Concerns\LogsActivity;
use App\Modules\Blueprints\Models\Blueprint;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $handle
 * @property string $title
 * @property array|null $settings
 */
class Navigation extends Model
{
    use LogsActivity;

    protected $table = 'navigations';

    protected $fillable = ['handle', 'title', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    /**
     * The blueprint driving this navigation's per-item fields. Prefers the
     * explicit pick from the Navigation form (settings.blueprint), and falls
     * back to a blueprint namespaced to this navigation (e.g.
     * "navigations.main"), mirroring Taxonomy::effectiveBlueprint().
     */
    public function effectiveBlueprint(): ?Blueprint
    {
        $explicitHandle = $this->settings['blueprint'] ?? null;

        if ($explicitHandle) {
            return Blueprint::where('handle', $explicitHandle)->first();
        }

        return Blueprint::where('namespace', "navigations.{$this->handle}")->first();
    }

    public function activityLabel(): string
    {
        return "navigation '{$this->title}'";
    }
}
