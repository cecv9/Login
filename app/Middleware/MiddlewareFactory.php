<?php
declare(strict_types=1);

namespace Enoc\Login\Middleware;

use Enoc\Login\Enums\UserRole;

/**
 * Factory para crear instancias de middlewares
 * CAMBIO: Ahora soporta dinámicamente TODOS los roles
 */
final class MiddlewareFactory
{
    /**
     * Crea un middleware basado en su identificador
     *
     * Ejemplos de uso:
     * - make('auth') → Middleware de autenticación
     * - make('role:admin') → Middleware para rol admin
     * - make('role:facturador') → Middleware para rol facturador
     * - make('role:bodeguero') → Middleware para rol bodeguero
     *
     * @param string $key Identificador del middleware
     * @return object Instancia del middleware
     * @throws \InvalidArgumentException Si el middleware no existe o el rol es inválido
     */
    public static function make(string $key): object
    {
        // ══════════════════════════════════════════
        // CASO 1: Middleware de autenticación
        // ══════════════════════════════════════════
        if ($key === 'auth') {
            return new Authenticate();
        }

        // ══════════════════════════════════════════
        // CASO 2: Middleware de autorización por rol
        // ══════════════════════════════════════════

        // ✅ CAMBIO: Soporte dinámico para TODOS los roles
        if (str_starts_with($key, 'role:')) {
            // Extraer el rol del string "role:admin" → "admin"
            $role = substr($key, 5);

            // VALIDACIÓN: ¿El rol existe en UserRole?
            if (!UserRole::exists($role)) {
                throw new \InvalidArgumentException(
                    "Rol inválido para middleware: {$role}. " .
                    "Roles válidos: " . implode(', ', UserRole::all())
                );
            }

            // Crear el middleware con el rol validado
            return new Authorize($role);
        }

        // ══════════════════════════════════════════
        // CASO 3: Middleware desconocido
        // ══════════════════════════════════════════
        throw new \InvalidArgumentException(
            "Middleware desconocido: {$key}. " .
            "Usa 'auth' o 'role:NOMBRE_ROL'"
        );
    }
}