<?php
// app/Config/routes.php
return [
    'GET' => [
        '/'         => fn() => 'Bienvenido a Home - <a href="/login">Ir a Login</a>',
        '/health'   => fn() => 'ok',
        '/login'    => 'AuthController@showLogin',
        '/logout'   => 'AuthController@logout',
    ],
    'POST' => [
        '/login'    => 'AuthController@processLogin',
    ],
];