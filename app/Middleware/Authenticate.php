<?php
declare(strict_types=1);

namespace Enoc\Login\Middleware;

final class Authenticate
{
    public function handle(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit('Redirecting to login...');
        }
    }
}