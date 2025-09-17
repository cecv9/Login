<?php
// config/routes.php
return [
    'GET' => [
        '/'       => fn() => 'Bienvenido a Home',
        '/health' => fn() => 'ok',
        '/login'  => fn() => 'Aquí va la vista de login',
    ],
    'POST' => [
        '/login'  => fn() => 'Procesando login',
    ],
];
