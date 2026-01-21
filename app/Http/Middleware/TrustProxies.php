<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Middleware для доверенных прокси (Traefik, Nginx, Load Balancers).
 *
 * Необходим для корректного определения:
 * - HTTPS (через X-Forwarded-Proto)
 * - Реального IP клиента (через X-Forwarded-For)
 * - Хоста (через X-Forwarded-Host)
 */
class TrustProxies extends Middleware
{
    /**
     * Доверенные прокси.
     *
     * В production за Traefik используем '*' для доверия всем прокси
     * в Docker сети. Это безопасно, т.к. внешний доступ только через Traefik.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * Заголовки, которые используются для определения информации о запросе.
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
