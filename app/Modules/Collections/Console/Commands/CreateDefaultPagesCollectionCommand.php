<?php

namespace App\Modules\Collections\Console\Commands;

use App\Modules\Collections\Models\Collection;
use Illuminate\Console\Command;

/**
 * Creates the default "Pages" collection — structured (nestable, with one
 * expected root entry as the homepage), orderable, with Live Preview,
 * Schema, and SEO all enabled. The admin sidebar's "Pages" link (see
 * SeedAdminSidebarCommand) already assumes a collection with this exact
 * handle exists. One building block of `draft:setup` (see
 * app/Console/Commands/DraftSetupCommand.php), but also runnable on its
 * own. A no-op once a "pages" collection already exists.
 */
class CreateDefaultPagesCollectionCommand extends Command
{
    const HANDLE = 'pages';

    protected $signature = 'collections:create-pages';

    protected $description = 'Create the default "Pages" collection, unless one already exists';

    public function handle(): int
    {
        if (Collection::where('handle', self::HANDLE)->exists()) {
            $this->info('A "pages" collection already exists — skipping.');

            return self::SUCCESS;
        }

        Collection::create([
            'handle' => self::HANDLE,
            'title' => 'Pages',
            'settings' => [
                'route_mode' => 'single',
                'route' => '{parent_uri}/{slug}',
                'routes' => [],
                'sites' => [],
                'structured' => true,
                'orderable' => true,
                'live_preview' => true,
                'schema_enabled' => true,
                'seo_enabled' => true,
                'max_depth' => null,
                'expects_root' => true,
                'save_action' => 'listing',
            ],
        ]);

        $this->info('Default "Pages" collection created.');

        return self::SUCCESS;
    }
}
