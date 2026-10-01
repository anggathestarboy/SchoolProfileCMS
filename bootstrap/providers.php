<?php

use App\Modules\ActivityLog\ActivityLogServiceProvider;
use App\Modules\Auth\AuthServiceProvider;
use App\Modules\Blueprints\BlueprintsServiceProvider;
use App\Modules\Collections\CollectionsServiceProvider;
use App\Modules\Forms\FormsServiceProvider;
use App\Modules\Globals\GlobalsServiceProvider;
use App\Modules\Media\MediaServiceProvider;
use App\Modules\Navigation\NavigationServiceProvider;
use App\Modules\NotFoundDetector\NotFoundDetectorServiceProvider;
use App\Modules\Redirects\RedirectsServiceProvider;
use App\Modules\Sitemap\SitemapServiceProvider;
use App\Modules\Sites\SitesServiceProvider;
use App\Modules\SiteSettings\SiteSettingsServiceProvider;
use App\Modules\Structure\StructureServiceProvider;
use App\Modules\Taxonomies\TaxonomiesServiceProvider;
use App\Modules\Users\UsersServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\TemplatingServiceProvider;

return [
    AppServiceProvider::class,
    TemplatingServiceProvider::class,

    // CMS modules (app/Modules/*) — see docs/AI-CONTEXT.md "Sistem modul CMS".
    ActivityLogServiceProvider::class,
    AuthServiceProvider::class,
    CollectionsServiceProvider::class,
    TaxonomiesServiceProvider::class,
    BlueprintsServiceProvider::class,
    MediaServiceProvider::class,
    GlobalsServiceProvider::class,
    FormsServiceProvider::class,
    NavigationServiceProvider::class,
    StructureServiceProvider::class,
    SitesServiceProvider::class,
    SitemapServiceProvider::class,
    RedirectsServiceProvider::class,
    UsersServiceProvider::class,
    SiteSettingsServiceProvider::class,
    NotFoundDetectorServiceProvider::class,
];
