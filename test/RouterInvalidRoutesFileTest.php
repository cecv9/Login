<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Enoc\Login\Core\Router;

/**
 * @param string $phpCode
 * @return string
 */
function createTemporaryRoutesFile(string $phpCode): string
{
    $tempFile = tempnam(sys_get_temp_dir(), 'routes');

    if ($tempFile === false) {
        throw new RuntimeException('No se pudo crear un archivo temporal para las rutas.');
    }

    $phpContent = "<?php\n" . $phpCode;

    if (file_put_contents($tempFile, $phpContent) === false) {
        throw new RuntimeException('No se pudo escribir el archivo temporal de rutas.');
    }

    return $tempFile;
}

$router = new Router();

$invalidFiles = [
    'valor escalar' => "return 'valor';\n",
    'definición por método no arreglo' => "return [\n    'GET' => 'handler',\n];\n",
];

foreach ($invalidFiles as $description => $phpCode) {
    $file = createTemporaryRoutesFile($phpCode);

    try {
        $router->loadRoutes($file);
        throw new RuntimeException('Se esperaba una excepción para el caso: ' . $description);
    } catch (UnexpectedValueException $exception) {
        if ($exception->getMessage() === '') {
            throw new RuntimeException('La excepción debe incluir un mensaje descriptivo.');
        }
    } finally {
        @unlink($file);
    }
}

echo "Los archivos de rutas inválidos fueron rechazados correctamente.\n";