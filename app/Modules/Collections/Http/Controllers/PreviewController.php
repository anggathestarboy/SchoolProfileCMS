<?php

namespace App\Modules\Collections\Http\Controllers;

use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Authenticated live preview (see routes.php — this sits behind the CMS's
 * auth+verified middleware, unlike PageController's public catch-all):
 * renders an entry's blueprint template from its in-progress, not-yet-saved
 * form state, kept fresh by EntryForm::refreshPreviewCache() on every field
 * change. Unlike PageController, this never checks the entry's publish
 * status — previewing an unpublished entry is the whole point — and only
 * works for collections that opted in via Collection::livePreview().
 */
class PreviewController
{
    public function show(string $token): Response
    {
        $payload = Cache::get("entry-preview:{$token}");

        if (! $payload) {
            throw new NotFoundHttpException;
        }

        $collection = Collection::where('handle', $payload['collection'])->first();

        if (! $collection || ! $collection->livePreview()) {
            throw new NotFoundHttpException;
        }

        if (request()->boolean('version')) {
            return response()->json(['version' => (int) ($payload['version'] ?? 0)])
                ->header('Cache-Control', 'no-store');
        }

        $template = Blueprint::where('handle', $payload['blueprint'])->value('data');
        $template = $template['template'] ?? null;

        if (! $template || ! view()->exists($template)) {
            throw new NotFoundHttpException;
        }

        $entry = new Entry($payload['attributes']);
        $entry->id = $payload['attributes']['id'] ?? null;
        $entry->exists = false;

        return response()->view($template, [
            'entry' => $entry,
            'data' => $entry->fluentData(),
            'site' => Site::where('handle', $payload['site'])->first(),
            'cms_preview_version' => (int) ($payload['version'] ?? 0),
        ])->header('Cache-Control', 'no-store');
    }
}
