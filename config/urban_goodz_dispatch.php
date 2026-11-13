<?php

return [
    /*
    | How old a driver's last reported position can be and still count as "where they are".
    | Older than this, dispatch falls back to the driver's saved base address (and never pretends the
    | old point is current). Raise it if drivers' phones report location rarely.
    */
    'live_location_max_minutes' => (int) env('DISPATCH_LIVE_LOCATION_MINUTES', 15),
];
