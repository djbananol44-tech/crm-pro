<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * URI, которые должны быть исключены из CSRF-проверки.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/webhooks/*',
        'export/*',
    ];
}
