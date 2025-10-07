<?php
declare(strict_types=1);

namespace Enoc\Login\Controllers;

/**
 * Contexto de auditoría para rastrear operaciones
 * Contiene información sobre QUIÉN hace QUÉ y DESDE DÓNDE
 */
final class AuditContext
{
    public function __construct(
        public readonly ?int $userId = null,
        public readonly ?string $username = null,
        public readonly ?string $userEmail = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly array $extra = []
    ) {}

    /**
     * Crea contexto desde la sesión actual
     */
    public static function fromSession(): self
    {
        return new self(
            userId: $_SESSION['user_id'] ?? null,
            username: $_SESSION['user_name'] ?? null,
            userEmail: $_SESSION['user_email'] ?? null,
            ipAddress: self::getClientIp(),
            userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null
        );
    }

    /**
     * Crea contexto para operaciones del sistema (cron, CLI)
     */
    public static function system(array $extra = []): self
    {
        return new self(
            userId: null,
            username: 'SYSTEM',
            userEmail: null,
            ipAddress: null,
            userAgent: php_sapi_name(),
            extra: $extra
        );
    }

    /**
     * Convierte a array para logging
     */
    public function toArray(): array
    {
        return array_filter([
            'actor_user_id' => $this->userId,
            'actor_username' => $this->username,
            'actor_email' => $this->userEmail,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'timestamp' => date('c'),
            ...$this->extra
        ], fn($v) => $v !== null);
    }

    /**
     * Obtiene la IP real del cliente (considera proxies)
     */
    private static function getClientIp(): ?string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Proxies
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Si hay múltiples IPs (proxy chain), tomar la primera
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }

        return null;
    }
}