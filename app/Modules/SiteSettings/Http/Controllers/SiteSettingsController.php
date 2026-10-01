<?php

namespace App\Modules\SiteSettings\Http\Controllers;

use App\Modules\SiteSettings\Models\SiteSetting;
use Illuminate\Http\Response;

/**
 * Serves the single global robots.txt/llms.txt (see SiteSetting) as plain
 * text — public, unauthenticated, one file for the whole app regardless of
 * how many sites are configured (see SiteSettingsManager).
 */
class SiteSettingsController
{
    public function robots(): Response
    {
        return response((string) SiteSetting::current()->robots_txt, 200, ['Content-Type' => 'text/plain']);
    }

    public function llms(): Response
    {
        return response((string) SiteSetting::current()->llms_txt, 200, ['Content-Type' => 'text/plain']);
    }
}
