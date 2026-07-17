<?php

namespace App\Providers;

use App\Contracts\LoadBoard\LoadBoardProviderInterface;
use App\Contracts\LoadSource\LoadSourceAdapter;
use App\Services\UrbanGoodz\LoadBoard\DatAdapter;
use App\Services\UrbanGoodz\LoadBoard\TruckstopAdapter;
use App\Services\UrbanGoodz\LoadSource\DatLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\DirectFreightLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\EmailLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\ManualLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\TruckSmarterLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\TruckerPathLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\TruckstopLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\TrulosLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\TbLoadLoadSourceAdapter;
use App\Services\UrbanGoodz\LoadSource\UrbanGoodzInternalLoadSourceAdapter;
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

        $this->app->singleton('loadsourcing.adapters', function ($app) {
            $config = config('urban_goodz_load_board.providers', []);

            return [
                'urban_goodz_internal' => new UrbanGoodzInternalLoadSourceAdapter($config),
                'email_inbox' => new EmailLoadSourceAdapter($config),
                'manual_import' => new ManualLoadSourceAdapter($config),
                'dat' => new DatLoadSourceAdapter($config),
                'truckstop' => new TruckstopLoadSourceAdapter($config),
                'trulos' => new TrulosLoadSourceAdapter($config),
                'tb_load' => new TbLoadLoadSourceAdapter($config),
                'direct_freight' => new DirectFreightLoadSourceAdapter($config),
                'trucker_path' => new TruckerPathLoadSourceAdapter($config),
                'trucksmarter' => new TruckSmarterLoadSourceAdapter($config),
            ];
        });

        $this->app->bind(LoadSourceAdapter::class, function ($app, array $params = []) {
            $key = $params['source'] ?? 'urban_goodz_internal';
            $adapters = $app->make('loadsourcing.adapters');

            if (!isset($adapters[$key])) {
                throw new \InvalidArgumentException("Load source adapter [{$key}] is not registered.");
            }

            return $adapters[$key];
        });
    }

    public function boot(): void
    {
        //
    }
}
