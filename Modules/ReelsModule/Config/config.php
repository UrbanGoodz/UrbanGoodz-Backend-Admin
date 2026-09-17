<?php

return [
    'name' => 'ReelsModule',
    'project' => '6ammart',
    'version' => '1.0.0',
    'pagination' => 25,

    /*
    |--------------------------------------------------------------------------
    | Multi-module flag
    |--------------------------------------------------------------------------
    | When false, the reels feature treats the project as single-module:
    | module_type / module_id checks are bypassed across admin, vendor,
    | and customer APIs, and stored reels fall back to the defaults below.
    */
    'is_multi_module' => env('REELS_IS_MULTI_MODULE', true),

    /*
    | Module types that are permitted to use the reels feature when the
    | project is running in multi-module mode. Ignored when
    | is_multi_module is false.
    */
    'allowed_module_types' => ['grocery', 'food', 'ecommerce', 'pharmacy', 'rental'],

    /*
    | Default module attributes used when is_multi_module is false, so
    | persisted reels still have consistent values on the database.
    */
    'default_module_id' => (int) env('REELS_DEFAULT_MODULE_ID', 0),
    'default_module_type' => env('REELS_DEFAULT_MODULE_TYPE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Creator commission rate (percent of order value, 0-100)
    |--------------------------------------------------------------------------
    | The share of an attributed order's value paid to the creator whose reel
    | drove it. Resolution order, most specific first:
    |
    |   1. business_settings row `creator_commission_rate`  (admin-changeable,
    |      takes effect immediately with no deploy)
    |   2. UG_CREATOR_COMMISSION_RATE in .env
    |   3. the default below
    |
    | Anything outside 0-100 is clamped at the point of use.
    |
    | NOTE: this module merges its config under the key `reelsmodule` (the
    | provider uses $this->moduleNameLower), NOT `reels`. Read it as
    | config('reelsmodule.creator_commission_rate'). CreatorCommerceController
    | previously read config('reels.creator_commission_rate', 5), a namespace
    | that does not exist, so every creator earning was silently pinned to the
    | hardcoded 5 and no setting could change it.
    */
    'creator_commission_rate' => (float) env('UG_CREATOR_COMMISSION_RATE', 5),
];
