<?php
declare(strict_types=1);

namespace Enoc\Login\Authorization;

use Enoc\Login\Authorization\Policies\PolicyInterface;
use Enoc\Login\Authorization\Policies\UserPolicy;
use Enoc\Login\models\Users;

/**
 * 🎯 PROPÓSITO: Punto central de autorización
 *
 * ANALOGÍA: Es el Director de Seguridad del edificio
 * - Tiene un walkie-talkie conectado a todos los guardias (Policies)
 * - Cuando alguien pregunta "¿puedo entrar?", él llama al guardia correcto
 *
 * PRINCIPIOS SOLID:
 * - Single Responsibility: Solo gestiona autorización
 * - Open/Closed: Puedes agregar nuevas Policies sin modificar este código
 * - Dependency Inversion: Depende de PolicyInterface, no de clases concretas
 *
 * BENEFICIOS:
 * - Centralización: Un solo punto de entrada para autorización
 * - Consistencia: Todos usan la misma API
 * - Extensibilidad: Fácil agregar nuevas políticas
 */
final class AuthorizationService
{
    /**
     * 📋 Registro de políticas
     *
     * ESTRUCTURA:
     * [
     *   'user' => UserPolicy,
     *   'invoice' => InvoicePolicy,  // futuro
     *   'product' => ProductPolicy,  // futuro
     * ]
     *
     * @var array<string, PolicyInterface>
     */
    private array $policies = [];

    /**
     * 🏗️ CONSTRUCTOR: Registra todas las políticas disponibles
     *
     * ¿POR QUÉ EN EL CONSTRUCTOR?
     * Para que cuando crees el service, ya tenga todo cargado
     *
     * EJEMPLO DE USO:
     * $auth = new AuthorizationService();
     * // Ya tiene todas las policies registradas y listas
     */
    public function __construct()
    {
        // Registramos la política de usuarios
        $this->policies['user'] = new UserPolicy();

        // 💡 FUTURO: Aquí agregarías más políticas
        // $this->policies['invoice'] = new InvoicePolicy();
        // $this->policies['product'] = new ProductPolicy();

        // ¿POR QUÉ STRING KEYS?
        // Para que el código sea legible:
        // $auth->can($user, 'create', 'invoice')  ← Se lee natural
    }

    /**
     * ✅ MÉTODO PRINCIPAL: Verifica autorización
     *
     * PROPÓSITO: API unificada para preguntar "¿puede hacer X?"
     *
     * EJEMPLO DE USO:
     * // ¿Juan puede ver usuarios?
     * if ($auth->can($juan, 'view', 'user')) { ... }
     *
     * // ¿María puede editar a Pedro?
     * if ($auth->can($maria, 'update', 'user', $pedro)) { ... }
     *
     * // ¿Admin puede crear facturas?
     * if ($auth->can($admin, 'create', 'invoice')) { ... }
     *
     * @param Users|null $actor Usuario que intenta la acción
     * @param string $ability Acción: 'view', 'create', 'update', 'delete'
     * @param string $resourceType Tipo de recurso: 'user', 'invoice', 'product'
     * @param mixed $resource Instancia del recurso (opcional para 'create')
     * @return bool True si puede, False si no
     */
    public function can(
        ?Users $actor,
        string $ability,
        string $resourceType,
        mixed $resource = null
    ): bool {
        // ══════════════════════════════════════════
        // PASO 1: Buscar la política correcta
        // ══════════════════════════════════════════

        // Intentamos obtener la policy del array
        // Operador '??' retorna null si no existe
        $policy = $this->policies[$resourceType] ?? null;

        // VALIDACIÓN: ¿Existe una política para este recurso?
        if (!$policy) {
            // Si no hay política definida, DENEGAMOS por defecto
            // Principio: "Fail-secure" (mejor denegar que permitir sin validar)

            // 💡 ALTERNATIVA: Podrías loguear esto como warning
            // LogManager::warning("No existe política para: {$resourceType}");

            return false;
        }

        // ══════════════════════════════════════════
        // PASO 2: Llamar al método correcto de la policy
        // ══════════════════════════════════════════

        // MATCH EXPRESSION (PHP 8+):
        // Similar a switch, pero retorna valor y es más limpio
        return match ($ability) {
            // Si ability es 'view' → llamamos $policy->view()
            'view' => $policy->view($actor, $resource),

            // Si ability es 'create' → llamamos $policy->create()
            'create' => $policy->create($actor),

            // Si ability es 'update' → llamamos $policy->update()
            'update' => $policy->update($actor, $resource),

            // Si ability es 'delete' → llamamos $policy->delete()
            'delete' => $policy->delete($actor, $resource),

            // Si ability es algo más → denegamos
            default => false,
        };

        // ¿POR QUÉ MATCH EN VEZ DE IF?
        // - Más limpio y legible
        // - Exhaustivo: PHP avisa si falta un caso
        // - Retorna valor directamente
    }

    /**
     * 🎯 MÉTODO: Verifica si puede asignar un rol
     *
     * PROPÓSITO: Método especializado para asignación de roles
     *
     * ¿POR QUÉ UN MÉTODO APARTE?
     * Porque asignar roles no es un CRUD estándar (view/create/update/delete)
     * Es una acción especial que merece su propio método
     *
     * EJEMPLO DE USO:
     * if ($auth->canAssignRole($admin, 'facturador')) {
     *     // Admin puede hacer a alguien facturador
     * }
     *
     * @param Users|null $actor Usuario que intenta asignar
     * @param string $targetRole Rol a asignar
     * @return bool True si puede, False si no
     */
    public function canAssignRole(?Users $actor, string $targetRole): bool
    {
        // Obtenemos la policy de usuarios
        $policy = $this->policies['user'] ?? null;

        // Verificamos que sea UserPolicy específicamente
        // (No cualquier policy, sino la de usuarios)
        if (!($policy instanceof UserPolicy)) {
            return false;
        }

        // Llamamos al método especializado
        return $policy->assignRole($actor, $targetRole);
    }

    /**
     * 🔍 MÉTODO: Verifica si tiene un permiso específico
     *
     * PROPÓSITO: Para casos donde solo necesitas verificar un permiso
     * sin asociarlo a un recurso específico
     *
     * DIFERENCIA CON can():
     * - can(): "¿Puede EDITAR a ESTE usuario?"
     * - hasPermission(): "¿Tiene permiso de EDITAR_USUARIOS en general?"
     *
     * EJEMPLO DE USO:
     * if ($auth->hasPermission($user, Permission::ACCESS_ADMIN_PANEL)) {
     *     // Mostrar link al panel admin en el menú
     * }
     *
     * @param Users|null $actor Usuario a verificar
     * @param string $permission Permiso a verificar
     * @return bool True si tiene el permiso, False si no
     */
    public function hasPermission(?Users $actor, string $permission): bool
    {
        // VALIDACIÓN: Usuario autenticado
        if (!$actor) {
            return false;
        }

        // Delegamos a RolePermissions
        return RolePermissions::hasPermission(
            $actor->getRole(),
            $permission
        );
    }

    /**
     * 📋 MÉTODO: Obtiene roles asignables para un usuario
     *
     * PROPÓSITO: Para poblar dropdowns en formularios
     *
     * EJEMPLO DE USO EN CONTROLLER:
     * $rolesDisponibles = $auth->getAssignableRoles($currentUser);
     * // Pasa a la vista para mostrar en <select>
     *
     * @param Users|null $actor Usuario actual
     * @return array Lista de roles que puede asignar
     */
    public function getAssignableRoles(?Users $actor): array
    {
        if (!$actor) {
            return [];
        }

        return RolePermissions::getAssignableRoles($actor->getRole());
    }
}