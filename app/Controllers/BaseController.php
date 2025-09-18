<?php

namespace Enoc\Login\Controllers;

abstract  class BaseController
{
    /**
     * Renderizar vista
     */
    protected function view(string $view, array $data = []): string
    {
        // Hacer las variables disponibles en la vista
        extract($data);

        // Iniciar buffer de salida
        ob_start();

        // Buscar el archivo de vista
        $viewFile = $this->getViewPath($view);

        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            throw new \Exception("Vista {$view} no encontrada en {$viewFile}");
        }

        return ob_get_clean();
    }

    /**
     * Obtener ruta completa de la vista
     */
    private function getViewPath(string $view): string
    {
        // Convertir puntos en directorios: 'auth.login' -> 'auth/login'
        $viewPath = str_replace('.', '/', $view);

        return __DIR__ . "/../../views/{$viewPath}.php";
    }

    /**
     * Respuesta JSON
     */
    protected function json(array $data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json');
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Redireccionar
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Obtener datos POST de forma segura
     */
    protected function getPost(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Validar token CSRF (para implementar más adelante)
     */
    protected function validateCsrf(?string $submittedToken, ?string $sessionToken = null): bool
    {
        // Validar token CSRF.

        $sessionToken ??= $_SESSION['csrf_token'] ?? null;

        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        if (!is_string($submittedToken) || $submittedToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

}