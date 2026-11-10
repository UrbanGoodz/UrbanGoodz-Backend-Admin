<?php

return [
    // Proof of delivery for loads and Order Anywhere. The server always ACCEPTS and RECORDS a receiver name
    // when the driver app sends one. Turning this on also REQUIRES it, so a job cannot be completed without
    // naming who received it. Off by default because the driver app's "complete job" call does not send a
    // receiver yet; ship the app prompt first, then switch this on. (Medical courier jobs always require it.)
    'require_recipient_on_delivery' => (bool) env('UG_REQUIRE_DELIVERY_PROOF', false),
];
