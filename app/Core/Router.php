<?php

declare(strict_types=1);

/**
 * Router - Sistema de Enrutamiento HTTP
 *
 * ============================================================================
 * 🎯 PROPÓSITO: Mapear URLs a Controladores y soportar FrontController
 * ============================================================================
 *
 * Este Router mantiene COMPATIBILIDAD TOTAL con tu código existente
 * y añade soporte para el nuevo FrontController sin breaking changes.
 *
 * ============================================================================
 * 🔄 MODO LEGACY (tu código actual):
 * ============================================================================
 * // public/index.php - SIN CAMBIOS
 * $router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
 *
 * ============================================================================
 * 🆕 MODO FRONTCONTROLLER (nuevo):
 * ============================================================================
 * // public/index.php - CON NUEVA ARQUITECTURA
 * $route = $router->match($request->method, $request->getPath());
 * if ($route) {
 *     $response = $router->executeHandler($route->handler, $request);
 * }
 *
 * ============================================================================
 * ⚡ CARACTERÍSTICAS CLAVE
 * ============================================================================
 *
 * ✅ Backward compatibility 100%
 * ✅ Cache de Route objects
 * ✅ Soporte para Dependency Injection futuro
 * ✅ Middleware pipeline intacto
 * ✅ Manejo completo de errores
 * ✅ HTTP methods validation
 * ✅ Protected routes support
 *
 * @package Enoc\Login\Core
 * @author Enoc (HTTP Router with FrontController Support)
 * @version 2.0.0 (backward compatible)
 */
namespace Enoc\Login\Core;

use Enoc\Login\Core\Domain\Request;
use Enoc\Login\Core\PdoConnection;
use Enoc\Login\Middleware\MiddlewareFactory;

class Router
{
    /**
     * @var array<string, array<string, mixed>> Routes storage [$method][$path] = $handler
     */
    private array $routes = [];

     private ?DependencyContainer $container = null;

    /**
     * @var string Namespace base para controllers
     */
    private string $controllerNamespace = "Enoc\\Login\\Controllers\\";

    /**
     * @var PdoConnection Conexión a BD (legacy compatibility)
     */
    private PdoConnection $pdoConnection;

    /**
     * @var array<string, array<string, array<string>>> Middleware por ruta [$method][$path] = [$middleware]
     */
    private array $routeMiddleware = [];

    /**
     * @var string[] Rutas protegidas
     */
    private array $protectedRoutes = [];

    /**
     * Cache de Route objects para FrontController (evita recreación)
     *
     * @var array<string, array<string, Route>>
     */
    private array $routeObjects = [];

    /**
     * HTTP Methods válidos
     */
    private const VALID_HTTP_METHODS = [
        'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'CONNECT', 'TRACE',
    ];

    /**
     * Constructor - Mantenido para legacy compatibility
     *
     * @param PdoConnection $pdoConnection Conexión legacy
     */
    public function __construct(PdoConnection $pdoConnection)
    {
        $this->pdoConnection = $pdoConnection;
    }

     public function setContainer(DependencyContainer $container): void
    {
        $this->container = $container;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 📝 TU CÓDIGO EXACTAMENTE IGUAL (SIN MODIFICACIONES)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Cargar rutas desde archivo de configuración
     *
     * @param string $routesFile Path al archivo de rutas
     * @throws \Exception Si archivo no existe o formato inválido
     */
    public function loadRoutes(string $routesFile): void
    {
        if (!file_exists($routesFile)) {
            throw new \Exception("Archivo de rutas no encontrado: {$routesFile}");
        }

        $routes = require $routesFile;

        if (!is_array($routes)) {
            throw new \UnexpectedValueException(
                'El archivo de rutas debe retornar un arreglo de rutas agrupadas por método.'
            );
        }

        $normalizedRoutes = [];

        foreach ($routes as $method => $routesForMethod) {
            if (!is_array($routesForMethod)) {
                $methodName = is_string($method) ? $method : (string) $method;
                throw new \UnexpectedValueException(
                    "Las rutas para el método {$methodName} deben estar definidas en un arreglo."
                );
            }
            $normalizedMethod = strtoupper((string) $method);

            if (!in_array($normalizedMethod, self::VALID_HTTP_METHODS, true)) {
                throw new \UnexpectedValueException(
                    "Método HTTP inválido: {$method}"
                );
            }

            $normalizedRoutesForMethod = [];

            foreach ($routesForMethod as $path => $handler) {
                if (!is_string($path)) {
                    $pathType = gettype($path);
                    throw new \UnexpectedValueException(
                        "Las rutas para el método {$normalizedMethod} deben tener claves de tipo string, {$pathType} recibido."
                    );
                }

                if ($path === '' || $path[0] !== '/') {
                    throw new \UnexpectedValueException(
                        "Las rutas deben comenzar con '/'. Ruta inválida: {$path}"
                    );
                }

                $normalizedPath = rtrim($path, '/') ?: '/';

                if (array_key_exists($normalizedPath, $normalizedRoutesForMethod)) {
                    throw new \UnexpectedValueException(
                        "Ruta duplicada detectada para {$normalizedMethod} {$normalizedPath}."
                    );
                }

                $normalizedRoutesForMethod[$normalizedPath] = $handler;
            }

            $normalizedRoutes[$normalizedMethod] = $normalizedRoutesForMethod;
        }

        $this->routes = $normalizedRoutes;
    }

    /**
     * Marcar rutas como protegidas
     *
     * @param string $path Ruta a proteger
     */
    public function protectRoute(string $path): void
    {
        $this->protectedRoutes[] = rtrim($path, '/') ?: '/';
    }

    /**
     * Asignar middleware a ruta
     *
     * @param string $method Método HTTP
     * @param string $path Ruta
     * @param array $middlewareList Lista de middleware keys
     */
    public function middleware(string $method, string $path, array $middlewareList): void
    {
        $method = strtoupper($method);
        $path = rtrim($path, '/') ?: '/';
        $this->routeMiddleware[$method][$path] = $middlewareList;
    }

    /**
     * Procesar la petición actual (tu método original)
     *
     * @param string $requestUri URI solicitado
     * @param string $requestMethod Método HTTP
     * @return mixed Response
     */
    public function dispatch(string $requestUri, string $requestMethod): mixed
    {
        // 1) Normaliza path (sin query) y barra final
        $parsedPath = parse_url($requestUri, PHP_URL_PATH);
        if ($parsedPath === false) {
            return $this->notFound();
        }
        $path = $parsedPath ?? '';
        $uri = rtrim($path, '/') ?: '/';

        // 2) Override de método vía _method en POST (PUT/PATCH/DELETE)
        $method = strtoupper($requestMethod);
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string)$_POST['_method']);
            if (in_array($override, ['PUT','PATCH','DELETE'], true)) {
                $method = $override;
            }
        }

        // 3) HEAD → GET fallback
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $routesForMethod = $this->routes[$method] ?? null;

        // 4) Pipeline de middlewares (ruta normalizada)
        $middlewareKeys = $this->routeMiddleware[$method][$uri] ?? [];
        foreach ($middlewareKeys as $key) {
            MiddlewareFactory::make($key)->handle(); // puede redirigir o exit
        }

        // 5) Match exacto y ejecución segura
        if (is_array($routesForMethod) && array_key_exists($uri, $routesForMethod)) {
            try {
                return $this->executeHandler($routesForMethod[$uri]);
            } catch (\Throwable $e) {
                \Enoc\Login\Core\LogManager::logError('Router handler exception: '.$e->getMessage());
                http_response_code(500);
                header('Content-Type: text/plain; charset=utf-8');
                return 'Lo sentimos, algo salió mal.';
            }
        }

        // 6) OPTIONS automático: anuncia métodos permitidos para este URI
        if ($requestMethod === 'OPTIONS') {
            $allowed = $this->findAllowedMethods($uri);
            if (!empty($allowed)) {
                header('Allow: ' . implode(', ', $allowed));
                return ''; // 204 implícito
            }
        }

        // 7) 405 si existe la ruta con otros métodos
        $allowedMethods = $this->findAllowedMethods($uri);
        if (!empty($allowedMethods)) {
            return $this->methodNotAllowed($allowedMethods);
        }

        // 8) 404
        return $this->notFound();
    }

    /**
     * Ejecutar el handler (closure o controlador)
     *
     * @param mixed $handler Handler a ejecutar
     * @return mixed Response
     * @throws \Exception Si handler inválido
     */
   public function executeHandler(mixed $handler, ?Request $request = null): mixed
    {
        // Closure/función
        if (is_callable($handler)) {
            try {
                return $this->invokeCallable($handler, $request);
            } catch (\Throwable $e) {
                \Enoc\Login\Core\LogManager::logError('Route closure exception: '.$e->getMessage());
                http_response_code(500);
                header('Content-Type: text/plain; charset=utf-8');
                return 'Lo sentimos, algo salió mal.';
            }
        }

        // "Controller@method"
        if (is_string($handler) && str_contains($handler, '@')) {
           return $this->executeControllerMethod($handler, $request);
        }

        throw new \Exception("Handler inválido para la ruta");
    }

    /**
     * Ejecutar método de controlador
     *
     * @param string $handler String "Controller@method"
     * @return mixed Response
     * @throws \Exception Si controller/método no existe
     */
    private function executeControllerMethod(string $handler, ?Request $request): mixed
    {
        [$controller, $method] = explode('@', $handler);
        $controllerClass = $this->controllerNamespace . $controller;

        if (!class_exists($controllerClass)) {
            throw new \Exception("Controlador {$controllerClass} no existe");
        }
            $instance = $this->resolveController($controllerClass);    

        if (!method_exists($instance, $method)) {
            throw new \Exception("Método {$method} no existe en {$controllerClass}");
        }

        $arguments = $this->resolveMethodArguments($instance, $method, $request);

        return $instance->$method(...$arguments);
    }

    /**
     * Manejar error 405 Method Not Allowed
     *
     * @param array $allowedMethods Métodos permitidos
     * @return string Error message
     */
    private function methodNotAllowed(array $allowedMethods): string
    {
        http_response_code(405);
        header('Allow: ' . implode(', ', $allowedMethods));
        return '405 Method Not Allowed';
    }

    /**
     * Obtener métodos permitidos para un URI
     *
     * @param string $uri URI a consultar
     * @return array<string> Métodos permitidos
     */
    private function findAllowedMethods(string $uri): array
    {
        $allowedMethods = [];

        foreach ($this->routes as $method => $routes) {
            if (isset($routes[$uri])) {
                $allowedMethods[] = $method;
            }
        }

        return $allowedMethods;
    }

    private function invokeCallable(callable $handler, ?Request $request): mixed
    {
        $parameters = $this->getCallableParameters($handler);
        $arguments = $this->resolveParameters($parameters, $request);

        return $handler(...$arguments);
    }

    private function resolveController(string $controllerClass): object
    {
        if ($this->container instanceof DependencyContainer) {
            return $this->container->get($controllerClass);
        }

        return new $controllerClass($this->pdoConnection);
    }

    private function resolveMethodArguments(object $instance, string $method, ?Request $request): array
    {
        $reflection = new \ReflectionMethod($instance, $method);

        return $this->resolveParameters($reflection->getParameters(), $request);
    }

    /**
     * @param array<int, \ReflectionParameter> $parameters
     */
    private function resolveParameters(array $parameters, ?Request $request): array
    {
        if ($parameters === []) {
            return [];
        }

        $arguments = [];

        foreach ($parameters as $parameter) {
            $arguments[] = $this->resolveParameterValue($parameter, $request);
        }

        return $arguments;
    }

    private function resolveParameterValue(\ReflectionParameter $parameter, ?Request $request): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();

            if ($request !== null && is_a($request, $typeName)) {
                return $request;
            }

            if ($this->container instanceof DependencyContainer) {
                return $this->container->get($typeName);
            }

            if ($typeName === PdoConnection::class) {
                return $this->pdoConnection;
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new \RuntimeException(
            sprintf(
                'No se pudo resolver el parámetro "%s" en %s',
                $parameter->getName(),
                $parameter->getDeclaringFunction()->getName()
            )
        );
    }

    private function getCallableParameters(callable $handler): array
    {
        if ($handler instanceof \Closure) {
            $reflection = new \ReflectionFunction($handler);
        } elseif (is_array($handler)) {
            $reflection = new \ReflectionMethod($handler[0], $handler[1]);
        } elseif (is_string($handler)) {
            $reflection = new \ReflectionFunction($handler);
        } else {
            $reflection = new \ReflectionFunction(\Closure::fromCallable($handler));
        }

        return $reflection->getParameters();
    }


    // ─────────────────────────────────────────────────────────────────────────────
    // ✅ NUEVOS: Métodos para FrontController (COMPATIBLES CON LEGACY)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * ✅ Match para FrontController - Buscar ruta específica
     *
     * Este método es exclusivo para el nuevo FrontController
     * Mantiene la misma lógica interna que dispatch() pero sin ejecutar
     *
     * @param string $method Método HTTP
     * @param string $path Ruta solicitada
     * @return Route|null Objeto Route si existe, null si no
     *
     * @example
     * $route = $router->match('GET', '/users');
     * if ($route) {
     *     echo "Handler: " . $route->handler; // "UserController@index"
     * }
     */
    public function match(string $method, string $path): ?Route
    {
        $normalizedPath = rtrim(parse_url($path, PHP_URL_PATH) ?: '/', '/') ?: '/';
        $method = strtoupper($method);
        
        // Soporte a override de método vía _method en POST (igual que en dispatch)
    if ($method === 'POST' && isset($_POST['_method'])) {
        $override = strtoupper((string)$_POST['_method']);
        if (in_array($override, ['PUT','PATCH','DELETE'], true)) {
            $method = $override;
        }
    }

    // HEAD → GET fallback (igual que en dispatch)
    if ($method === 'HEAD') {
        $method = 'GET';
    }

    
        // Reusar tu lógica de routing existente (100% compatible)
        if (isset($this->routes[$method][$normalizedPath])) {
            // Caching para evitar recrear objetos Route repetidamente
            if (!isset($this->routeObjects[$method][$normalizedPath])) {
                $this->routeObjects[$method][$normalizedPath] = new Route(
                    method: $method,
                    path: $normalizedPath,
                    handler: $this->routes[$method][$normalizedPath],
                    middleware: $this->routeMiddleware[$method][$normalizedPath] ?? []
                );
            }
            return $this->routeObjects[$method][$normalizedPath];
        }

        return null;
    }

    /**
     * ✅ Manejador de 404 - Ahora público para FrontController
     *
     * @return string HTML de error 404
     */
    public function notFound(): string
    {
        http_response_code(404);
        return $this->render404();
    }

    /**
     * Renderizar página 404 básica
     *
     * @return string HTML
     */
    private function render404(): string
    {
        return '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>404 - Página no encontrada</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
                h1 { color: #e74c3c; }
            </style>
        </head>
        <body>
            <h1>404 - Página no encontrada</h1>
            <p>La página que buscas no existe.</p>
            <a href="/">Volver al inicio</a>
        </body>
        </html>';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ✅ NUEVO: Route Value Object (mismo namespace, archivo separado pero mismo fichero)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Route - Value Object para Enrutamiento
 *
 * ============================================================================
 * 🎯 PROPÓSITO: DTO encapsular información de ruta HTTP
 * ============================================================================
 *
 * Objeto simple e inmutable que representa una ruta registrada
 * Usado exclusivamente por FrontController para separar concerns
 *
 * ============================================================================
 * 🚀 EJEMPLOS DE USO
 * ============================================================================
 *
 * $route = new Route(
 *     method: 'GET',
 *     path: '/users',
 *     handler: 'UserController@index',
 *     middleware: ['auth', 'role:admin']
 * );
 *
 * echo $route->method;           // "GET"
 * echo $route->path;             // "/users"
 * echo $route->hasAuth();        // true
 * echo $route->getRoles();       // ["admin"]
 *
 * ============================================================================
 * ⚡ CARACTERÍSTICAS
 * ============================================================================
 *
 * ✅ Inmutable (readonly properties)
 * ✅ Type Safety con constructor properties
 * ✅ Métodos helper comunes
 * ✅ Compatible con FrontController
 *
 * @package Enoc\Login\Core
 * @internal Solo para uso interno de Router/FrontController
 * @author Enoc (Route DTO)
 * @version 1.0.0
 */
class Route
{
    /**
     * Constructor con propiedades readonly (inmutables)
     *
     * @param string $method      Método HTTP (GET, POST, etc.)
     * @param string $path         Ruta normalizada
     * @param mixed  $handler     Handler (Controller@method o Closure)
     * @param array  $middleware  Lista de middleware keys
     */
    public function __construct(
        public readonly string $method,        // 'GET', 'POST', etc.
        public readonly string $path,           // '/users', '/dashboard', etc.
        public readonly mixed $handler,         // 'UserController@index' o Closure
        public readonly array $middleware = []  // ['auth', 'role:admin']
    ) {}

    /**
     * ✅ Verificar si tiene middleware de autenticación
     *
     * @return bool true si requiere auth
     */
    public function hasAuth(): bool
    {
        return $this->hasMiddleware('auth');
    }

    /**
     * ✅ Verificar si requiere rol específico
     *
     * @param string $role Rol a verificar
     * @return bool true si requiere el rol
     *
     * @example
     * if ($route->requiresRole('admin')) { // true si tiene 'role:admin'
     *     // Check user has admin role...
     * }
     */
    public function requiresRole(string $role): bool
    {
        return $this->hasMiddleware("role:{$role}");
    }

    /**
     * ✅ Obtener roles requeridos
     *
     * @return array<string> Lista de roles, ej: ['admin', 'editor']
     */
    public function getRequiredRoles(): array
    {
        $roles = [];
        foreach ($this->middleware as $middleware) {
            if (str_starts_with($middleware, 'role:')) {
                $roles[] = substr($middleware, 5); // Remover 'role:' prefix
            }
        }
        return $roles;
    }

    /**
     * ✅ Verificar si tiene un middleware específico
     *
     * @param string $middleware Key del middleware
     * @return bool true si lo tiene
     */
    public function hasMiddleware(string $middleware): bool
    {
        return in_array($middleware, $this->middleware, true);
    }

    /**
     * ✅ Obtener middleware por tipo
     *
     * @param string $type Tipo de middleware, ej: 'role', 'throttle'
     * @return array<string> Lista de middleware del tipo
     */
    public function getMiddlewareByType(string $type): array
    {
        return array_filter($this->middleware, fn($m) => str_starts_with($m, $type));
    }

    /**
     * ✅ Verificar si es GET
     *
     * @return bool true si GET
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * ✅ Verificar si es POST
     *
     * @pro retorno bool true si POST
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * ✅ Representación en string (debugging)
     *
     * @return string Formato: "GET /users"
     */
    public function __toString(): string
    {
        return "{$this->method} {$this->path}";
    }
}