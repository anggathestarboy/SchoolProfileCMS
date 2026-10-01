<?php

namespace App\Modules\NotFoundDetector\Services;

use App\Modules\Collections\Http\Controllers\PageController;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Collections\Services\EntryUrlResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Finds published entries whose own public URL doesn't actually resolve —
 * checked by handing each entry's resolved URL straight to PageController,
 * the exact same code the public site uses, rather than re-guessing its
 * uri-lookup/self-heal/template logic here and risking the two drifting
 * apart. Draft/need_review entries are skipped entirely — those are
 * *supposed* to 404 publicly, so that's not a broken link to report.
 *
 * ponytail: one real Entry -> View resolution attempt per entry, and
 * PageController's own uri-miss fallback is O(n) over its collection — fine
 * for an on-demand admin scan at this app's scale; move to a queued/batched
 * job if this ever needs to run automatically over a very large site.
 */
class NotFoundScanner
{
    /**
     * @param  array<int, string>  $collectionHandles  empty means every collection
     * @return SupportCollection<int, array{entry: Entry, collection: ?Collection, url: ?string, reason: string}>
     */
    public function scan(array $collectionHandles = []): SupportCollection
    {
        $entries = Entry::where('status', 'published')
            ->when($collectionHandles !== [], fn ($query) => $query->whereIn('collection', $collectionHandles))
            ->get();

        $collections = Collection::whereIn('handle', $entries->pluck('collection')->unique())->get()->keyBy('handle');
        $resolver = app(EntryUrlResolver::class);

        return $entries
            ->map(function (Entry $entry) use ($collections, $resolver): ?array {
                $collection = $collections->get($entry->collection);
                $reason = $this->check($collection, $entry, $resolver);

                return $reason ? ['entry' => $entry, 'collection' => $collection, 'url' => $resolver->url($collection, $entry), 'reason' => $reason] : null;
            })
            ->filter()
            ->values();
    }

    protected function check(?Collection $collection, Entry $entry, EntryUrlResolver $resolver): ?string
    {
        if (! $collection) {
            return __("Its collection ':handle' no longer exists.", ['handle' => $entry->collection]);
        }

        $url = $resolver->url($collection, $entry);

        if ($url === null) {
            return __('This collection has no route configured for this entry\'s site.');
        }

        try {
            app(PageController::class)->show(Request::create($url), trim((string) parse_url($url, PHP_URL_PATH), '/'));

            return null;
        } catch (NotFoundHttpException) {
            return __('Its resolved URL returns a 404.');
        } catch (\Throwable $e) {
            return __('Resolving it threw an error: :message', ['message' => $e->getMessage()]);
        }
    }
}
