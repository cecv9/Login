<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Enoc\Login\Core\Router;

$routesFile = tempnam(sys_get_temp_dir(), 'routes');

if ($routesFile === false) {
    throw new RuntimeException('No se pudo crear el archivo temporal de rutas.');
}

$routesDefinition = <<<PHP
<?php
return [
    'GET' => [
        '/' => static fn () => 'home',
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

    http_response_code(200);

    $response = $router->dispatch('http:////', 'GET');

    if (http_response_code() !== 404) {
        throw new RuntimeException('Se esperaba un código de respuesta 404 para URI inválida.');
    }

    if (!is_string($response) || strpos($response, '404') === false) {
        throw new RuntimeException('La respuesta para URI inválida debe ser la página 404.');
    }

    echo "Las URIs inválidas se manejan devolviendo 404 en lugar de error fatal.\n";
} finally {
    @unlink($routesFile);
}