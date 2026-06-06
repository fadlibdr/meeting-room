<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Synchronous export row limit
    |--------------------------------------------------------------------------
    |
    | Exports with at most this many rows stream directly in the request.
    | Anything larger is handed to GenerateBookingExportJob and the user is
    | notified when the file is ready.
    |
    */

    'sync_row_limit' => (int) env('EXPORT_SYNC_ROW_LIMIT', 1000),

];
