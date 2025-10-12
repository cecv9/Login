<?php
declare(strict_types=1);

namespace Enoc\Login\Enums;

final class UserRole
{
    public const ADMIN = 'admin';
    public const FACTURADOR = 'facturador';
    public const BODEGUERO = 'bodeguero';
    public const LIQUIDADOR = 'liquidador';
    public const VENDEDOR_SISTEMA = 'vendedor_sistema';
    public const USER = 'user';

    /**
     * Todos los roles disponibles en el sistema
     */
    public static function all(): array


    {
        return [
            self::ADMIN,
            self::FACTURADOR,
            self::BODEGUERO,
            self::LIQUIDADOR,
            self::VENDEDOR_SISTEMA,
            self::USER,
        ];
    }

    /**
     * Roles en formato string para validación
     * Retorna: "admin,facturador,bodeguero,..."
     */
    public static function forValidation(): string
    {
        return implode(',', self::all());
    }

    /**
     * Roles en formato para SQL ENUM
     * Retorna: "'admin','facturador','bodeguero',..."
     */
    public static function forSQL(): string
    {
        return "'" . implode("','", self::all()) . "'";
    }

    /**
     * Verifica si un rol existe
     */
    public static function exists(string $role): bool
    {
        return in_array($role, self::all(), true);
    }

    /**
     * Roles con descripción para UI
     */
    public static function withLabels(): array
    {
        return [
            self::ADMIN => 'Administrador',
            self::FACTURADOR => 'Facturador',
            self::BODEGUERO => 'Bodeguero',
            self::LIQUIDADOR => 'Liquidador',
            self::VENDEDOR_SISTEMA => 'Vendedor con acceso al sistema',
            self::USER => 'Usuario básico',
        ];
    }
}