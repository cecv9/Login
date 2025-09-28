<?php
return [
    'GET' => [
        '/'         => fn() => 'Bienvenido a Home - <a href="/login">Ir a Login</a>',
        '/health'   => fn() => 'ok',
        '/login'    => 'AuthController@showLogin',
        '/dashboard' => 'DashboardController@show',
        '/register' => 'AuthController@showRegister',
        '/admin/users' => 'AdminController@index',  //Listar usuarios
        '/admin/users/create' => 'AdminController@create', //Formulario crear usuario
        '/admin/users/{id}/edit' => 'AdminController@edit',   //Formulario editar usuario
        '/admin/users/{id}/delete' => 'AdminController@delete', //Confirmar eliminación usuario
    ],
    'POST' => [
        '/login'    => 'AuthController@processLogin',
        '/logout'   => 'AuthController@logout',
        '/register' => 'AuthController@processRegister',
        '/admin/users' => 'AdminController@store',       //Guardar nuevo usuario
        '/admin/users/{id}/update' => 'AdminController@update', //Actualizar usuario
        '/admin/users/{id}/delete' => 'AdminController@destroy', //Eliminar usuario
    ],

];