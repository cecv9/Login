<?php
return [
    'GET' => [
        '/'         => fn() => 'Bienvenido a Home - <a href="/login">Ir a Login</a>',
        '/health'   => fn() => 'ok',
        '/login'    => 'AuthController@showLogin',
        '/dashboard' => function () {
            $userName = htmlspecialchars($_SESSION['user_name'] ?? 'Invitado', ENT_QUOTES, 'UTF-8');
            $csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');

            return <<<HTML
                <h1>Dashboard</h1>
                <p>¡Login exitoso!</p>
                <p>Usuario: {$userName}</p>
                <form action="/logout" method="POST">
                    <input type="hidden" name="csrf_token" value="{$csrfToken}">
                    <button type="submit">Cerrar sesión</button>
                </form>
            HTML;
        },
    ],
    'POST' => [
        '/login'    => 'AuthController@processLogin',
        '/logout'   => 'AuthController@logout',
    ],

];