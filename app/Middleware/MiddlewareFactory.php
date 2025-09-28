<?php
declare(strict_types=1);

namespace Enoc\Login\Middleware;

final class MiddlewareFactory
{
    private const MAP = [
        'auth'      => Authenticate::class,
        'role:user' => Authorize::class,
        'role:admin'=> Authorize::class,
    ];

    public static function make(string $key): object
    {
        return match ($key) {
            'auth'      => new Authenticate(),
            'role:user' => new Authorize('user'),
            'role:admin'=> new Authorize('admin'),
            default     => throw new \InvalidArgumentException("Middleware desconocido: $key")
        };
    }
}