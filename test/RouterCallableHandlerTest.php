<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Enoc\Login\Core\Router;

$routesFile = tempnam(sys_get_temp_dir(), 'routes');

if ($routesFile === false) {
    throw new RuntimeException('No se pudo crear el archivo temporal de rutas.');
}

$className = 'TestCallableController' . str_replace('.', '', uniqid('', true));

$routesDefinition = <<<PHP
<?php

class {$className}
{
    public function handle(): string
    {
        return 'callable array executed';
    }
}

return [
    'GET' => [
        '/callable-array' => [new {$className}(), 'handle'],
    ],
];
PHP;

if (file_put_contents($routesFile, $routesDefinition) === false) {
    @unlink($routesFile);
    throw new RuntimeException('No se pudo escribir el archivo temporal de rutas.');
}

$router = new Router();

try {
    $router->loadRoutes($routesFile);

    $response = $router->dispatch('/callable-array', 'GET');

    if ($response !== 'callable array executed') {
        throw new RuntimeException('La ruta con callable en arreglo no retornó la respuesta esperada.');
    }

    echo "Los handlers tipo [instancia, method] se ejecutan sin errores fatales.\n";
} finally {
    @unlink($routesFile);
}
