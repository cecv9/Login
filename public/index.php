<?php
declare(strict_types=1);

use Dotenv\Dotenv;
use Enoc\Login\Config\DatabaseConfig;
use Enoc\Login\Core\PdoConnection;
use Enoc\Login\Core\DatabaseConnectionException;

require dirname(__DIR__) . '/vendor/autoload.php';

$rootPath = dirname(__DIR__);

/**
 * 1) Cargar .env  → llena $_ENV con tus variables
 */
Dotenv::createImmutable($rootPath)->safeLoad();

/**
 * 2) Modo debug / errores
 */
$appDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
    ini_set('display_errors', '0');
}
date_default_timezone_set($_ENV['APP_TZ'] ?? 'UTC');

/**
 * 3) Cargar configuración de BD
 *    - Si existe app/config/database.php (retorna un array), úsalo.
 *    - Si no, toma directo de $_ENV.
 */
$configFile = $rootPath . '/app/Config/database.php';
$db = is_file($configFile)
    ? require $configFile : null ;
    // Validar que el archivo sea array y tenga la claves necesarias
//Ahora SÍ valida el contenido:
//Carga el archivo y lo asigna a $db
//Verifica que sea un array con is_array($db)
//Verifica que tenga todas las claves necesarias con isset()
//Si no cumple cualquiera de las dos condiciones, usa el fallback seguro
//Esto previene errores como "Undefined array key" cuando se intenta acceder a $db['driver'] en la línea del DatabaseConfig.
if(!is_array($db) || !isset($db['driver'],$db['host'],$db['user'],$db['password'],$db['database'],$db['charset'])) {
    // Usar fallback si no es válido
    $db = [
        'driver'   => $_ENV['DB_DRIVER']  ?? 'mysql',
        'host'     => $_ENV['DB_HOST']    ?? 'localhost',
        'user'     => $_ENV['DB_USER']    ?? 'root',
        'password' => $_ENV['DB_PASS']    ?? '',
        'database' => $_ENV['DB_NAME']    ?? '',
        'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'port'     => (int)($_ENV['DB_PORT'] ?? 3306),  // ← Nuevo
    ];
};




/**
 * 4) Construir el DTO de configuración y abrir la conexión PDO (infra)
 */
$dbConfig = new DatabaseConfig(
    driver:   $db['driver'],
    host:     $db['host'],
    user:     $db['user'],
    password: $db['password'],
    database: $db['database'],
    charset:  $db['charset'],
);

try {
    $connection = new PdoConnection($dbConfig);
} catch (DatabaseConnectionException $e) {
    http_response_code(500);
    echo $appDebug
        ? 'DB connection error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        : 'Internal server error';
    exit;
}

/**
 * 5) Sesión segura (mínimos recomendados)
 */
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * 6) (Temporal) Mini-despacho hasta tener Router real
 *    Aquí solo confirmamos que el bootstrap funcionó.
 */
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/' && $method === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK: App lista. Conexión a BD establecida.\n";
    exit;
}

http_response_code(404);
echo 'Not Found';
