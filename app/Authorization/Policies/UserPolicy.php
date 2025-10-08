<?php
declare(strict_types=1);

namespace Enoc\Login\Authorization\Policies;

use Enoc\Login\Authorization\RolePermissions;
use Enoc\Login\Enums\Permission;
use Enoc\Login\Enums\UserRole;
use Enoc\Login\models\Users;

/**
 * 🛡️ PROPÓSITO: Definir TODAS las reglas de autorización para usuarios
 *
 * ANALOGÍA: Es como el manual de seguridad de RRHH
 * "Reglas para contratar, despedir, cambiar salarios, etc."
 *
 * PRINCIPIO SOLID: Single Responsibility
 * Esta clase SOLO se encarga de autorización de usuarios
 * NO hace queries a BD, NO envía emails, SOLO decide permisos
 *
 * ¿POR QUÉ SEPARAR ESTO DEL SERVICE?
 * - Service: Lógica de negocio (crear usuario, validar email único)
 * - Policy: Lógica de autorización (¿PUEDE crear usuario?)
 *
 * Separar responsabilidades = código más limpio y testeable
 */
final class UserPolicy implements PolicyInterface
{
    /**
     * 👁️ MÉTODO: ¿Puede VER usuarios?
     *
     * REGLA: Solo quien tenga el permiso VIEW_USERS
     *
     * FLUJO:
     * 1. Verificar que hay un actor (usuario autenticado)
     * 2. Consultar si su rol tiene el permiso VIEW_USERS
     *
     * @param Users|null $actor Usuario que intenta ver
     * @param mixed $resource No usado aquí (podría ser para ver UN usuario específico)
     * @return bool True si puede ver, False si no
     */
    public function view(?Users $actor, mixed $resource): bool
    {
        // VALIDACIÓN 1: ¿Hay alguien intentando?
        if (!$actor) {
            // Si $actor es null = usuario no autenticado
            // Usuarios anónimos NO pueden ver la lista de usuarios
            return false;
        }

        // VALIDACIÓN 2: ¿Su rol tiene el permiso?
        return RolePermissions::hasPermission(
            $actor->getRole(),           // Rol del actor (ej: 'admin')
            Permission::VIEW_USERS       // Permiso que necesita
        );

        // EJEMPLO DE FLUJO:
        // Actor es Admin → RolePermissions dice: "Admin SÍ tiene VIEW_USERS" → true
        // Actor es Facturador → RolePermissions dice: "Facturador NO tiene VIEW_USERS" → false
    }

    /**
     * ➕ MÉTODO: ¿Puede CREAR usuarios?
     *
     * REGLA: Solo quien tenga el permiso CREATE_USERS
     *
     * @param Users|null $actor Usuario que intenta crear
     * @return bool True si puede crear, False si no
     */
    public function create(?Users $actor): bool
    {
        if (!$actor) {
            return false;
        }

        return RolePermissions::hasPermission(
            $actor->getRole(),
            Permission::CREATE_USERS
        );
    }

    /**
     * ✏️ MÉTODO: ¿Puede ACTUALIZAR usuarios?
     *
     * REGLAS COMPLEJAS (múltiples validaciones):
     * 1. Debe tener el permiso EDIT_USERS
     * 2. Nadie (excepto admin) puede editar a un admin
     * 3. Admin puede editarse a sí mismo (nombre/email) pero con cuidado en el rol
     *
     * ¿POR QUÉ ESTAS REGLAS?
     * - Regla 2: Protección contra escalada de privilegios
     * - Regla 3: Admin puede actualizar sus datos, pero no quitarse permisos accidentalmente
     *
     * @param Users|null $actor Usuario que intenta editar
     * @param mixed $resource Usuario a ser editado
     * @return bool True si puede editar, False si no
     */
    public function update(?Users $actor, mixed $resource): bool
    {
        // ══════════════════════════════════════════
        // VALIDACIONES BÁSICAS
        // ══════════════════════════════════════════

        // VALIDACIÓN 1: ¿Hay actor?
        if (!$actor) {
            return false;
        }

        // VALIDACIÓN 2: ¿El recurso es un usuario?
        // Usamos 'instanceof' para verificar el tipo
        if (!($resource instanceof Users)) {
            // Si $resource no es un Users, algo está mal
            return false;
        }

        // Ahora sabemos que $resource ES un Users
        // PHP ahora sabe que $resource tiene métodos como getRole(), getId()

        $actorRole = $actor->getRole();

        // ══════════════════════════════════════════
        // VALIDACIÓN 3: Permiso básico de edición
        // ══════════════════════════════════════════

        if (!RolePermissions::hasPermission($actorRole, Permission::EDIT_USERS)) {
            // Si ni siquiera tiene permiso de editar usuarios, FIN
            return false;
        }

        // ══════════════════════════════════════════
        // VALIDACIÓN 4: Protección de admins
        // ══════════════════════════════════════════

        // REGLA: Nadie puede editar a un admin, excepto otro admin
        if ($resource->getRole() === UserRole::ADMIN && $actorRole !== UserRole::ADMIN) {
            // Ejemplo: Un facturador NO puede editar a un admin
            return false;
        }

        // ══════════════════════════════════════════
        // VALIDACIÓN 5: Auto-edición de admin
        // ══════════════════════════════════════════

        // CASO ESPECIAL: Admin editándose a sí mismo
        if ($actor->getId() === $resource->getId() && $actorRole === UserRole::ADMIN) {
            // ⚠️ CUIDADO: Permitimos que admin se edite
            // Pero en el Service debemos validar que no se quite su propio rol admin
            // (No queremos que el último admin se elimine accidentalmente)
            return true;
        }

        // Si pasó todas las validaciones
        return true;
    }

    /**
     * 🗑️ MÉTODO: ¿Puede ELIMINAR usuarios?
     *
     * REGLAS ESTRICTAS:
     * 1. Debe tener permiso DELETE_USERS
     * 2. NADIE puede eliminar admins (ni siquiera otro admin)
     * 3. No puede eliminarse a sí mismo
     *
     * ¿POR QUÉ TAN ESTRICTO?
     * - Regla 2: Protección máxima - admins son críticos
     * - Regla 3: Evitar "me elimino accidentalmente"
     *
     * 💡 ALTERNATIVA MÁS FLEXIBLE:
     * Podrías permitir que admin elimine a otro admin,
     * pero dejar al menos 1 admin siempre (validación en Service)
     *
     * @param Users|null $actor Usuario que intenta eliminar
     * @param mixed $resource Usuario a eliminar
     * @return bool True si puede eliminar, False si no
     */
    public function delete(?Users $actor, mixed $resource): bool
    {
        // Validaciones básicas
        if (!$actor || !($resource instanceof Users)) {
            return false;
        }

        $actorRole = $actor->getRole();

        // VALIDACIÓN 1: Permiso básico
        if (!RolePermissions::hasPermission($actorRole, Permission::DELETE_USERS)) {
            return false;
        }

        // VALIDACIÓN 2: NO se pueden eliminar admins
        // Esto es una decisión de negocio - puedes cambiarla
        if ($resource->getRole() === UserRole::ADMIN) {
            // RAZÓN: Los admins son críticos, mejor deshabilitarlos
            // que eliminarlos permanentemente
            return false;
        }

        // VALIDACIÓN 3: No puedes eliminarte a ti mismo
        if ($actor->getId() === $resource->getId()) {
            // RAZÓN: Evitar "me elimino sin querer"
            return false;
        }

        return true;
    }

    /**
     * 🎯 MÉTODO EXTRA: ¿Puede asignar un rol específico?
     *
     * Este método NO está en PolicyInterface porque es específico
     * de la gestión de usuarios
     *
     * REGLA:
     * - Solo admin puede asignar el rol admin
     * - Admin puede asignar cualquier otro rol
     *
     * @param Users|null $actor Usuario que intenta asignar rol
     * @param string $targetRole Rol a asignar
     * @return bool True si puede asignarlo, False si no
     */
    public function assignRole(?Users $actor, string $targetRole): bool
    {
        if (!$actor) {
            return false;
        }

        // Delegamos a RolePermissions que tiene la lógica
        return RolePermissions::canAssignRole(
            $actor->getRole(),
            $targetRole
        );
    }
}