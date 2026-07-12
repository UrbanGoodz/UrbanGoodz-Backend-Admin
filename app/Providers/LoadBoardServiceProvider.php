<?php

namespace App\Providers;

use App\Contracts\LoadBoard\LoadBoardProviderInterface;
use App\Services\UrbanGoodz\LoadBoard\DatAdapter;
use App\Services\UrbanGoodz\LoadBoard\TruckstopAdapter;
use Illuminate\Support\ServiceProvider;

class LoadBoardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('loadboard.providers', function ($app) {
            $config = config('urban_goodz_load_board.providers', []);
            $providers = [];

            if (!empty($config['dat']['enabled'])) {
                $providers['dat'] = new DatAdapter($config['dat']);
            }

            if (!empty($config['truckstop']['enabled'])) {
                $providers['truckstop'] = new TruckstopAdapter($config['truckstop']);
            }

            return $providers;
        });

        $this->app->bind(LoadBoardProviderInterface::class, function ($app, array $params = []) {
            $slug = $params['provider'] ?? 'dat';
            $providers = $app->make('loadboard.providers');

            if (!isset($providers[$slug])) {
                throw new \InvalidArgumentException("Load board provider [{$slug}] is not configured or enabled.");
            }

            return $providers[$slug];
        });
    }

    public function boot(): void
    {
        //
    }
}
