<?php
return [
    'GET' => [
        '/'         => fn() => 'Bienvenido a Home - <a href="/login">Ir a Login</a>',
        '/health'   => fn() => 'ok',
        '/login'    => 'AuthController@showLogin',
        '/dashboard' => 'DashboardController@show',
    ],
    'POST' => [
        '/login'    => 'AuthController@processLogin',
        '/logout'   => 'AuthController@logout',
    ],

];