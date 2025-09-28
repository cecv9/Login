<?php
declare(strict_types=1);

namespace Enoc\Login\Middleware;

final class Authorize
{
    public function __construct(private string $role) {}

    public function handle(): void
    {
        if (($_SESSION['user_role'] ?? 'user') !== $this->role) {
            header('Location: /login');
            exit('Redirecting to login...');
        }
    }
}