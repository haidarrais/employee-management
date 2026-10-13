<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * The app runs in a container behind a reverse proxy (Traefik / Coolify)
     * on the same Docker network, so every request arrives from the proxy and
     * carries the real client address in X-Forwarded-For. Without this the
     * framework only sees the proxy's plain-HTTP hop: it generates http://
     * URLs for redirects and asset links and refuses to mark the session
     * cookie Secure, which downgrades every authenticated page.
     *
     * `*` trusts any proxy, which is acceptable only because the container
     * port is not published on the host — the sole route in is the proxy. Set
     * TRUSTED_PROXIES to a comma-separated list of IPs if that ever changes.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    public function __construct()
    {
        $configured = env('TRUSTED_PROXIES');

        $this->proxies = $configured ? explode(',', $configured) : '*';
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
