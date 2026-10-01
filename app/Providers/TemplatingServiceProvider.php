<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Registers @collection/@taxonomy/@sites/@global Blade directives so public
 * templates can fetch CMS content without hand-rolling Eloquent queries —
 * see App\Support\Templating\TemplateTags for what each one fetches.
 *
 * @collection('blog', ['site' => $site->handle]) ... {{ $data->title }} ... @endcollection
 * @collection() and @taxonomy() take an optional second argument with any
 * of: 'site', 'status' (@collection() only, defaults to "published"),
 * 'where' (equality filters, e.g. ['featured' => true]), 'sort'
 * ("field:asc"/"field:desc", or an array of those for multiple columns),
 * 'random' (true shuffles instead), 'limit', and 'related' (@collection()
 * only — an Entry or entry id; returns entries sharing a taxonomy term with
 * it). See App\Support\Templating\TemplateTags for exact behavior.
 *
 * @taxonomy('categories') ... {{ $data->title }} ... @endtaxonomy
 *
 * @sites() ... {{ $site->name }} ... @endsites
 *
 * @global('site-settings') ... {{ $global->site_name }} ... @endglobal
 */
class TemplatingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Blade::directive('collection', fn (string $expression): string => "<?php foreach (\\App\\Support\\Templating\\TemplateTags::collection({$expression}) as \$entry): \$data = \$entry->fluentData(); ?>");
        Blade::directive('endcollection', fn (): string => '<?php endforeach; ?>');

        Blade::directive('taxonomy', fn (string $expression): string => "<?php foreach (\\App\\Support\\Templating\\TemplateTags::taxonomy({$expression}) as \$term): \$data = \$term->fluentData(); ?>");
        Blade::directive('endtaxonomy', fn (): string => '<?php endforeach; ?>');

        Blade::directive('sites', fn (string $expression): string => "<?php foreach (\\App\\Support\\Templating\\TemplateTags::sites({$expression}) as \$site): ?>");
        Blade::directive('endsites', fn (): string => '<?php endforeach; ?>');

        Blade::directive('global', fn (string $expression): string => "<?php if (\$global = \\App\\Support\\Templating\\TemplateTags::global({$expression})): ?>");
        Blade::directive('endglobal', fn (): string => '<?php endif; ?>');
    }
}
