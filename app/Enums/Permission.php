<?php
declare(strict_types=1);

namespace Enoc\Login\Enums;

/**
 * 🎯 PROPÓSITO: Definir TODAS las acciones que existen en el sistema
 *
 * ANALOGÍA: Es como el manual de un edificio que lista TODAS las puertas
 * y qué se puede hacer en cada habitación
 */
final class Permission
{
    // ══════════════════════════════════════════
    // PERMISOS DE USUARIOS
    // ══════════════════════════════════════════

    /**
     * 👁️ Ver lista de usuarios
     * EJEMPLO: Entrar a /admin/users y ver la tabla
     */
    public const VIEW_USERS = 'view_users';

    /**
     * ➕ Crear nuevos usuarios
     * EJEMPLO: Botón "Crear usuario" en /admin/users/create
     */
    public const CREATE_USERS = 'create_users';

    /**
     * ✏️ Editar usuarios existentes
     * EJEMPLO: Botón "Editar" en /admin/users/edit
     */
    public const EDIT_USERS = 'edit_users';

    /**
     * 🗑️ Eliminar usuarios
     * EJEMPLO: Botón "Eliminar" en la tabla de usuarios
     */
    public const DELETE_USERS = 'delete_users';

    // ══════════════════════════════════════════
    // PERMISOS DE ROLES (meta-permisos)
    // ══════════════════════════════════════════

    /**
     * 👤 Asignar roles básicos (facturador, bodeguero, etc.)
     * EJEMPLO: En el form de crear usuario, puede seleccionar roles normales
     */
    public const ASSIGN_BASIC_ROLES = 'assign_basic_roles';

    /**
     * 👑 Asignar rol de ADMIN (súper permiso)
     * EJEMPLO: Solo otro admin puede hacer que alguien sea admin
     *
     * ¿POR QUÉ SEPARARLO? Porque es MUY peligroso
     * Es como dar la llave maestra del edificio
     */
    public const ASSIGN_ADMIN_ROLE = 'assign_admin_role';

    // ══════════════════════════════════════════
    // PERMISOS DE FACTURACIÓN
    // ══════════════════════════════════════════

    /**
     * 📝 Crear facturas
     * EJEMPLO: Facturador puede crear nueva factura
     */
    public const CREATE_INVOICES = 'create_invoices';

    /**
     * 👁️ Ver facturas
     * EJEMPLO: Ver lista de facturas y sus detalles
     */
    public const VIEW_INVOICES = 'view_invoices';

    // ══════════════════════════════════════════
    // PERMISOS DE BODEGA
    // ══════════════════════════════════════════

    /**
     * 📦 Gestionar inventario
     * EJEMPLO: Agregar/quitar productos, actualizar stock
     */
    public const MANAGE_INVENTORY = 'manage_inventory';

    // ══════════════════════════════════════════
    // PERMISOS DE LIQUIDACIONES
    // ══════════════════════════════════════════

    /**
     * 💰 Gestionar liquidaciones
     * EJEMPLO: Crear liquidaciones de vendedores
     */
    public const MANAGE_SETTLEMENTS = 'manage_settlements';

    // ══════════════════════════════════════════
    // PERMISOS DE SISTEMA
    // ══════════════════════════════════════════

    /**
     * 🏢 Acceder al panel de administración
     * EJEMPLO: Poder entrar a /admin
     */
    public const ACCESS_ADMIN_PANEL = 'access_admin_panel';

    /**
     * 📋 MÉTODO: Retorna todos los permisos
     *
     * ¿POR QUÉ? Para poder iterar sobre todos los permisos
     * Ejemplo de uso: Generar un reporte de "¿qué permisos existen?"
     *
     * @return array Lista de todos los permisos
     */
    public static function all(): array
    {
        // Retornamos un array con TODAS las constantes definidas arriba
        return [
            self::VIEW_USERS,
            self::CREATE_USERS,
            self::EDIT_USERS,
            self::DELETE_USERS,
            self::ASSIGN_BASIC_ROLES,
            self::ASSIGN_ADMIN_ROLE,
            self::CREATE_INVOICES,
            self::VIEW_INVOICES,
            self::MANAGE_INVENTORY,
            self::MANAGE_SETTLEMENTS,
            self::ACCESS_ADMIN_PANEL,
        ];
    }

    /**
     * ✅ MÉTODO: Verifica si un permiso existe
     *
     * ¿POR QUÉ? Protección contra typos
     * Si escribes 'create_userss' (con doble 's'), esto detecta el error
     *
     * @param string $permission El permiso a verificar
     * @return bool True si existe, False si no
     */
    public static function exists(string $permission): bool
    {
        // in_array() busca si $permission está en la lista de all()
        // El tercer parámetro 'true' es IMPORTANTE:
        // Hace comparación ESTRICTA (mismo tipo y valor)
        return in_array($permission, self::all(), true);
    }
}