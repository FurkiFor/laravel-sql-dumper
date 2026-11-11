<?php

return [

    /*
    | Write every executed query (bindings filled in) to the Laravel log at debug level.
    | Meant for local development; leave it off in production.
    */
    'log' => (bool) env('SQL_DUMPER_LOG', false),

    /*
    | Only log queries that took at least this many milliseconds. null logs all of them.
    */
    'slow_ms' => env('SQL_DUMPER_SLOW_MS'),

    /*
    | Log channel to write to. null uses the default channel.
    */
    'channel' => env('SQL_DUMPER_CHANNEL'),

];
