<?php

namespace Enoc\Login\Core;

class Router
{
   private array $routes = [];
   private string $controllerNamespace = "Enoc\\Login\\Controllers\\";
    /**
     * Cargar rutas desde archivo de configuración
     */
    public function loadRoutes(string $routesFile): void
    {
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

        foreach ($routes as $method => $routesForMethod) {
            if (!is_array($routesForMethod)) {
                $methodName = is_string($method) ? $method : (string) $method;
                throw new \UnexpectedValueException(
                    "Las rutas para el método {$methodName} deben estar definidas en un arreglo."
                );
            }
        }

        $this->routes = $routes;
    }

    /**
     * Procesar la petición actual
     */
    public function dispatch(string $requestUri, string $requestMethod): mixed
    {
        // Limpiar query parameters y barras adicionales
        $uri = rtrim(parse_url($requestUri, PHP_URL_PATH), '/') ?: '/';

        $routesForMethod = $this->routes[$requestMethod] ?? null;

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
    private function executeHandler(mixed $handler): mixed
    {
        // Si es una closure/función anónima
        if (is_callable($handler)) {
            return $handler();
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
    private function executeControllerMethod(string $handler): mixed
{
    [$controller, $method] = explode('@', $handler);
    $controllerClass = $this->controllerNamespace . $controller;

    if (!class_exists($controllerClass)) {
        throw new \Exception("Controlador {$controllerClass} no existe");
    }

    $instance = new $controllerClass();

    if (!method_exists($instance, $method)) {
        throw new \Exception("Método {$method} no existe en {$controllerClass}");
    }

    return $instance->$method();
}


    /**
     * Manejar error 405 Method Not Allowed
     */
    private function methodNotAllowed(array $allowedMethods): string
    {
        http_response_code(405);
        header('Allow: ' . implode(', ', $allowedMethods));

        return '405 Method Not Allowed';
    }

    /**
     * Obtener métodos permitidos para un URI
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




    /**
     * Manejar error 404
     */
    private function notFound(): string
    {
        http_response_code(404);
        return $this->render404();
    }

    /**
     * Renderizar página 404 básica
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