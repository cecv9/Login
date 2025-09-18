<?php
declare(strict_types=1);
use Enoc\Login\Core\Router;
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
if(!is_array($db) || !isset($db['driver'],$db['host'],$db['user'],$db['password'],$db['database'],$db['charset'],$db['port'])) {
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
    port:     $db['port'], // ← Nuevo
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
/**
 * Detecta HTTPS considerando proxies y load balancers
 */
function isHttps(): bool {
    // 1. Detección estándar
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    // 2. Puerto 443 (conexión directa)
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }

    // 3. Headers de proxies comunes

    // X-Forwarded-Proto (Nginx, Apache, AWS ALB)
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }

    // X-Forwarded-SSL (algunos proxies)
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) &&
        $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        return true;
    }

    // Cloudflare
    if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
        $cloudflareVisitor = $_SERVER['HTTP_CF_VISITOR'];
        $cloudflareData = json_decode($cloudflareVisitor, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (($cloudflareData['scheme'] ?? null) === 'https') {
                return true;
            }
        } elseif (preg_match('/"?scheme"?\s*[:=]\s*"?https"?/i', $cloudflareVisitor) === 1) {
            return true;
        }

    }

    // X-Forwarded-Port (algunos load balancers)
    if (!empty($_SERVER['HTTP_X_FORWARDED_PORT']) &&
        (int)$_SERVER['HTTP_X_FORWARDED_PORT'] === 443) {
        return true;
    }

    return false;
}

$secure = isHttps();

//$domain = $_ENV['APP_DOMAIN'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
//$domain = explode(':', $domain)[0]; // Remover puerto
$configuredDomain = $_ENV['APP_DOMAIN'] ?? null;
$configuredDomain = is_string($configuredDomain) ? trim($configuredDomain) : '';
$explicitDomainConfigured = $configuredDomain !== '';

$detectedHost = $_SERVER['HTTP_HOST'] ?? '';
$detectedHost = is_string($detectedHost) ? trim($detectedHost) : '';
if ($detectedHost !== '') {
    $parsedHost = parse_url('//' . $detectedHost, PHP_URL_HOST);
    if (is_string($parsedHost) && $parsedHost !== '') {
        $detectedHost = $parsedHost;
    }
}

$domain = $explicitDomainConfigured ? $configuredDomain : $detectedHost;

$cookieParams=[
    'lifetime' => 0,
    'path'     => '/',
 //   'domain'   => $domain,
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
];

if ($domain !== '' && ($explicitDomainConfigured || strpos($domain, '.') !== false)) {
    $cookieParams['domain'] = $domain;
}

session_set_cookie_params($cookieParams);



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
try {
    $router = new Router();

    // Cargar rutas desde configuración
    $router->loadRoutes(__DIR__ . '/../app/Config/routes.php');

    // Procesar petición actual
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    echo $router->dispatch($requestUri, $requestMethod);

} catch (\Throwable $e) {
    http_response_code(500);
   // echo "Error del servidor: " . htmlspecialchars($e->getMessage());

    // En desarrollo, mostrar stack trace
    error_log('Unhandled exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    if ($appDebug) {
        echo 'Error del servidor: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'Error interno del servidor.';
    }

    exit;

}