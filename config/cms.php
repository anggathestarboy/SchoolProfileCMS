<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CMS Panel
    |--------------------------------------------------------------------------
    |
    | Controls whether the CMS admin panel is registered at all, and which
    | URL prefix it lives under. Mirrors Statamic's `cp.enabled` / `cp.route`.
    | The same prefix is applied to Fortify's auth routes (see
    | config/fortify.php) so login/register/dashboard all live together,
    | e.g. with CMS_ROUTE=admin: /admin/login, /admin/dashboard.
    |
    */

    "enabled" => env("CMS_ENABLED", true),

    "route" => env("CMS_ROUTE", "admin-panel"),

    /*
    |--------------------------------------------------------------------------
    | Protected Navigations
    |--------------------------------------------------------------------------
    |
    | Navigation handles that must never be deleted, because other parts of
    | the app depend on them existing (e.g. "sidebar-admin" drives the admin
    | panel sidebar — see resources/views/layouts/app/sidebar.blade.php and
    | the `admin:sidebar` console command).
    |
    */

    "protected_navigations" => ["sidebar-admin"],
];
