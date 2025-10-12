<?php

declare(strict_types=1);

/**
 * RequestSecurity - Security Utils for HTTP Requests
 *
 * ============================================================================
 * 🎯 PROPÓSITO: Utilidades de seguridad para peticiones web
 * ============================================================================
 *
 * Esta clase provee funciones estáticas para:
 * ✅ Detección segura de IP del cliente
 * ✅ Verificación de HTTPS (con proxy support)
 * ✅ Validación de trusted proxies (CIDR)
 * ✅ Soporte para Cloudflare, Nginx, Apache
 *
 * ============================================================================
 * 🚀 INTEGRACIÓN CON REQUEST OBJECT
 * ============================================================================
 *
 *应该在Request::getClientIp()中使用:
 * return RequestSecurity::getClientIp($this->server);
 *
 * 应该在Request::isSecure()中使用:
 * return RequestSecurity::isHttps($this->server);
 *
 * ============================================================================
 * ⚡ CARACTERÍSTICAS DE SEGURIDAD
 * ============================================================================
 *
 * ✅ Proxy chain traversal analysis
 * ✅ CIDR subnet matching (IPv4 + IPv6)
 * ✅ Cloudflare special headers
 * ✅ Trusted proxies via environment
 * ✅ IP validation with filter_var
 * ✅ Backward compatibility membranes
 *
 * @package Enoc\Login\Core
 * @author Enoc (Security Utils)
 * @version 1.0.0
 */
namespace Enoc\Login\Core;

/**
 * RequestSecurity - Final class for HTTP security utilities
 *
 * Provides static methods for secure HTTP request processing including
 * IP detection, HTTPS verification, and proxy handling.
 */
final class RequestSecurity
{
    /**
     * Headers que pueden contener la IP original del cliente
     *
     * Orden importante: Cloudflare primero, luego genéricos
     */
    private const FORWARDED_IP_HEADERS = [
        'HTTP_CF_CONNECTING_IP',    // Cloudflare
        'HTTP_X_FORWARDED_FOR',     // Nginx/Apache reverse proxy
        'HTTP_X_REAL_IP',           // Nginx real_ip module
    ];

    /**
     * Cache de trusted proxies configurados
     *
     * @var list<string>|null
     */
    private static ?array $trustedProxies = null;

    /**
     * Constructor privado - clase helper estática
     */
    private function __construct()
    {
        // Static helper. No instances allowed.
    }

    /**
     * ✅ Obtener IP real del cliente (best-effort)
     *
     * Algoritmo de detección:
     * 1. Si REMOTE_ADDR no es trusted proxy → usar REMOTE_ADDR directamente
     * 2. Si trusted proxy → analizar chain de headers forwards
     * 3. Devolver primera IP que NO sea trusted proxy
     * 4. Fallback a REMOTE_ADDR
     *
     * @param array $server $_SERVER superglobal
     * @return string IP detectada (never null)
     *
     * @example
     * $ip = RequestSecurity::getClientIp($_SERVER);
     * // "190.15.24.12" (real client IP behind Cloudflare)
     */
    public static function getClientIp(array $server): string
    {
        $remoteAddr = self::extractIp($server['REMOTE_ADDR'] ?? null);
        if ($remoteAddr === null) {
            return '0.0.0.0';
        }

        // Si no es trusted proxy, usar directo
        if (!self::isTrustedProxy($remoteAddr)) {
            return $remoteAddr;
        }

        // Analizar cadena de forwards
        foreach (self::FORWARDED_IP_HEADERS as $header) {
            if (empty($server[$header])) {
                continue;
            }

            $candidates = array_map('trim', explode(',', (string) $server[$header]));
            foreach ($candidates as $candidate) {
                $candidateIp = self::extractIp($candidate);
                if ($candidateIp === null) {
                    continue;
                }

                // Saltar otros proxies confiables en la cadena
                if (self::isTrustedProxy($candidateIp)) {
                    continue;
                }

                return $candidateIp;
            }
        }

        return $remoteAddr;
    }

    /**
     * ✅ Determinar si la petición original fue HTTPS
     *
     * Verificación exhaustiva con soporte para:
     * - Direct HTTPS detection
     * - Forwarded protocol headers
     * - Cloudflare scheme detection
     * - Trusted proxy verification
     *
     * @param array $server $_SERVER superglobal
     * @return bool true si HTTPS detectado
     *
     * @example
     * $isSecure = RequestSecurity::isHttps($_SERVER);
     * // true detrás de Cloudflare/Nginx
     */
    public static function isHttps(array $server): bool
    {
        // 1️⃣ Detección directa
        if (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') {
            return true;
        }

        if (isset($server['SERVER_PORT']) && (int) $server['SERVER_PORT'] === 443) {
            return true;
        }

        // 2️⃣ Verificar trusted proxy
        $remoteAddr = self::extractIp($server['REMOTE_ADDR'] ?? null);
        if ($remoteAddr === null || !self::isTrustedProxy($remoteAddr)) {
            return false;
        }

        // 3️⃣ Forwarded protocol headers
        if (!empty($server['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $server['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }

        if (!empty($server['HTTP_X_FORWARDED_SSL'])
            && strtolower((string) $server['HTTP_X_FORWARDED_SSL']) === 'on') {
            return true;
        }

        if (!empty($server['HTTP_X_FORWARDED_SCHEME'])
            && strtolower((string) $server['HTTP_X_FORWARDED_SCHEME']) === 'https') {
            return true;
        }

        // 4️⃣ Cloudflare special handling
        if (!empty($server['HTTP_CF_VISITOR'])) {
            $decoded = json_decode((string) $server['HTTP_CF_VISITOR'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (($decoded['scheme'] ?? null) === 'https') {
                    return true;
                }
            } elseif (preg_match('/"?scheme"?\s*[:=]\s*"?https"?/i', (string) $server['HTTP_CF_VISITOR'])) {
                return true;
            }
        }

        // 5️⃣ Forwarded port verification
        if (!empty($server['HTTP_X_FORWARDED_PORT']) && (int) $server['HTTP_X_FORWARDED_PORT'] === 443) {
            return true;
        }

        return false;
    }

    /**
     * ✅ Extraer y validar IP desde mixed input
     *
     * @param mixed $value Input a validar
     * @return string|null IP validada o null
     */
    private static function extractIp(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return filter_var($trimmed, FILTER_VALIDATE_IP) ? $trimmed : null;
    }

    /**
     * ✅ Cargar trusted proxies desde configuración
     *
     * Formatos soportados:
     * - "192.168.1.1,10.0.0.0/8,172.16.0.0/12,*"
     * - Via $_ENV['TRUSTED_PROXIES']
     *
     * @return list<string> Lista de trusted proxies/IP ranges
     */
    private static function loadTrustedProxies(): array
    {
        if (self::$trustedProxies !== null) {
            return self::$trustedProxies;
        }

        $configured = [];
        $envValue = $_ENV['TRUSTED_PROXIES'] ?? '';
        if (is_string($envValue) && $envValue !== '') {
            $configured = array_filter(
                array_map('trim', explode(',', $envValue)),
                static fn($value) => $value !== ''
            );
        }

        self::$trustedProxies = array_values($configured);

        return self::$trustedProxies;
    }

    /**
     * ✅ Verificar si IP es trusted proxy
     *
     * Soporta:
     * - IP individual: "192.168.1.1"
     * - CIDR range: "10.0.0.0/8"
     * - Wildcard: "*" (confiar en todo - no recommended para producción)
     *
     * @param string $ip IP a verificar
     * @return bool true si es trusted
     */
    private static function isTrustedProxy(string $ip): bool
    {
        foreach (self::loadTrustedProxies() as $trusted) {
            if ($trusted === '*') {
                return true;
            }

            if (str_contains($trusted, '/')) {
                if (self::cidrMatch($ip, $trusted)) {
                    return true;
                }
                continue;
            }

            if ($ip === $trusted) {
                return true;
            }
        }

        return false;
    }

    /**
     * ✅ Verificar match CIDR (IPv4 + IPv6)
     *
     * @param string $ip IP a verificar
     * @param string $cidr Range en formato CIDR
     * @return bool true si IP está en range
     *
     * @example
     * RequestSecurity::cidrMatch('192.168.1.100', '192.168.1.0/24'); // true
     * RequestSecurity::cidrMatch('::1', '::1/128'); // true
     */
    private static function cidrMatch(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);
        if (!self::extractIp($subnet)) {
            return false;
        }

        $maskBits = (int) $mask;

        // IPv6 handling
        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipBinary = @inet_pton($ip);
            $subnetBinary = @inet_pton($subnet);
            if ($ipBinary === false || $subnetBinary === false) {
                return false;
            }

            $bytes = intdiv($maskBits, 8);
            $remainder = $maskBits % 8;

            if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) {
                return false;
            }

            if ($remainder === 0) {
                return true;
            }

            $maskByte = (~0 << (8 - $remainder)) & 0xFF;
            return (ord($ipBinary[$bytes]) & $maskByte) === (ord($subnetBinary[$bytes]) & $maskByte);
        }

        // IPv4 handling
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        if ($maskBits < 0 || $maskBits > 32) {
            return false;
        }

        $maskLong = -1 << (32 - $maskBits);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}