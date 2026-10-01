<?php

namespace App\Modules\Forms\Models;

use App\Modules\Blueprints\Models\Blueprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A front-end HTML form (e.g. contact, newsletter signup) whose fields are
 * defined by a Blueprint — the same field-builder Collections/Globals/
 * Taxonomies use — while the actual markup is hand-built by the site's own
 * templates (see FormSubmissionController). Mirrors GlobalSet's
 * explicit-pick-or-auto-created blueprint convention.
 *
 * @property int $id
 * @property string $handle
 * @property string $title
 * @property array|null $settings
 */
class Form extends Model
{
    protected $table = 'forms';

    protected $fillable = ['handle', 'title', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'form', 'handle');
    }

    /**
     * The blueprint this form's fields come from. Prefers the explicit pick
     * from the Form form (settings.blueprint), and falls back to a
     * blueprint namespaced to this form (e.g. "forms.contact"), mirroring
     * GlobalSet::effectiveBlueprint().
     */
    public function effectiveBlueprint(): ?Blueprint
    {
        $explicitHandle = $this->settings['blueprint'] ?? null;

        if ($explicitHandle) {
            return Blueprint::where('handle', $explicitHandle)->first();
        }

        return Blueprint::where('namespace', "forms.{$this->handle}")->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        return $this->effectiveBlueprint()?->data['fields'] ?? [];
    }

    /**
     * Whether a successful submission is persisted to form_submissions.
     * Defaults on — a form with nowhere to send its data isn't useful.
     */
    public function storesSubmissions(): bool
    {
        return $this->settings['store_submissions'] ?? true;
    }

    /**
     * Where a successful submission redirects to. Null means "redirect
     * back to the page the form was submitted from" (the default).
     */
    public function redirectUrl(): ?string
    {
        return $this->settings['redirect_url'] ?? null;
    }

    /**
     * Which field handles show as columns in the submissions table (see
     * FormSubmissionManager). Empty means "every field" — the default
     * before this was ever configured.
     *
     * @return array<int, string>
     */
    public function previewFieldHandles(): array
    {
        return $this->settings['preview_fields'] ?? [];
    }
}
