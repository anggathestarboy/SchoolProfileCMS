<?php

namespace App\Modules\Media\Console\Commands;

use App\Modules\Media\Models\AssetContainer;
use App\Modules\Media\Models\AssetMeta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RemoveMediaCommand extends Command
{
    protected $signature = 'remove:media {handle : Container handle to remove}';

    protected $description = 'Delete a Media Library asset container and all of its files';

    public function handle(): int
    {
        $handle = $this->argument('handle');
        $container = AssetContainer::where('handle', $handle)->first();

        if (! $container) {
            $this->error("No container found with handle \"{$handle}\".");

            return self::FAILURE;
        }

        $fileCount = AssetMeta::where('container', $handle)->count();

        $this->warn("This will permanently delete the \"{$container->title}\" ({$handle}) container and its {$fileCount} file(s). This cannot be undone.");

        if (! $this->confirm('Are you sure you want to continue?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        Storage::disk($container->disk)->deleteDirectory($handle);
        AssetMeta::where('container', $handle)->delete();
        $container->delete();

        $this->info("Container \"{$handle}\" removed.");

        return self::SUCCESS;
    }
}
