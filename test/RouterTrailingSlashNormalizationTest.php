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
    'GET' => [
        '/foo/' => fn() => 'foo handler',
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

    $responseWithSlash = $router->dispatch('/foo/', 'GET');
    $responseWithoutSlash = $router->dispatch('/foo', 'GET');

    if ($responseWithSlash !== 'foo handler') {
        throw new RuntimeException('La ruta con barra final no resolvió el handler esperado.');
    }

    if ($responseWithoutSlash !== 'foo handler') {
        throw new RuntimeException('La ruta sin barra final no resolvió el handler esperado.');
    }

    echo "Las rutas con y sin barra final se resuelven correctamente.\n";
} finally {
    @unlink($routesFile);
}

