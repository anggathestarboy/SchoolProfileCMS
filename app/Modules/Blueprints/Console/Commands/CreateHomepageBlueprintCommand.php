<?php

namespace App\Modules\Blueprints\Console\Commands;

use App\Modules\Blueprints\Models\Blueprint;
use Illuminate\Console\Command;

/**
 * Creates the "Homepage" blueprint for the default "Pages" collection (see
 * CreateDefaultPagesCollectionCommand) — a Heading/Subheading/Content field
 * set, rendered by resources/views/pages/static/homepage.blade.php. One
 * building block of `draft:setup` (see app/Console/Commands/DraftSetupCommand.php),
 * but also runnable on its own. A no-op once it already exists.
 */
class CreateHomepageBlueprintCommand extends Command
{
    const NAMESPACE = 'collections.pages';

    const HANDLE = 'homepage';

    protected $signature = 'blueprints:create-homepage';

    protected $description = 'Create the "Homepage" blueprint for the default Pages collection, unless one already exists';

    public function handle(): int
    {
        if (Blueprint::where('namespace', self::NAMESPACE)->where('handle', self::HANDLE)->exists()) {
            $this->info('A "homepage" blueprint already exists — skipping.');

            return self::SUCCESS;
        }

        Blueprint::create([
            'namespace' => self::NAMESPACE,
            'handle' => self::HANDLE,
            'data' => [
                'template' => 'pages.static.homepage',
                'fields_collapsed_by_default' => false,
                'tabs' => [
                    ['handle' => 'main', 'display' => 'Main', 'sections' => []],
                ],
                'fields' => [
                    [
                        'handle' => 'heading',
                        'display' => 'Heading',
                        'type' => 'text',
                        'required' => true,
                        'localizable' => true,
                        'placeholder' => null,
                        'default' => null,
                        'validation' => null,
                        'instructions' => null,
                        'validate_sometimes' => false,
                        'conditions' => null,
                        'tab' => 'main',
                        'section' => null,
                    ],
                    [
                        'handle' => 'subheading',
                        'display' => 'Subheading',
                        'type' => 'textarea',
                        'required' => false,
                        'localizable' => true,
                        'placeholder' => null,
                        'default' => null,
                        'validation' => null,
                        'instructions' => null,
                        'validate_sometimes' => false,
                        'conditions' => null,
                        'tab' => 'main',
                        'section' => null,
                    ],
                    [
                        'handle' => 'content',
                        'display' => 'Content',
                        'type' => 'bard',
                        'required' => false,
                        'localizable' => true,
                        'placeholder' => null,
                        'default' => null,
                        'validation' => null,
                        'instructions' => null,
                        'validate_sometimes' => false,
                        'conditions' => null,
                        'toolbar' => [],
                        'tab' => 'main',
                        'section' => null,
                    ],
                ],
            ],
        ]);

        $this->info('"Homepage" blueprint created.');

        return self::SUCCESS;
    }
}
