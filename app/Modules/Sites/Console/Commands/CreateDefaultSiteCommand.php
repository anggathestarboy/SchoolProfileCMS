<?php

namespace App\Modules\Sites\Console\Commands;

use App\Modules\Sites\Models\Site;
use Illuminate\Console\Command;

/**
 * Creates the first Site — most of the app assumes at least one exists
 * (entries, sitemap, public pages). One building block of `draft:setup`
 * (see app/Console/Commands/DraftSetupCommand.php), but also runnable on
 * its own. A no-op once any site already exists.
 */
class CreateDefaultSiteCommand extends Command
{
    protected $signature = 'sites:create-default
        {--name= : Site name}
        {--handle= : Site handle}';

    protected $description = 'Create a default site, unless one already exists';

    public function handle(): int
    {
        if (Site::query()->exists()) {
            $this->info('A site already exists — skipping.');

            return self::SUCCESS;
        }

        $name = '';

        while ($name === '') {
            $name = trim((string) ($this->option('name') ?: $this->ask('Default site name')));
            $this->input->setOption('name', null);
        }

        $handle = '';

        while ($handle === '') {
            $handle = trim((string) ($this->option('handle') ?: $this->ask('Default site handle')));
            $this->input->setOption('handle', null);

            if ($handle === '') {
                $this->error('Enter a site handle.');
            }
        }

        $site = Site::create([
            'handle' => $handle,
            'name' => $name,
            'url' => '/',
            'locale' => 'en',
            'lang' => 'en',
            'attributes' => [],
            'order' => 1,
        ]);

        $this->info("Default site created: {$site->handle} ({$site->name})");

        return self::SUCCESS;
    }
}
