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

        $this->routes = require $routesFile;
    }

    /**
     * Procesar la petición actual
     */
    public function dispatch(string $requestUri, string $requestMethod): mixed
    {
        // Limpiar query parameters y barras adicionales
        $uri = rtrim(parse_url($requestUri, PHP_URL_PATH), '/') ?: '/';

        // Verificar si existe el método HTTP
        if (!isset($this->routes[$requestMethod])) {
            return $this->notFound();
        }

        // Buscar la ruta exacta
        if (isset($this->routes[$requestMethod][$uri])) {
            return $this->executeHandler($this->routes[$requestMethod][$uri]);
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