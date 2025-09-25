<?php
return [
    'GET' => [
        '/'         => fn() => 'Bienvenido a Home - <a href="/login">Ir a Login</a>',
        '/health'   => fn() => 'ok',
        '/login'    => 'AuthController@showLogin',
        '/dashboard' => 'DashboardController@show',
        '/register' => 'AuthController@showRegister',
    ],
    'POST' => [
        '/login'    => 'AuthController@processLogin',
        '/logout'   => 'AuthController@logout',
        '/register' => 'AuthController@processRegister',
    ],

];