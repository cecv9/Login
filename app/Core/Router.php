<?php

namespace Enoc\Login\Core;

use Enoc\Login\Core\PdoConnection;
use Enoc\Login\Middleware\MiddlewareFactory;

class Router
{
    private const VALID_HTTP_METHODS = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
        'HEAD',
        'CONNECT',
        'TRACE',
    ];

    private array $routes = [];
    private string $controllerNamespace = "Enoc\\Login\\Controllers\\";
    private PdoConnection $pdoConnection;
    private array $routeMiddleware = []; // ← esta línea
    //private array $protectedRoutes = [];  // ← NUEVO: Array de rutas que requieren auth (e.g., ['/dashboard'])

    public function __construct(PdoConnection $pdoConnection) {
        $this->pdoConnection = $pdoConnection;
    }

    /**
     * Cargar rutas desde archivo de configuración
     */
    public function loadRoutes(string $routesFile): void {

        if (!file_exists($routesFile)) {
            throw new \Exception("Archivo de rutas no encontrado: {$routesFile}");
        }

        //$this->routes = require $routesFile;
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

            //$normalizedRoutes[$normalizedMethod] = $routesForMethod;
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


// NUEVO: Método para marcar rutas como protegidas (llamar en loadRoutes o constructor)
    public function protectRoute(string $path): void {
        $this->protectedRoutes[] = rtrim($path, '/') ?: '/';
    }

    public function middleware(string $method, string $path, array $middlewareList): void
    {
        $method = strtoupper($method);
        $path   = rtrim($path, '/') ?: '/';
        $this->routeMiddleware[$method][$path] = $middlewareList;
    }



    /**
     * Procesar la petición actual
     */
    public function dispatch(string $requestUri, string $requestMethod): mixed {
        // Limpiar query parameters y barras adicionales
       // $uri = rtrim(parse_url($requestUri, PHP_URL_PATH), '/') ?: '/';

        $parsedPath = parse_url($requestUri, PHP_URL_PATH);

        if ($parsedPath === false) {
            return $this->notFound();
        }

        $path = $parsedPath ?? '';
        $uri = rtrim($path, '/') ?: '/';

        $normalizedMethod = strtoupper($requestMethod);
        $routesForMethod = $this->routes[$normalizedMethod] ?? null;

     // ← NUEVO: middleware pipeline (reemplaza protectRoute)
        $middlewareKeys = $this->routeMiddleware[$normalizedMethod][$path] ?? [];
        foreach ($middlewareKeys as $key) {
            MiddlewareFactory::make($key)->handle();
        } // lanza redirect o exit

        // Buscar la ruta exacta
        if (is_array($routesForMethod) && isset($routesForMethod[$uri])) {
            return $this->executeHandler($routesForMethod[$uri]);
        }

        $allowedMethods = $this->findAllowedMethods($uri);

        if (!empty($allowedMethods)) {
            return $this->methodNotAllowed($allowedMethods);
        }

        // Si no encuentra la ruta, error 404
        return $this->notFound();
    }

    /**
     * Ejecutar el handler (closure o controlador)
     */
    private function executeHandler(mixed $handler): mixed  {
        // Si es una closure/función anónima
        if (is_callable($handler)) {
            return \call_user_func($handler);
        }

        // Si es string con formato "Controller@method"
        if (is_string($handler) && str_contains($handler, '@')) {
            return $this->executeControllerMethod($handler);
        }

        throw new \Exception("Handler inválido para la ruta");
    }

    /**
     * Ejecutar método de controlador
     */
    private function executeControllerMethod(string $handler): mixed {
    [$controller, $method] = explode('@', $handler);
    $controllerClass = $this->controllerNamespace . $controller;

    if (!class_exists($controllerClass)) {
        throw new \Exception("Controlador {$controllerClass} no existe");
    }

    $instance = new $controllerClass($this->pdoConnection);

    if (!method_exists($instance, $method)) {
        throw new \Exception("Método {$method} no existe en {$controllerClass}");
    }

    return $instance->$method();
}


    /**
     * Manejar error 405 Method Not Allowed
     */
    private function methodNotAllowed(array $allowedMethods): string {
        http_response_code(405);
        header('Allow: ' . implode(', ', $allowedMethods));

        return '405 Method Not Allowed';
    }

    /**
     * Obtener métodos permitidos para un URI
     */
    private function findAllowedMethods(string $uri): array {
        $allowedMethods = [];

        foreach ($this->routes as $method => $routes) {
            if (isset($routes[$uri])) {
                $allowedMethods[] = $method;
            }
        }

        return $allowedMethods;
    }





    /**
     * Manejar error 404
     */
    private function notFound(): string  {
        http_response_code(404);
        return $this->render404();
    }

    /**
     * Renderizar página 404 básica
     */
    private function render404(): string  {
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