<?php

namespace App\Modules\Collections\Console\Commands;

use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Collections\Services\CollectionTreeService;
use App\Modules\Collections\Services\EntryUrlResolver;
use App\Modules\Sites\Models\Site;
use Illuminate\Console\Command;

/**
 * Creates a dummy homepage entry in the default "Pages" collection, using
 * the "Homepage" blueprint (see CreateDefaultPagesCollectionCommand and
 * CreateHomepageBlueprintCommand) — order 1 makes it the root/homepage
 * entry (see EntryUrlResolver::path()). One building block of `draft:setup`
 * (see app/Console/Commands/DraftSetupCommand.php), but also runnable on
 * its own. A no-op unless a site, the "pages" collection, and its
 * "homepage" blueprint all already exist, or a homepage entry already does.
 */
class CreateHomepageEntryCommand extends Command
{
    protected $signature = 'collections:create-homepage-entry';

    protected $description = 'Create a dummy homepage entry in the default Pages collection, unless one already exists';

    public function handle(): int
    {
        $site = Site::orderBy('order')->first();

        if (! $site) {
            $this->warn('No site exists yet — run `sites:create-default` first.');

            return self::SUCCESS;
        }

        $collection = Collection::where('handle', 'pages')->first();

        if (! $collection) {
            $this->warn('No "pages" collection exists yet — run `collections:create-pages` first.');

            return self::SUCCESS;
        }

        $blueprint = Blueprint::where('namespace', 'collections.pages')->where('handle', 'homepage')->first();

        if (! $blueprint) {
            $this->warn('No "homepage" blueprint exists yet — run `blueprints:create-homepage` first.');

            return self::SUCCESS;
        }

        if (Entry::where('collection', 'pages')->where('site', $site->handle)->where('order', 1)->exists()) {
            $this->info('A homepage entry already exists — skipping.');

            return self::SUCCESS;
        }

        $entry = Entry::create([
            'site' => $site->handle,
            'collection' => 'pages',
            'blueprint' => $blueprint->handle,
            'slug' => 'home',
            'status' => 'published',
            'published' => true,
            'date' => now(),
            'order' => 1,
            'data' => [
                'title' => 'Home',
                'heading' => 'Welcome to '.(config('app.name') ?: 'Draft CMS'),
                'subheading' => 'This is a dummy homepage — edit or replace it from the Pages collection.',
                'content' => '<p>This content is managed from the entry\'s "Homepage" blueprint. Head to the Pages collection in the control panel to edit it.</p>',
            ],
        ]);

        if ($collection->structured()) {
            app(CollectionTreeService::class)->setParent('pages', $site->handle, $entry->id, null, $collection->maxDepth());
        }

        $entry->update(['uri' => app(EntryUrlResolver::class)->path($collection, $entry)]);

        $this->info('Dummy homepage entry created.');

        return self::SUCCESS;
    }
}
