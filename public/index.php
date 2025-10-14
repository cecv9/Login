<?php

declare(strict_types=1);

/**
 * INDEX.PHP - Punto de Entrada Principal
 *
 * ============================================================================
 * 🎯 PROPÓSITO: Bootstrap de la Aplicación con FrontController
 * ============================================================================
 *
 * Este archivo es el "bootloader" que:
 * 1️⃣ Carga configuración (.env)
 * 2️⃣ Inicializa seguridad y sesión
 * 3️⃣ Configura Dependency Injection
 * 4️⃣ Ejecuta FrontController con Request/Response
 *
 * ============================================================================
 * 🚀 EJECUCIÓN SIMPLIFICADA
 * ============================================================================
 *
 * 1. Load .env variables
 * 2. Setup error reporting
 * 3. Initialize secure session
 * 4. Configure dependency container
 * 5. Execute FrontController
 *
 * ============================================================================
 * ⚡ CARACTERÍSTICAS
 * ============================================================================
 *
 * ✅ Strict typing
 * ✅ Modern FrontController architecture
 * ✅ Security-first approach
 * ✅ Dependency Injection ready
 * ✅ Production/Debug modes
 * ✅ Comprehensive error handling
 *
 * @package Enoc\Login
 * @author Enoc (Application Bootstrap)
 * @version 2.0.0 (FrontController)
 */

// Initial error configuration (will be refined after loading .env)
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/php-errors.log');

// ============================================================================
// 📦 IMPORTS LIMPIOS Y CORREGIDOS
// ============================================================================

// Core classes
use Enoc\Login\Core\{
    Router,
    FrontController,
    DependencyContainer,
    PdoConnection,
    RequestSecurity
};

// Exceptions
use Enoc\Login\Core\{
    DatabaseConnectionException
};

// Domain objects
use Enoc\Login\Core\Domain\{ Request };

// External dependencies
use Dotenv\Dotenv;
use Enoc\Login\Core\LogManager;
use Enoc\Login\Config\DatabaseConfig;

require dirname(__DIR__) . '/vendor/autoload.php';

$rootPath = dirname(__DIR__);

// ============================================================================
// ✅ 1) CONFIGURACIÓN INICIAL (mejorada con early returns)
// ============================================================================

// Load environment variables
Dotenv::createImmutable($rootPath)->safeLoad();
LogManager::logInfo('Aplicación iniciada con FrontController');

// Configure error reporting based on environment
$appDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    LogManager::logInfo('Application running in DEBUG mode');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}

date_default_timezone_set($_ENV['APP_TZ'] ?? 'UTC');

// ============================================================================
// ✅ 2) CONFIGURACIÓN BASE DE DATOS (mejorada)
// ============================================================================

$configFile = $rootPath . '/app/Config/database.php';
$db = is_file($configFile) ? require $configFile : null;

if (!is_array($db) || !isset($db['driver'],$db['host'],$db['user'],$db['password'],$db['database'],$db['charset'],$db['port'])) {
    $db = [
        'driver'   => $_ENV['DB_DRIVER']  ?? 'mysql',
        'host'     => $_ENV['DB_HOST']    ?? 'localhost',
        'user'     => $_ENV['DB_USER']    ?? 'root',
        'password' => $_ENV['DB_PASS']    ?? '',
        'database' => $_ENV['DB_NAME']    ?? '',
        'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'port'     => (int)($_ENV['DB_PORT'] ?? 3306),
    ];
}

$dbConfig = new DatabaseConfig(
    driver:   $db['driver'],
    host:     $db['host'],
    user:     $db['user'],
    password: $db['password'],
    database: $db['database'],
    charset:  $db['charset'],
    port:     $db['port'],
);

// ============================================================================
// ✅ 3) CONEXIÓN A BASE DE DATOS (con error handling)
// ============================================================================

try {
    $connection = new PdoConnection($dbConfig);
} catch (DatabaseConnectionException $e) {
    http_response_code(500);
    echo $appDebug
        ? 'DB connection error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        : 'Internal server error';
    exit;
}

// ============================================================================
// ✅ 4) CONFIGURACIÓN DE SEGURIDAD (refactorizada en clase helper)
// ============================================================================

/**
 * AppSecurity - Helper para configuración de seguridad
 *
 * Evita funciones globales y concentra toda la configuración de seguridad
 */
class AppSecurity
{
    /**
     * Aplicar headers de seguridad estándar
     */
    public static function setHeaders(): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        // Security headers
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; frame-ancestors 'none';");
        header('X-XSS-Protection: 1; mode=block');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        // HTTPS headers
        if (RequestSecurity::isHttps($_SERVER)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Configurar sesión segura
     */
    public static function setupSession(): void
    {
        $secure = RequestSecurity::isHttps($_SERVER);

        // Domain configuration
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

        // Cookie parameters
        $cookieParams = [
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        if ($domain !== '' && ($explicitDomainConfigured || strpos($domain, '.') !== false)) {
            $cookieParams['domain'] = $domain;
        }

        session_set_cookie_params($cookieParams);

        // Start session if not active
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Initialize CSRF token
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}

// Apply security configuration
AppSecurity::setHeaders();
AppSecurity::setupSession();

// ============================================================================
// ✅ 5) DEPENDENCY CONTAINER ARCHITECTURE
// ============================================================================

$container = DependencyContainer::getInstance();

// Bind database connection
$container->bind(PdoConnection::class, fn() => $connection);

// Bind Router con configuración completa
$container->bind(Router::class, function($container) use ($connection) {
    $router = new Router($connection);
    $router->setContainer($container);

    // Load routes
    $router->loadRoutes(__DIR__ . '/../app/Config/routes.php');

    // Middleware configuration (copiado exactamente de tu código)
    $router->middleware('GET',  '/dashboard',            ['auth']);
    $router->middleware('GET',  '/admin/users',          ['auth', 'role:admin']);
    $router->middleware('POST', '/admin/users',          ['auth', 'role:admin']);
    $router->middleware('GET',  '/admin/users/create',   ['auth', 'role:admin']);
    $router->middleware('POST', '/admin/users/update',   ['auth', 'role:admin']);
    $router->middleware('POST', '/admin/users/delete',   ['auth', 'role:admin']);

    // Rutas de auditoría - todas requieren autenticación y rol admin
    $router->middleware('GET',  '/admin/audit',          ['auth', 'role:admin']);
    $router->middleware('GET',  '/admin/audit/export',   ['auth', 'role:admin']);
    $router->middleware('GET',  '/admin/audit/admin',     ['auth', 'role:admin']);
    $router->middleware('GET',  '/admin/audit/history',  ['auth', 'role:admin']);

    return $router;
});

// Bind FrontController
$container->bind(FrontController::class, function($container) {
    return new FrontController(
        $container->get(Router::class),
        $container
    );
});

// ============================================================================
// ✅ 6) EJECUCIÓN PRINCIPAL (FrontController con error handling mejorado)
// ============================================================================

try {
    // Execute FrontController
    $frontController = $container->get(FrontController::class);
    $frontController->handle();

} catch (\Throwable $e) {
    // Error response setup
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    // Log error (siempre, independiente del modo debug)
    LogManager::logError('Unhandled exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

    // Display error based on environment
    if ($appDebug && php_sapi_name() !== 'cli') {
        // Debug mode - show full error details
        echo '<!DOCTYPE html>';
        echo '<html lang="es">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Error 500 - Debug Mode</title>';
        echo '</head>';
        echo '<body>';
        echo '<h1>❌ Error 500 - Internal Server Error</h1>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':' . $e->getLine() . '</p>';
        echo '<details><summary><strong>Stack Trace:</strong></summary>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
        echo '</details>';
        echo '</body>';
        echo '</html>';
    } else {
        // Production mode - generic error page
        echo '<!DOCTYPE html>';
        echo '<html lang="es">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Error del Servidor</title>';
        echo '</head>';
        echo '<body>';
        echo '<h1>🤕 Lo sentimos, algo salió mal.</h1>';
        echo '<p>Estamos trabajando para solucionar el problema.</p>';
        echo '<p>Por favor, intenta de nuevo más tarde.</p>';
        echo '<p><small>Error ID: ' . uniqid() . '</small></p>';
        echo '</body>';
        echo '</html>';
    }

    exit;
}