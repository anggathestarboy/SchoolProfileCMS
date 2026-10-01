<?php

namespace App\Modules\Globals\Models;

use App\Modules\ActivityLog\Concerns\LogsActivity;
use App\Modules\Blueprints\Models\Blueprint;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $handle
 * @property string $title
 * @property array|null $settings
 */
class GlobalSet extends Model
{
    use LogsActivity;

    protected $table = 'global_sets';

    protected $fillable = ['handle', 'title', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * The blueprint this global set's values use. Prefers the explicit pick
     * from the global set form (settings.blueprint), and falls back to a
     * blueprint namespaced to this global set (e.g. "globals.site-settings"),
     * mirroring Collection::effectiveBlueprint().
     */
    public function effectiveBlueprint(): ?Blueprint
    {
        $explicitHandle = $this->settings['blueprint'] ?? null;

        if ($explicitHandle) {
            return Blueprint::where('handle', $explicitHandle)->first();
        }

        return Blueprint::where('namespace', "globals.{$this->handle}")->first();
    }

    /**
     * Where the values form sends you after saving this global set's
     * values: 'listing' (back to the global sets list — the default) or
     * 'editing' (stay on the values form). Mirrors Collection::saveAction().
     */
    public function saveAction(): string
    {
        return $this->settings['save_action'] ?? 'listing';
    }

    public function activityLabel(): string
    {
        return "global set '{$this->title}'";
    }
}
