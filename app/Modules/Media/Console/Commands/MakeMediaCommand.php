<?php

namespace App\Modules\Media\Console\Commands;

use App\Modules\Media\Models\AssetContainer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MakeMediaCommand extends Command
{
    protected $signature = 'make:media {title? : Container title} {--type= : public or private}';

    protected $description = 'Create a new Media Library asset container';

    public function handle(): int
    {
        $title = $this->argument('title') ?: $this->ask('Container title');
        $handle = Str::slug($title, '_');

        while (AssetContainer::where('handle', $handle)->exists()) {
            $this->error("A container with handle \"{$handle}\" already exists. Choose a different title.");
            $title = $this->ask('Container title');
            $handle = Str::slug($title, '_');
        }

        $type = $this->option('type');

        if (! in_array($type, ['public', 'private'], true)) {
            $type = $this->choice('Container type', ['public', 'private'], 0);
        }

        $disk = $type === 'public' ? 'public' : 'local';

        AssetContainer::create([
            'handle' => $handle,
            'title' => $title,
            'disk' => $disk,
            'settings' => null,
        ]);

        Storage::disk($disk)->makeDirectory($handle);

        $this->info("Container \"{$title}\" ({$handle}) created on the \"{$disk}\" disk.");

        return self::SUCCESS;
    }
}
