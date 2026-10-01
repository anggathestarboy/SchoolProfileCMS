<?php

namespace App\Modules\Collections\Console\Commands;

use App\Modules\Collections\Models\Entry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Flips every "scheduled" entry whose date has now passed over to
 * "published" — mirrors Statamic's scheduled-content cron
 * (statamic.dev/advanced-topics/scheduling). An entry lands in "scheduled"
 * in the first place via EntryForm::save()/EntryManager::bulkUpdateStatus()
 * picking "Published" (or "Scheduled") with a future Date; nothing makes it
 * live again until this command runs, so it must be registered on the
 * scheduler (see routes/console.php) with a cron entry actually calling
 * `php artisan schedule:run` every minute on the server.
 */
class PublishScheduledEntriesCommand extends Command
{
    protected $signature = 'entries:publish-scheduled';

    protected $description = 'Publish every "scheduled" entry whose date has passed';

    public function handle(): int
    {
        $entries = Entry::where('status', 'scheduled')->get()
            ->filter(fn (Entry $entry): bool => $entry->date && ! Carbon::parse($entry->date)->isFuture());

        foreach ($entries as $entry) {
            $entry->update(['status' => 'published', 'published' => true]);
        }

        $this->info($entries->isEmpty() ? 'No scheduled entries were due.' : "Published {$entries->count()} scheduled entries.");

        return self::SUCCESS;
    }
}
