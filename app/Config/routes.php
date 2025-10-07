<?php
return [
    'GET' => [
        '/'         => fn() => 'Bienvenido a Home - <a href="/login">Ir a Login</a>',
        '/health'   => fn() => 'ok',
        '/login'    => 'AuthController@showLogin',
        '/dashboard' => 'DashboardController@show',
        '/register' => 'AuthController@showRegister',
        '/admin/users' => 'AdminController@index',
        '/admin/users/create' => 'AdminController@create',
        '/admin/users/edit' => 'AdminController@edit',
        '/admin/users/delete' => 'AdminController@delete',

        // Rutas de auditoría (usan query strings para parámetros)
        '/admin/audit' => 'AuditDashboardController@index',
        '/admin/audit/export' => 'AuditDashboardController@export',
        '/admin/audit/user' => 'AuditDashboardController@userActions',
        '/admin/audit/history' => 'AuditDashboardController@userHistory',
    ],
    'POST' => [
        '/login'    => 'AuthController@processLogin',
        '/logout'   => 'AuthController@logout',
        '/register' => 'AuthController@processRegister',
        '/admin/users' => 'AdminController@store',
        '/admin/users/update' => 'AdminController@update',
        '/admin/users/delete' => 'AdminController@destroy',
    ],
];