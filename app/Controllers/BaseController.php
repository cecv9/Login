<?php

namespace Enoc\Login\Controllers;


abstract  class BaseController
{
    /**
     * Renderizar vista
     */

    protected function view(string $view, array $data = []): string{
        // 0) Sanitización y validación de $view (igual)
        $view = trim($view, '/ ');
        if (empty($view)) {
            throw new \InvalidArgumentException('Nombre de vista vacío.');
        }
        if (!preg_match('#^[a-zA-Z0-9/_\.-]+$#', $view)) {
            error_log("Vista rechazada por regex: '{$view}'");
            throw new \InvalidArgumentException('Nombre de vista inválido.');
        }

        // 1) Sanitización mejorada: Preserva null, auto-escapa strings, castea solo no-scalars
        $sanitizedData = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                $sanitizedData[$key] = null;  // Preserva null
            } elseif (is_string($value)) {
                $sanitizedData[$key] = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');  // Escape strings
            } elseif (is_array($value) || is_object($value)) {
                $sanitizedData[$key] = $value;  // ← FIX: NO castees arrays/objects – presérvalos para loops/getters
            } else {
                $sanitizedData[$key] = $value;  // Scalars intactos
            }
        }

        // 2-5) Resto igual (resolución de ruta, render, etc.)
        $baseDir = rtrim($this->viewsDir ?? realpath(__DIR__ . '/../../views'), DIRECTORY_SEPARATOR);
        if ($baseDir === false) {
            throw new \RuntimeException('Directorio de vistas no disponible.');
        }
        $normalizedViewsDir = $baseDir . DIRECTORY_SEPARATOR;

        $viewPath = str_replace('.', '/', $view);
        $candidate = $baseDir . '/' . ltrim($viewPath, '/') . '.php';
        $resolvedViewFile = realpath($candidate);

        if ($resolvedViewFile === false || !file_exists($resolvedViewFile) || !is_readable($resolvedViewFile)) {
            throw new \RuntimeException('Vista no encontrada.');
        }

        if (strncmp($resolvedViewFile, $normalizedViewsDir, strlen($normalizedViewsDir)) !== 0) {
            throw new \RuntimeException('Acceso a vista fuera del directorio permitido.');
        }

        $render = static function (string $__file, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            require $__file;
        };

        $level = ob_get_level();
        ob_start();
        try {
            $render($resolvedViewFile, $sanitizedData);
            return ob_get_clean();
        } catch (\Throwable $ex) {
            while (ob_get_level() > $level) { ob_end_clean(); }
            error_log("Error rendering view '{$view}': " . $ex->getMessage());
            throw new \RuntimeException('Error al renderizar la vista.');
        }
    }

    /**
     * Obtener ruta completa de la vista
     */
    private function getViewPath(string $view): string {
        // Convertir puntos en directorios: 'auth.login' -> 'auth/login'
        $viewPath = str_replace('.', '/', $view);

        return __DIR__ . "/../../views/{$viewPath}.php";
    }

    /**
     * Respuesta JSON
     */
    protected function json(array $data, int $status = 200): string  {
        http_response_code($status);
        header('Content-Type: application/json');
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Redireccionar
     */
   protected function redirect(string $url): void  {
        header("Location: {$url}");
        exit;
    }

    /**
     * Obtener datos POST de forma segura
     */
    protected function getPost(string $key, mixed $default = null): mixed {
        return $_POST[$key] ?? $default;
    }

    /**
     * Validar token CSRF (para implementar más adelante)
     */
    protected function validateCsrf(?string $submittedToken, ?string $sessionToken = null): bool {
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

    // ← NUEVO: Helper para generar token fresco (llama en show* methods)
    protected function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $_SESSION['csrf_token_time'] = time();  // Reset expiración si usas
        return $_SESSION['csrf_token'];
    }


    // app/Controllers/BaseController.php
    protected function rotateCsrf(): string
    {
        $new = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $new;
        $_SESSION['csrf_token_time'] = time(); // opcional TTL
        return $new;
    }



}