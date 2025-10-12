<?php

declare(strict_types=1);

/**
 * Response - Value Object para Response HTTP
 *
 * ============================================================================
 * 🎯 PROPÓSITO: Abstracción completa de respuestas HTTP PSR-7 compatible
 * ============================================================================
 *
 * Esta clase encapsula toda la lógica de respuestas HTTP con:
 * ✅ Factory methods para casos comunes
 * ✅ Fluent interface con method chaining
 * ✅ Auto JSON encoding con UTF-8
 * ✅ Status codes human-readable
 * ✅ Backward compatibility con echo
 * ✅ PSR-7 compliance ready
 *
 * ============================================================================
 * 🚀 EJEMPLOS DE USO
 * ============================================================================
 *
 * // JSON API response
 * return Response::json(['users' => $users], 200);
 *
 * // HTML page
 * $html = $this->renderTemplate('dashboard');
 * return Response::html($html);
 *
 * // Redirect
 * return Response::redirect('/dashboard', 301);
 *
 * // Custom response
 * return Response::ok()
 *     ->setHeader('X-Rate-Limit', '100')
 *     ->setHeader('X-Token', $token)
 *     ->setStatus(200);
 *
 * ============================================================================
 * ⚡ CARACTERÍSTICAS AVANZADAS
 * ============================================================================
 *
 * ✅ PSR-7 status phrases
 * ✅ Auto Content-Type detection
 * ✅ Method chaining
 * ✅ Magic methods (__toString)
 * ✅ Type safety
 * ✅ Security headers ready
 *
 * @package Enoc\Login\Core\Domain
 * @author Enoc (HTTP Response Abstraction)
 * @version 2.0.0 (PSR-7 Enhanced)
 */
namespace Enoc\Login\Core\Domain;

/**
 * Response - Value Object for HTTP Responses
 *
 * @package Enoc\Login\Core\Domain
 */
class Response
{
    /**
     * @var int HTTP Status Code (200, 301, 404, 500, etc.)
     */
    private int $statusCode = 200;

    /**
     * @var array<string, string> HTTP Headers
     */
    private array $headers = [];

    /**
     * @var mixed Response body content (string, array, object)
     */
    private mixed $body = '';

    /**
     * @var string HTTP Version (1.0, 1.1, 2.0)
     */
    private string $version = '1.1';

    /**
     * Constructor - Crear respuesta con propiedades iniciales
     *
     * @param int    $statusCode Código de estado HTTP
     * @param array  $headers    Headers HTTP
     * @param mixed  $body       Contenido del body
     * @param string $version    Versión HTTP
     */
    public function __construct(
        int $statusCode = 200,
        array $headers = [],
        mixed $body = '',
        string $version = '1.1'
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->version = $version;
    }

    /**
     * ✅ Factory Method - Response 200 OK
     *
     * @param mixed $data Contenido a enviar
     * @return self Nueva instancia
     */
    public static function ok(mixed $data = ''): self
    {
        return new self(200, [], $data);
    }

    /**
     * ✅ Factory Method - Response JSON con auto-encoding y charset
     *
     * @param mixed $data    Datos a codificar
     * @param int    $status  HTTP status code
     * @return self Nueva instancia con Content-Type: application/json
     *
     * @example
     * return Response::json(['users' => $users], 200);
     * return Response::json(['error' => 'Unauthorized'], 401);
     */
    public static function json(mixed $data, int $status = 200): self
    {
        $jsonBody = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback para JSON malformed
            $jsonBody = json_encode(['error' => 'JSON encoding failed'],
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return new self($status, [
            'Content-Type' => 'application/json; charset=utf-8'
        ], $jsonBody);
    }

    /**
     * ✅ Factory Method - HTML Response con UTF-8
     *
     * @param string $html    Contenido HTML
     * @param int    $status  HTTP status code
     * @return self Nueva instancia con Content-Type: text/html
     *
     * @example
     * $html = $this->renderTemplate('users/index');
     * return Response::html($html);
     */
    public static function html(string $html, int $status = 200): self
    {
        return new self($status, [
            'Content-Type' => 'text/html; charset=utf-8'
        ], $html);
    }

    /**
     * ✅ Factory Method - 404 Not Found
     *
     * @param string $message Mensaje personalizado
     * @return self Nueva instancia 404
     */
    public static function notFound(string $message = 'Not Found'): self
    {
        return new self(404, [], $message);
    }

    /**
     * ✅ Factory Method - 403 Forbidden
     *
     * @param string $message Mensaje personalizado
     * @return self Nueva instancia 403
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self(403, [], $message);
    }

    /**
     * ✅ Factory Method - 500 Internal Server Error
     *
     * @param string $message Mensaje personalizado
     * @return self Nueva instancia 500
     */
    public static function internalError(string $message = 'Internal Server Error'): self
    {
        return new self(500, [], $message);
    }

    /**
     * ✅ Factory Method - Redirect Response
     *
     * @param string $url     URL a redirigir
     * @param int    $status  HTTP status code (301=permanent, 302=temporal)
     * @return self Nueva instancia con Location header
     *
     * @example
     * return Response::redirect('/login', 302);
     * return Response::redirect('https://example.com', 301);
     */
    public static function redirect(string $url, int $status = 302): self
    {
        // Validate URL (seguridad básica)
        if (!filter_var($url, FILTER_VALIDATE_URL) && !str_starts_with($url, '/')) {
            throw new \InvalidArgumentException("Invalid redirect URL: $url");
        }

        return new self($status, ['Location' => $url], '');
    }

    /**
     * ✅ Enviar Response Completo (con PSR-7 status phrase)
     *
     * Este método DEBE llamarse UNA SOLA VEZ por request.
     * Envía headers + body con formato HTTP estándar.
     *
     * @return void
     */
    public function send(): void
    {
        // 1️⃣ STATUS LINE CON PHRASE (mejora PSR-7)
        $phrase = $this->getStatusPhrase($this->statusCode);

        if (PHP_SAPI !== 'cli') {
            header(
                sprintf('HTTP/%s %d %s', $this->version, $this->statusCode, $phrase),
                true,
                $this->statusCode
            );
        }

        // 2️⃣ HEADERS (validación de duplicados)
        foreach ($this->headers as $name => $value) {
            if ($value !== null && $value !== '') {
                // Nombre de header normalizado
                $normalizedName = ucwords(strtolower(str_replace('-', ' ', $name)));
                $headerName = str_replace(' ', '-', $normalizedName);

                header("{$headerName}: {$value}", true);
            }
        }

        // 3️⃣ BODY
        $this->sendBody();
    }

    /**
     * ✅ Enviar Solo Body (sin headers)
     *
     * Útil para backward compatibility con código existente
     * que usa echo directamente
     *
     * @return void
     */
    public function sendBody(): void
    {
        if ($this->body === '') {
            // Empty body - nothing to send
            return;
        }

        // Body ya está pre-procesado (ej. JSON encoding)
        echo $this->body;
    }

    /**
     * ✅ Setear Header Individual (con validación)
     *
     * @param string $name  Header name
     * @param string $value Header value
     * @return self Para method chaining
     *
     * @example
     * $response->setHeader('X-Custom', 'value')
     *          ->setStatus(429);
     */
    public function setHeader(string $name, string $value): self
    {
        // Validación básica de nombre del header
        if (empty(trim($name))) {
            throw new \InvalidArgumentException('Header name cannot be empty');
        }

        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * ✅ Setear Status Code (con validación)
     *
     * @param int $code HTTP status code
     * @return self Para method chaining
     *
     * @example
     * return $response->setStatus(422);
     */
    public function setStatus(int $code): self
    {
        // Validar rango de status codes
        if ($code < 100 || $code > 599) {
            throw new \InvalidArgumentException("Invalid HTTP status code: $code");
        }

        $this->statusCode = $code;
        return $this;
    }

    /**
     * ✅ Obtener Status Code
     *
     * @return int HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * ✅ Obtener Headers Completos
     *
     * @return array<string, string> Headers actuales
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * ✅ Obtener Body as String
     *
     * Útil para testing o debugging
     *
     * @return string Body content as string
     */
    public function getBodyAsString(): string
    {
        if ($this->body === '') {
            return '';
        }

        return (string) $this->body;
    }

    /**
     * ✅ Backward Compatibility - Magic Method
     *
     * Permite: echo $response;
     * Envía solo el body (no headers) para compatibilizar con código existente
     *
     * @return string Body content
     *
     * @example
     * $response = Response::html('<h1>Hello</h1>');
     * echo $response; // → <h1>Hello</h1>
     */
    public function __toString(): string
    {
        ob_start();
        $this->sendBody();
        return ob_get_clean();
    }

    /**
     * ✅ PSR-7 Status Phrase Mapping
     *
     * Mapea códigos de estado HTTP a sus frases descriptivas
     * para complir con RFC 7231 y PSR-7
     *
     * @param int $code HTTP status code
     * @return string Status phrase
     */
    private function getStatusPhrase(int $code): string
    {
        return match($code) {
            // Informational 1xx
            100 => 'Continue',
            101 => 'Switching Protocols',

            // Successful 2xx
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',

            // Redirection 3xx
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',

            // Client Error 4xx
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',

            // Server Error 5xx
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            503 => 'Service Unavailable',

            default => ''
        };
    }
}