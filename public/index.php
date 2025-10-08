<?php


declare(strict_types=1);

// Initial error configuration (will be refined after loading .env)
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/php-errors.log');

// ... resto de tu código
use Enoc\Login\Core\Router;
use Dotenv\Dotenv;
use Enoc\Login\Core\LogManager;
use Enoc\Login\Config\DatabaseConfig;
use Enoc\Login\Core\PdoConnection;
use Enoc\Login\Core\DatabaseConnectionException;
use Enoc\Login\Repository\UsuarioRepository;
use Enoc\Login\Services\UserService;
use Enoc\Login\Controllers\AdminController;

require dirname(__DIR__) . '/vendor/autoload.php';

$rootPath = dirname(__DIR__);

/**
 * 1) Cargar .env  → llena $_ENV con tus variables
 */
Dotenv::createImmutable($rootPath)->safeLoad();

// Inicializar LogManager
//LogManager::init();
LogManager::logInfo('Aplicación iniciada');

/*****************************************************************
 * 2) Modo debug / errores + seguridad de producción
 *****************************************************************/
$appDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

/* Configuración de errores basada en modo debug */
if ($appDebug) {
    // Development mode: Show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    LogManager::logInfo('Application running in DEBUG mode');
} else {
    // Production mode: Hide errors from users, log internally
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    // Ensure errors are logged
    ini_set('log_errors', '1');
}

date_default_timezone_set($_ENV['APP_TZ'] ?? 'UTC');

/**
 * 3) Cargar configuración de BDeee
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
 * Set security headers for all responses
 * Protects against common web vulnerabilities (OWASP recommendations)
 */
function setSecurityHeaders(): void {
    if (php_sapi_name() === 'cli') {
        return; // Skip in CLI mode
    }

    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Control referrer information
    header('Referrer-Policy: no-referrer');
    
    // Content Security Policy - restrictive but allows inline styles for compatibility
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; frame-ancestors 'none';");
    
    // Enable XSS protection (legacy, but doesn't hurt)
    header('X-XSS-Protection: 1; mode=block');
    
    // HSTS - Force HTTPS for 1 year (only send over HTTPS)
    if (isHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Permissions Policy - disable unnecessary features
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
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
 * 6) Apply security headers to all responses
 */
setSecurityHeaders();

/**
 * 7) Router dispatch and request handling
 */
try {
    $router = new Router($connection);

    // Cargar rutas desde configuración
    $router->loadRoutes(__DIR__ . '/../app/Config/routes.php');

    // Middleware configuration
    $router->middleware('GET',  '/dashboard',            ['auth']);
    $router->middleware('GET',  '/admin/users',          ['auth', 'role:admin']);
    $router->middleware('POST', '/admin/users',          ['auth', 'role:admin']);
    $router->middleware('GET',  '/admin/users/create',   ['auth', 'role:admin']);
    $router->middleware('POST', '/admin/users/update',   ['auth', 'role:admin']);
    $router->middleware('POST', '/admin/users/delete',   ['auth', 'role:admin']);
    // Rutas de auditoría - todas requieren autenticación y rol admin
    $router->middleware('GET',  '/admin/audit',          ['auth', 'role:admin']);
    $router->middleware('GET',  '/admin/audit/export',   ['auth', 'role:admin']);
    $router->middleware('GET',  '/admin/audit/user',     ['auth', 'role:admin']);
    $router->middleware('GET',  '/admin/audit/history',  ['auth', 'role:admin']);

    // Procesar petición actual
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    echo $router->dispatch($requestUri, $requestMethod);

} catch (\Throwable $e) {
    // Set error response code
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    // Log error internally (always log, regardless of debug mode)
    LogManager::logError('Unhandled exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

    // Display appropriate error message based on environment
    if ($appDebug && php_sapi_name() !== 'cli') {
        // Development mode: Show detailed error information
        echo '<h1>Error 500 - Internal Server Error</h1>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':' . $e->getLine() . '</p>';
        echo '<pre><strong>Stack Trace:</strong>' . "\n" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
    } else {
        // Production mode: Generic error message (no sensitive information)
        echo '<!DOCTYPE html>';
        echo '<html lang="es">';
        echo '<head><meta charset="UTF-8"><title>Error del Servidor</title></head>';
        echo '<body>';
        echo '<h1>Lo sentimos, algo salió mal.</h1>';
        echo '<p>Estamos trabajando para solucionar el problema. Por favor, intenta de nuevo más tarde.</p>';
        echo '</body>';
        echo '</html>';
    }

    exit;
}