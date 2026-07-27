<?php

use App\Broadcasting\DmLocationChannel;
use App\Broadcasting\UrbanGoodzChannelAuthorizer;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('dm_location_{id}', DmLocationChannel::class);

Broadcast::channel('ug.shopper.{customerId}.orders', function ($user, $customerId) {
    return app(UrbanGoodzChannelAuthorizer::class)->shopper($user, (int) $customerId);
});

Broadcast::channel('ug.vendor.{vendorId}.orders', function ($user, $vendorId) {
    return app(UrbanGoodzChannelAuthorizer::class)->vendor($user, (int) $vendorId);
});

Broadcast::channel('ug.driver.{deliveryManId}.assignments', function ($user, $deliveryManId) {
    return app(UrbanGoodzChannelAuthorizer::class)->driver($user, (int) $deliveryManId);
});

Broadcast::channel('ug.business.{businessClientId}.routes', function ($user, $businessClientId) {
    return app(UrbanGoodzChannelAuthorizer::class)->business($user, (int) $businessClientId);
});

Broadcast::channel('ug.dispatcher.{dispatcherId}.loads', function ($user, $dispatcherId) {
    return app(UrbanGoodzChannelAuthorizer::class)->dispatcher($user, (int) $dispatcherId);
});

Broadcast::channel('ug.payment.{accountType}.{accountId}.statuses', function ($user, $accountType, $accountId) {
    return app(UrbanGoodzChannelAuthorizer::class)->payment(
        $user,
        (string) $accountType,
        (int) $accountId
    );
});

Broadcast::channel('ug.support.{conversationId}', function ($user, $conversationId) {
    return app(UrbanGoodzChannelAuthorizer::class)->supportConversation(
        $user,
        (int) $conversationId
    );
});

Broadcast::channel('ug.admin.operations', function ($user) {
    return app(UrbanGoodzChannelAuthorizer::class)->admin($user);
});


