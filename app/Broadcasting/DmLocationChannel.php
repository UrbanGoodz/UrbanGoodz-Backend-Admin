<?php

namespace App\Broadcasting;

class DmLocationChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Authenticate the user's access to the channel.
     */
    public function join($user, int $id): array|bool
    {
        return app(UrbanGoodzChannelAuthorizer::class)->driver($user, $id);
    }
}
