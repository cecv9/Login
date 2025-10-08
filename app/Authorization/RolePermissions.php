<?php
declare(strict_types=1);

namespace Enoc\Login\Authorization;

use Enoc\Login\Enums\Permission;
use Enoc\Login\Enums\UserRole;

/**
 * 🗺️ PROPÓSITO: Mapear cada ROL con sus PERMISOS
 *
 * ANALOGÍA: Es como un directorio del edificio
 * "Director → puede entrar a TODAS las oficinas"
 * "Contador → solo puede entrar a Contabilidad"
 *
 * ¿POR QUÉ ES IMPORTANTE?
 * - Single Source of Truth: UN solo lugar define los permisos
 * - Fácil auditoría: "¿Qué puede hacer un bodeguero?" → Miras aquí
 * - Fácil cambios: Si quieres que bodeguero vea facturas, cambias 1 línea
 */
final class RolePermissions
{
    /**
     * 📊 MATRIZ DE PERMISOS
     *
     * ESTRUCTURA:
     * [
     *   'nombre_del_rol' => [
     *      'permiso_1',
     *      'permiso_2',
     *      ...
     *   ]
     * ]
     *
     * ¿POR QUÉ ARRAY DE ARRAYS?
     * Porque cada rol puede tener MÚLTIPLES permisos
     *
     * ¿POR QUÉ 'private const'?
     * - private: Solo esta clase puede verlo (encapsulación)
     * - const: No se puede modificar en tiempo de ejecución (seguridad)
     */
    private const ROLE_PERMISSIONS = [
        // ══════════════════════════════════════════
        // 👑 ADMIN - El Dios del sistema
        // ══════════════════════════════════════════
        UserRole::ADMIN => [
            // Gestión de usuarios
            Permission::VIEW_USERS,
            Permission::CREATE_USERS,
            Permission::EDIT_USERS,
            Permission::DELETE_USERS,

            // Meta-permisos (puede dar permisos a otros)
            Permission::ASSIGN_BASIC_ROLES,
            Permission::ASSIGN_ADMIN_ROLE,  // ⚠️ Solo admin puede crear admins

            // Acceso a TODO
            Permission::CREATE_INVOICES,
            Permission::VIEW_INVOICES,
            Permission::MANAGE_INVENTORY,
            Permission::MANAGE_SETTLEMENTS,
            Permission::ACCESS_ADMIN_PANEL,
        ],

        // ══════════════════════════════════════════
        // 📝 FACTURADOR - Solo facturas
        // ══════════════════════════════════════════
        UserRole::FACTURADOR => [
            Permission::CREATE_INVOICES,
            Permission::VIEW_INVOICES,
            // ⚠️ NO puede ver usuarios, NO puede entrar al panel admin
        ],

        // ══════════════════════════════════════════
        // 📦 BODEGUERO - Solo inventario
        // ══════════════════════════════════════════
        UserRole::BODEGUERO => [
            Permission::MANAGE_INVENTORY,
            // ⚠️ NO puede ver facturas, NO puede ver usuarios
        ],

        // ══════════════════════════════════════════
        // 💰 LIQUIDADOR - Ve facturas y hace liquidaciones
        // ══════════════════════════════════════════
        UserRole::LIQUIDADOR => [
            Permission::MANAGE_SETTLEMENTS,
            Permission::VIEW_INVOICES,  // Necesita ver facturas para liquidar
        ],

        // ══════════════════════════════════════════
        // 🛒 VENDEDOR_SISTEMA - Vende y ve sus facturas
        // ══════════════════════════════════════════
        UserRole::VENDEDOR_SISTEMA => [
            Permission::CREATE_INVOICES,
            Permission::VIEW_INVOICES,
        ],

        // ══════════════════════════════════════════
        // 👤 USER - Usuario básico sin permisos
        // ══════════════════════════════════════════
        UserRole::USER => [
            // Array vacío = sin permisos especiales
            // Solo puede ver su propio perfil (definido en otra parte)
        ],
    ];

    /**
     * 📋 MÉTODO: Obtiene todos los permisos de un rol
     *
     * ¿POR QUÉ EXISTE?
     * Para consultar "¿Qué puede hacer este rol?"
     *
     * EJEMPLO DE USO:
     * $permisos = RolePermissions::getPermissions('facturador');
     * // Retorna: ['create_invoices', 'view_invoices']
     *
     * @param string $role El rol a consultar
     * @return array Lista de permisos
     */
    public static function getPermissions(string $role): array
    {
        // OPERADOR '??':
        // Si self::ROLE_PERMISSIONS[$role] existe → retorna eso
        // Si NO existe → retorna [] (array vacío)
        //
        // ¿POR QUÉ? Para evitar errores si pasas un rol inválido
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }

    /**
     * ✅ MÉTODO: Verifica si un rol tiene un permiso específico
     *
     * ¿POR QUÉ EXISTE?
     * Para preguntar "¿Este rol PUEDE hacer esta acción?"
     *
     * EJEMPLO DE USO:
     * if (RolePermissions::hasPermission('facturador', Permission::CREATE_INVOICES)) {
     *     // Sí puede crear facturas
     * }
     *
     * @param string $role El rol a verificar
     * @param string $permission El permiso a verificar
     * @return bool True si tiene el permiso, False si no
     */
    public static function hasPermission(string $role, string $permission): bool
    {
        // PASO 1: Obtener todos los permisos del rol
        $permissions = self::getPermissions($role);

        // PASO 2: Buscar si el permiso específico está en la lista
        // in_array() retorna true/false
        // El tercer parámetro 'true' hace comparación estricta
        return in_array($permission, $permissions, true);
    }

    /**
     * 🎯 MÉTODO: Obtiene roles que un usuario puede asignar
     *
     * ¿POR QUÉ EXISTE?
     * Para la vista del formulario: "¿Qué opciones de rol mostrar?"
     *
     * REGLAS DE NEGOCIO:
     * - Admin puede asignar CUALQUIER rol (es el jefe)
     * - Otros roles NO pueden crear usuarios (no tienen ese poder)
     *
     * EJEMPLO DE USO:
     * $rolesDisponibles = RolePermissions::getAssignableRoles('admin');
     * // Retorna: ['admin', 'facturador', 'bodeguero', ...]
     *
     * @param string $actorRole El rol del usuario que quiere asignar
     * @return array Lista de roles que puede asignar
     */
    public static function getAssignableRoles(string $actorRole): array
    {
        // Si es ADMIN
        if ($actorRole === UserRole::ADMIN) {
            return UserRole::all();  // Puede asignar TODOS los roles
        }

        // Si NO es admin
        return [];  // No puede asignar ningún rol

        // 💡 FUTURO: Aquí podrías agregar lógica más compleja
        // Por ejemplo: "Jefe de área puede crear usuarios de su área"
    }

    /**
     * ✅ MÉTODO: Verifica si un rol puede asignar otro rol específico
     *
     * ¿POR QUÉ EXISTE?
     * Para validar: "¿Este usuario PUEDE hacer a alguien más 'admin'?"
     *
     * REGLA ESPECIAL:
     * Solo ADMIN puede crear otros ADMINS
     * (Es como: solo el director puede contratar otro director)
     *
     * @param string $actorRole El rol del que asigna
     * @param string $targetRole El rol que se quiere asignar
     * @return bool True si puede asignarlo, False si no
     */
    public static function canAssignRole(string $actorRole, string $targetRole): bool
    {
        // REGLA 1: Solo admin puede crear admin
        if ($targetRole === UserRole::ADMIN) {
            return $actorRole === UserRole::ADMIN;
        }

        // REGLA 2: Para otros roles, verificar si está en la lista
        $assignable = self::getAssignableRoles($actorRole);
        return in_array($targetRole, $assignable, true);
    }
}