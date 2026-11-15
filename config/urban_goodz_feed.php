<?php

/*
| Creator / reel feed ranking ("For You").
|
| The customer reels list used to be strictly newest-first, so a creator who posted often buried everyone else and a
| great reel disappeared in a day. The feed now scores a pool of recent reels and orders them by that score. Every
| number here is a dial, not a rule: change them without a deploy via .env, or send sort=latest to get the old order.
*/
return [
    // for_you | latest
    'default_sort' => env('FEED_DEFAULT_SORT', 'for_you'),

    // How many of the most recent active reels are scored per request.
    'pool_size' => (int) env('FEED_POOL_SIZE', 300),

    // Recency: a reel's freshness halves every this many hours.
    'half_life_hours' => (float) env('FEED_HALF_LIFE_HOURS', 48),

    'weights' => [
        'engagement' => 1.0,   // how much people who SEE it react (smoothed so tiny samples cannot win)
        'recency' => 0.8,
        'follow' => 0.7,       // the viewer follows this creator
        'affinity' => 0.5,     // the viewer liked this creator or similar tagged items before
        'exploration' => 0.35, // new reels with little data get a fair look
        'seen' => -1.2,        // already viewed: push down, not out
    ],

    // Engagement actions, as multiples of one like.
    'action_value' => ['likes' => 1.0, 'comments' => 2.0, 'shares' => 3.0, 'visits' => 3.5, 'views' => 0.05],

    // Bayesian smoothing: pretend every reel starts with this many views at the platform-typical reaction rate, so
    // 2 likes on 2 views does not beat 400 likes on 5,000.
    'prior_views' => (float) env('FEED_PRIOR_VIEWS', 50),
    'prior_rate' => (float) env('FEED_PRIOR_RATE', 0.08),

    // A reel with fewer views than this counts as "new" and gets the exploration boost.
    'new_reel_views' => (int) env('FEED_NEW_REEL_VIEWS', 30),

    // No more than this many reels from one creator/store on a single page, and never back-to-back.
    'max_per_author_per_page' => (int) env('FEED_MAX_PER_AUTHOR', 2),
];
