<?php

namespace App\Modules\Media;

use App\Modules\Media\Console\Commands\MakeMediaCommand;
use App\Modules\Media\Console\Commands\RemoveMediaCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Asset containers and file/media metadata.
 * Owns tables: asset_containers, assets_meta.
 */
class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'media');

        Livewire::addNamespace('media', classNamespace: 'App\\Modules\\Media\\Livewire');

        // Laravel's default local-disk serve() dumps the whole file with fpassthru and
        // ignores the Range header, which browsers require to play/seek <video>. Serving
        // via response()->file() uses Symfony's BinaryFileResponse, which handles Range
        // (206 Partial Content) automatically.
        Storage::disk('local')->serveUsing(
            fn ($request, $path, $headers) => response()->file(Storage::disk('local')->path($path), $headers)
        );

        // The "public" disk is normally served straight off the storage symlink, which
        // bypasses Laravel (and its Range support) entirely — most MP4s aren't "faststart"
        // (their moov atom sits at the end of the file), so a browser needs a Range request
        // to seek to it before it can even start playing, and without one it just fails to
        // play. Route video files through the app instead, at a path the symlink can't
        // shadow, so response()->file()'s Range handling actually gets hit.
        Route::get('media/stream/{path}', function (string $path) {
            $fullPath = Storage::disk('public')->path($path);

            abort_unless(is_file($fullPath), 404);

            return response()->file($fullPath);
        })->where('path', '.*')->name('media.stream.public');

        $this->commands([
            MakeMediaCommand::class,
            RemoveMediaCommand::class,
        ]);

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('media.')
            ->group(__DIR__.'/routes.php');
    }
}
