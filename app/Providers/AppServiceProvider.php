<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Traits\AddonHelper;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Config;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use App\Services\Translations\RuntimeTranslationLoader;
use App\Services\Translations\RuntimeTranslationRepository;

class AppServiceProvider extends ServiceProvider
{
    use AddonHelper;
    /**
     * Register any application services. 
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(RuntimeTranslationRepository::class);
        $this->app->extend('translation.loader', fn ($loader, $app) => new RuntimeTranslationLoader(
            $loader,
            $app->make(RuntimeTranslationRepository::class),
        ));

        $this->app->bind(
            \App\Contracts\FashionFitMeasurementProvider::class,
            function ($app) {
                // The HTTP provider needs an external vendor endpoint. When one is not
                // configured, fall back to the in-platform silhouette engine so photo
                // analysis still produces real measurements.
                $http = $app->make(\App\Services\FashionFit\HttpFashionFitMeasurementProvider::class);
                if (config('fashion_fit_ai.provider') !== 'silhouette' && $http->configured()) {
                    return $http;
                }

                return $app->make(\App\Services\FashionFit\SilhouetteFashionFitMeasurementProvider::class);
            },
        );
        $this->app->bind(
            \App\Contracts\ServiceBookingPaymentGateway::class,
            function () {
                $provider = config('service_bookings.payment.provider', 'stripe');
                if ($provider === 'stripe') {
                    return new \App\Services\ServiceBookings\StripeServiceBookingPaymentGateway();
                }
                return new \App\Services\ServiceBookings\HttpSandboxServiceBookingPaymentGateway();
            },
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        //TODO: need to remove after 3.8 development
        if (app()->environment('local')) {
            if (request()->header('x-forwarded-proto') === 'https' || request()->getScheme() === 'https') {
                \URL::forceScheme('https');
            }
            if(request()->header('x-forwarded-host')) {
                \URL::forceRootUrl('https://' . request()->header('x-forwarded-host'));
            }
        }

        try
        {
            Request::macro('isAny', function (array $patterns) {
                return collect($patterns)->contains(fn ($pattern) => Request::is($pattern));
            });

            Config::set('addon_admin_routes',$this->get_addon_admin_routes());
            Config::set('get_payment_publish_status',$this->get_payment_publish_status());
            Paginator::useBootstrap();
            foreach(Helpers::get_view_keys() as $key=>$value)
            {
                view()->share($key, $value);
            }
        }
        catch(\Exception $e)
        {

        }

    }
}
