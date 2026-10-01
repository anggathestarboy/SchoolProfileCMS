<?php

namespace App\Modules\Collections\Models;

use App\Modules\ActivityLog\Concerns\LogsActivity;
use App\Modules\Blueprints\Models\Blueprint;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $handle
 * @property string $title
 * @property array|null $settings
 */
class Collection extends Model
{
    use LogsActivity;

    protected $table = 'collections';

    protected $fillable = ['handle', 'title', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * The blueprint this collection's entries use — a blueprint namespaced
     * to this collection (e.g. a blueprint with namespace "collections.pages"
     * for the "pages" collection), assigned from the Blueprint form's
     * "Namespace" dropdown rather than from the Collection form.
     */
    public function effectiveBlueprint(): ?Blueprint
    {
        return Blueprint::where('namespace', "collections.{$this->handle}")->first();
    }

    /**
     * Which sites this collection's entries are available on. Empty means
     * every site (unrestricted) — the behavior before this setting existed.
     *
     * @return array<int, string>
     */
    public function configuredSites(): array
    {
        return $this->settings['sites'] ?? [];
    }

    /**
     * 'single' uses the same URI template on every site; 'per_site' looks
     * the template up per site instead.
     */
    public function routeMode(): string
    {
        return $this->settings['route_mode'] ?? 'single';
    }

    /**
     * The Statamic-style URI template per site, e.g.
     * ['indonesia' => 'blog/{slug}', 'english' => 'en/blog/{slug}'].
     * Only populated when routeMode() is 'per_site'.
     *
     * @return array<string, string>
     */
    public function routes(): array
    {
        return $this->settings['routes'] ?? [];
    }

    /**
     * The URI template for this collection's entries on the given site —
     * the single shared template, or that site's own when routeMode() is
     * 'per_site'. Null means entries have no public route on that site.
     */
    public function routeForSite(string $site): ?string
    {
        if ($this->routeMode() === 'per_site') {
            return $this->routes()[$site] ?? null;
        }

        return $this->settings['route'] ?? null;
    }

    /**
     * Whether entries in this collection can be nested under a parent entry
     * (Statamic's "Structured" collections) — enables the {parent_uri}
     * route variable and a tree/hierarchy for entries, backed by the
     * Structure module's trees table (type=collection, handle=this handle).
     */
    public function structured(): bool
    {
        return (bool) ($this->settings['structured'] ?? false);
    }

    /**
     * Whether entries can be manually reordered (drag-and-drop), independent
     * of whether the collection is structured.
     */
    public function orderable(): bool
    {
        return (bool) ($this->settings['orderable'] ?? false);
    }

    /**
     * Whether the "Order" field shows in the entry form sidebar. Purely a
     * display toggle — reordering (see orderable()) and the "first root
     * entry becomes the homepage via order = 1" logic (see
     * EntryForm::save()) both keep working the same either way, whether or
     * not an editor can see the field.
     */
    public function visibleOrder(): bool
    {
        return (bool) ($this->settings['visible_order'] ?? true);
    }

    /**
     * Whether entries can be viewed via the authenticated live preview pane
     * on the entry form regardless of their publish status, and whether
     * their fields sync to the server as they're typed so the preview stays
     * up to date (see resources/views/livewire/partials/entry-field.blade.php).
     */
    public function livePreview(): bool
    {
        return (bool) ($this->settings['live_preview'] ?? false);
    }

    /**
     * Maximum nesting depth for a structured collection's tree. Null means
     * unlimited.
     */
    public function maxDepth(): ?int
    {
        return $this->settings['max_depth'] ?? null;
    }

    /**
     * Whether a structured collection requires a single root entry (one
     * entry with no parent) rather than allowing multiple top-level entries.
     */
    public function expectsRoot(): bool
    {
        return (bool) ($this->settings['expects_root'] ?? false);
    }

    /**
     * Whether entries in this collection get a "Schema" tab on their edit
     * form, letting an editor attach schema.org JSON-LD blocks (Article,
     * Organization, etc.) to that specific entry. Unlike a blueprint tab,
     * this one isn't field-builder-driven — its inputs are fixed in code
     * (see entry-field's schema partial) — so it's just a toggle here
     * rather than something configured through Blueprints.
     */
    public function schemaEnabled(): bool
    {
        return (bool) ($this->settings['schema_enabled'] ?? false);
    }

    /**
     * Whether entries in this collection get an "SEO" tab on their edit
     * form (meta title/description, robots, sitemap hints, etc.) — same
     * code-managed-tab convention as schemaEnabled().
     */
    public function seoEnabled(): bool
    {
        return (bool) ($this->settings['seo_enabled'] ?? false);
    }

    /**
     * Where the entry form sends you after saving one of this collection's
     * entries: 'listing' (back to the entry list — the default), 'editing'
     * (stay on the entry, useful for iterating), or 'create_another' (a
     * fresh create form for the same collection/site).
     */
    public function saveAction(): string
    {
        return $this->settings['save_action'] ?? 'listing';
    }

    public function activityLabel(): string
    {
        return "collection '{$this->title}'";
    }
}
