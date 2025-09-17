<?php
return [
    'GET' => [
        '/'         => fn() => 'Bienvenido a Home - <a href="/login">Ir a Login</a>',
        '/health'   => fn() => 'ok',
        '/login'    => 'AuthController@showLogin',
        '/logout'   => 'AuthController@logout',
        '/dashboard' => fn() => '
            <h1>Dashboard</h1>
            <p>¡Login exitoso!</p>
            <p>Usuario: ' . ($_SESSION['user_name'] ?? 'Invitado') . '</p>
            <a href="/logout">Cerrar sesión</a>
        ',
    ],
    'POST' => [
        '/login'    => 'AuthController@processLogin',
    ],
];