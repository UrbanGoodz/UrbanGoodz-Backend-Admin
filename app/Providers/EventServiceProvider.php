<?php

namespace App\Providers;

use App\Models\BusinessSetting;
use App\Models\DataSetting;
use App\Models\Message;
use App\Models\Order;
use App\Models\Module;
use App\Models\Banner;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Observers\BusinessSettingObserver;
use App\Observers\BannerObserver;
use App\Observers\DataSettingObserver;
use App\Observers\MessageObserver;
use App\Observers\OrderObserver;
use App\Observers\ModuleObserver;
use App\Observers\UrbanGoodzDedicatedRouteObserver;
use App\Observers\UrbanGoodzLoadBoardLoadObserver;
use App\Observers\UrbanGoodzPaymentTransactionObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \Laravel\Reverb\Events\MessageReceived::class => [
        \App\Listeners\HandleClientMessage::class,
    ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        Order::observe(OrderObserver::class);
        Message::observe(MessageObserver::class);
        UrbanGoodzDedicatedRoute::observe(UrbanGoodzDedicatedRouteObserver::class);
        UrbanGoodzLoadBoardLoad::observe(UrbanGoodzLoadBoardLoadObserver::class);
        UrbanGoodzPaymentTransaction::observe(UrbanGoodzPaymentTransactionObserver::class);
        BusinessSetting::observe(BusinessSettingObserver::class);
        Banner::observe(BannerObserver::class);
        DataSetting::observe(DataSettingObserver::class);
        Module::observe(ModuleObserver::class);
    }
}
