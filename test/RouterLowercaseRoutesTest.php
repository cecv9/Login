<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Enoc\Login\Core\Router;

$routesFile = tempnam(sys_get_temp_dir(), 'routes');

if ($routesFile === false) {
    throw new RuntimeException('No se pudo crear el archivo temporal de rutas.');
}

$routesDefinition = <<<'PHP'
return [
    'get' => [
        '/lower' => fn() => 'lowercase handled',
    ],
];
PHP;

if (file_put_contents($routesFile, "<?php\n" . $routesDefinition) === false) {
    @unlink($routesFile);
    throw new RuntimeException('No se pudo escribir el archivo temporal de rutas.');
}

$router = new Router();

try {
    $router->loadRoutes($routesFile);

    $upperResponse = $router->dispatch('/lower', 'GET');
    $lowerResponse = $router->dispatch('/lower', 'get');

    if ($upperResponse !== 'lowercase handled') {
        throw new RuntimeException('La ruta declarada en minúsculas no se resolvió con método GET.');
    }

    if ($lowerResponse !== 'lowercase handled') {
        throw new RuntimeException('La ruta declarada en minúsculas no se resolvió con método get.');
    }

    echo "Las rutas declaradas con 'get' se resuelven correctamente.\n";
} finally {
    @unlink($routesFile);
}