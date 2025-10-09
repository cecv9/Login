<?php

declare(strict_types=1);

namespace Enoc\Login\Core;

final class RequestSecurity
{
    /**
     * Headers that may contain the originating client IP.
     */
    private const FORWARDED_IP_HEADERS = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
    ];

    /** @var list<string>|null */
    private static ?array $trustedProxies = null;

    private function __construct()
    {
        // Static helper. No instances allowed.
    }

    /**
     * Returns the best-effort client IP address.
     *
     * The first non-proxy address in the forwarded chain is used, but only
     * when the immediate sender is a trusted proxy. Otherwise REMOTE_ADDR is
     * returned.
     */
    public static function getClientIp(array $server): string
    {
        $remoteAddr = self::extractIp($server['REMOTE_ADDR'] ?? null);
        if ($remoteAddr === null) {
            return '0.0.0.0';
        }

        if (!self::isTrustedProxy($remoteAddr)) {
            return $remoteAddr;
        }

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

                // Skip other trusted proxies that might appear in the chain.
                if (self::isTrustedProxy($candidateIp)) {
                    continue;
                }

                return $candidateIp;
            }
        }

        return $remoteAddr;
    }

    /**
     * Determines whether the original request was performed using HTTPS.
     */
    public static function isHttps(array $server): bool
    {
        if (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') {
            return true;
        }

        if (isset($server['SERVER_PORT']) && (int) $server['SERVER_PORT'] === 443) {
            return true;
        }

        $remoteAddr = self::extractIp($server['REMOTE_ADDR'] ?? null);
        if ($remoteAddr === null || !self::isTrustedProxy($remoteAddr)) {
            return false;
        }

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

        if (!empty($server['HTTP_X_FORWARDED_PORT']) && (int) $server['HTTP_X_FORWARDED_PORT'] === 443) {
            return true;
        }

        return false;
    }

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

    private static function loadTrustedProxies(): array
    {
        if (self::$trustedProxies !== null) {
            return self::$trustedProxies;
        }

        $configured = [];
        $envValue = $_ENV['TRUSTED_PROXIES'] ?? '';
        if (is_string($envValue) && $envValue !== '') {
            $configured = array_filter(array_map('trim', explode(',', $envValue)), static fn($value) => $value !== '');
        }

        self::$trustedProxies = array_values($configured);

        return self::$trustedProxies;
    }

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