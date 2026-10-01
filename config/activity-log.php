<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    |
    | Not every client wants an audit trail of admin activity. When disabled,
    | nothing is written (LogsActivity-trait models, login/logout events, and
    | every explicit ActivityLogger::log() call all become no-ops) and the
    | admin panel page/sidebar link are hidden. Toggle via ACTIVITY_LOG_ENABLED
    | in .env — defaults to off.
    |
    */

    'enabled' => env('ACTIVITY_LOG_ENABLED', false),

];
