<?php

declare(strict_types=1);

/**
 * Request - Value Object para Request HTTP
 *
 * ============================================================================
 * 🎯 PROPÓSITO: Abstracción de Petición HTTP
 * ============================================================================
 *
 * Este Value Object encapsula Toda la información de una petición HTTP
 * Reemplaza el uso directo de superglobales ($_GET, $_POST, $_SERVER, etc.)
 *
 * ============================================================================
 * 🚀 EJEMPLOS DE USO EN CONTROLLERS
 * ============================================================================
 *
 * class LoginController {
 *     public function login(Request $request): Response
 *     {
 *         // Datos del cuerpo
 *         $email = $request->getBody('email');
 *         $password = $request->getBody('password');
 *
 *         // Query params
 *         $redirect = $request->getQuery('redirect', '/dashboard');
 *
 *         // Info del request
 *         if ($request->isAjax()) {
 *             return Response::json(['message' => 'AJAX login']);
 *         }
 *
 *         if ($request->isPost()) {
 *             // Procesar login...
 *         }
 *
 *         return Response::html($this->renderLoginForm());
 *     }
 * }
 *
 * ============================================================================
 * ⚡ CARACTERÍSTICAS
 * ============================================================================
 *
 * ✅ Inmutable (readonly properties)
 * ✅ Type Safety con strict_types
 * ✅ Compatible con superglobales (bridge temporal)
 * ✅ Métodos helper para casos comunes
 * ✅ Soporte para uploads, cookies, headers
 * ✅ IP detection con proxies
 * ✅ Framework agnostic
 *
 * @package Enoc\Login\Core\Domain
 * @author Enoc (HTTP Abstraction Layer)
 * @version 1.0.0
 */
namespace Enoc\Login\Core\Domain;

use Enoc\Login\Core\RequestSecurity;

class Request
{
    /**
     * Constructor con Propiedades Readonly (INMUTABLES)
     *
     * @param string $method      Método HTTP (GET, POST, PUT, DELETE, etc.)
     * @param string $uri         URI completo con query string
     * @param array  $headers     Headers HTTP (key => value)
     * @param array  $query       Parámetros URL ($_GET)
     * @param array  $body        Cuerpo PETICIÓN ($_POST para forms)
     * @param array  $cookies     Cookies ($_COOKIE)
     * @param array  $files       Subidas ($_FILES)
     * @param array  $server      Servidor ($_SERVER)
     */
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $headers = [],
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $cookies = [],
        public readonly array $files = [],
        public readonly array $server = []
    ) {}

    /**
     * ✅ Factory Method - Crear Request desde superglobales
     *
     * Bridge entre código legacy (superglobales) y objeto Request futuro
     * Permite migración gradual sin breaking changes
     *
     * @return self Nueva instancia desde $_GET, $_POST, $_SERVER, etc.
     */
    public static function fromGlobals(): self
    {
        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            uri: $_SERVER['REQUEST_URI'] ?? '/',
            headers: self::getAllHeaders(),  // Fallback seguro
            query: $_GET,
            body: $_POST,
            cookies: $_COOKIE,
            files: $_FILES,
            server: $_SERVER
        );
    }

    /**
     * ✅ Extraer Path sin Query String
     *
     * @return string Path solamente (ej: "/admin/users")
     */
    public function getPath(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH) ?? '/';
        return '/' . trim($path, '/');
    }

    /**
     * ✅ Extraer Query String (parametros después de ?)
     *
     * @return string Query string sin '?'
     */
    public function getQueryString(): string
    {
        return parse_url($this->uri, PHP_URL_QUERY) ?? '';
    }

    /**
     * ✅ Verificar si es método POST
     *
     * @return bool true si POST
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * ✅ Verificar si es método GET
     *
     * @return bool true si GET
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * ✅ Verificar si es AJAX (X-Requested-With header)
     *
     * @return bool true si AJAX
     */
    public function isAjax(): bool
    {
        return ($this->headers['X-Requested-With'] ?? '') === 'XMLHttpRequest' ||
            ($this->headers['x-requested-with'] ?? '') === 'XMLHttpRequest';
    }

    /**
     * ✅ Obtener Header Specífico (case-insensitive)
     *
     * @param string $name Nombre del header
     * @return string|null Header value o null si no existe
     *
     * @example
     * $contentType = $request->getHeader('Content-Type');
     * $userAgent = $request->getHeader('User-Agent');
     */
    public function getHeader(string $name): ?string
    {
        // Intentar exact match primero
        if (isset($this->headers[$name])) {
            return $this->headers[$name];
        }

        // Case-insensitive fallback
        $nameUpper = strtoupper($name);
        foreach ($this->headers as $headerName => $value) {
            if (strtoupper($headerName) === $nameUpper) {
                return $value;
            }
        }

        return null;
    }

    /**
     * ✅ Obtener Parámetro URL ($_GET)
     *
     * @param string $key     Clave del parámetro
     * @param mixed $default Valor por defecto si no existe
     * @return mixed
     *
     * @example
     * $page = $request->getQuery('page', 1);
     * $search = $request->getQuery('q', '');
     */
    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * ✅ Obtener Parámetro Cuerpo ($_POST)
     *
     * @param string $key     Clave del parámetro
     * @param mixed $default Valor por defecto si no existe
     * @return mixed
     *
     * @example
     * $email = $request->getBody('email');
     * $remember = $request->getBody('remember', false);
     */
    public function getBody(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * ✅ Obtener Parámetro (body优先，query后)
     *
     * @param string $key     Clave del parámetro
     * @param mixed $default Valor por defecto si no existe
     * @return mixed
     *
     * @example
     * $action = $request->get('action'); // busca en POST primero, luego GET
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * ✅ Obtener IP del Cliente (con soporte para proxies)
     *
     * Soporta: Cloudflare, Nginx reverse proxy, X-Forwarded-For
     *
     * @return string IP detectada (respetando privacidad)
     *
     * @example
     * $ip = $request->getClientIp(); // "190.15.24.12" o "127.0.0.1"
     */
    public function getClientIp(): string
    {
       return RequestSecurity::getClientIp($this->server);
    }

    /**
     * ✅ Obtener User Agent
     *
     * @return string User Agent string o empty string
     */
    public function getUserAgent(): string
    {
        return $this->getHeader('User-Agent') ??
            $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * ✅ Obtener Todos los Headers (con Fallback)
     *
     * Compatible con getallheaders() (Apache) y fallback para CLI/FPM
     *
     * @return array<string, string> Headers como array asociativo
     */
    private static function getAllHeaders(): array
    {
        // Caso 1: getallheaders() disponible (Apache,某些CGI setups)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                return $headers;
            }
        }

        // Caso 2: Fallback manual (CLI, PHP-FPM, etc.)
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                // Convert "HTTP_USER_AGENT" → "User-Agent"
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            } elseif ($name === 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($name === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }

        return $headers;
    }

    /**
     * ✅ Obtener URL Base (protocol + host)
     *
     * @return string URL base como "https://example.com"
     */
    public function getBaseUrl(): string
    {
        $protocol = $this->isSecure() ? 'https://' : 'http://';
        $host = $this->getHeader('Host') ?? $this->server['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host;
    }

    /**
     * ✅ Verificar si es HTTPS
     *
     * @return bool true si SSL/TLS detectado
     */
    public function isSecure(): bool
    {
        return RequestSecurity::isHttps($this->server);
    }

    /**
     * ✅ Obtener Puerto del Servidor
     *
     * @return int Número de puerto (80, 443, 8080, etc.)
     */
    public function getPort(): int
    {
        $port = (int)($this->server['SERVER_PORT'] ?? 80);

        // Ajustar para puertos estándar de HTTPS
        if ($this->isSecure() && $port === 80) {
            return 443;
        }

        return $port;
    }
}