<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Set to '*' so Cloudflare's ever-changing IP ranges are
     * all trusted automatically.
     *
     * @var array|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * Only FOR and PROTO. Cloudflare passes the original Host through
     * untouched and never sends X-Forwarded-Host/Port, and the origin is
     * reachable directly, bypassing Cloudflare. Trusting X-Forwarded-Host
     * from '*' would let anyone who hits the origin rewrite the host that
     * url() builds - poisoning password-reset links.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_PROTO;
}
