<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Browser portals use their existing session guard. Mobile clients use
        // separate auth endpoints so each token type is authenticated by the
        // same middleware as the rest of that client API.
        Broadcast::routes([
            'as' => 'broadcasting.web.',
            'middleware' => [
                'web',
                'auth:admin,vendor,vendor_employee,business,web,customer,delivery_men',
            ],
        ]);

        Broadcast::routes([
            'as' => 'broadcasting.shopper.',
            'prefix' => 'api/v1/realtime/shopper',
            'middleware' => ['api', 'auth:api'],
        ]);

        Broadcast::routes([
            'as' => 'broadcasting.vendor.',
            'prefix' => 'api/v1/realtime/vendor',
            'middleware' => ['api', 'vendor.api', 'auth:vendor'],
        ]);

        Broadcast::routes([
            'as' => 'broadcasting.driver.',
            'prefix' => 'api/v1/realtime/driver',
            'middleware' => ['api', 'dm.api', 'auth:delivery_men'],
        ]);

        require base_path('routes/channels.php');

        if(addon_published_status('RideShare')){
            require base_path('Modules/RideShare/Routes/channels.php');
        }
    }
}
