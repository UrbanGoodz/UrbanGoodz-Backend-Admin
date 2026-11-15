<?php

return [
    // A request still "live" after this many hours is dead, not urgent. The dispatch tick used to be unscheduled
    // (the schedule file was never read), so old requests may be stuck in broadcasting/awaiting_selection. Left
    // alone, the first run would widen and escalate every one of them and message responders about old emergencies.
    // They are expired quietly instead.
    'max_live_hours' => (int) env('STRANDED_MAX_LIVE_HOURS', 6),

    // Only an offer that lapsed this recently counts as a missed job against a responder. Older lapses (including
    // everything that piled up before the tick ever ran) are expired without penalty.
    'missed_offer_window_minutes' => (int) env('STRANDED_MISSED_OFFER_WINDOW_MINUTES', 15),

    // "Any nearby user" tier. Goodz Samaritans always get a request first; users who switched on Nearby help
    // alerts are offered it on the next pass (the offer window lapsed or the radius widened), or straight away
    // when no Samaritan is in range.
    'community' => [
        'enabled' => (bool) env('STRANDED_COMMUNITY_ENABLED', true),
        // A reported position older than this is ignored (and deleted by the hourly prune).
        'presence_ttl_minutes' => (int) env('STRANDED_PRESENCE_TTL_MINUTES', 120),
        'max_per_round' => (int) env('STRANDED_COMMUNITY_MAX_PER_ROUND', 15),
    ],
];
