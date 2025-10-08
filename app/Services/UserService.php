<?php
declare(strict_types=1);

namespace Enoc\Login\Services;

use Enoc\Login\Authorization\AuthorizationService;
use Enoc\Login\Controllers\AuditContext;
use Enoc\Login\Core\LogManager;
use Enoc\Login\Dto\CreateUserDTO;
use Enoc\Login\Dto\UpdateUserDTO;
use Enoc\Login\Enums\UserRole;
use Enoc\Login\Repository\UsuarioRepository;
use Enoc\Login\Services\Exceptions\EmailAlreadyExists;
use Enoc\Login\Services\Exceptions\UnauthorizedException;
use Enoc\Login\Services\Exceptions\ValidationException;
use PDOException;

final class UserService
{
    private const ACTION_USER_CREATED = 'USER_CREATED';
    private const ACTION_USER_UPDATED = 'USER_UPDATED';

    private LogManager $logger;
    private AuthorizationService $auth;

    public function __construct(
        private readonly UsuarioRepository $repository,
        ?LogManager $logger = null,
        ?AuthorizationService $auth = null
    ) {
        $this->logger = $logger ?? LogManager::getInstance();
        $this->auth = $auth ?? new AuthorizationService();
    }

    public function create(CreateUserDTO $dto, ?AuditContext $audit = null): int
    {
        $audit = $audit ?? AuditContext::fromSession();

        $this->logger->info('Intentando crear usuario', [
            'target_email' => $dto->email,
            'target_role' => $dto->role,
            ...$audit->toArray()
        ]);

        // Obtener el usuario actual (actor)
        $actor = $this->getActorFromAudit($audit);

        // Validar autorización: ¿Puede crear usuarios?
        if (!$this->auth->can($actor, 'create', 'user')) {
            $this->logger->warning('❌ Usuario sin permisos intentó crear usuario', [
                'actor_role' => $actor?->getRole() ?? 'guest',
                'target_email' => $dto->email,
                ...$audit->toArray()
            ]);
            throw new UnauthorizedException('No tienes permisos para crear usuarios');
        }

        // Validar autorización: ¿Puede asignar este rol?
        if (!$this->auth->canAssignRole($actor, $dto->role)) {
            $this->logger->warning('❌ Usuario intentó asignar rol sin permisos', [
                'actor_role' => $actor?->getRole() ?? 'guest',
                'target_role' => $dto->role,
                ...$audit->toArray()
            ]);
            throw new UnauthorizedException("No tienes permisos para asignar el rol: {$dto->role}");
        }

        // Validar negocio: Email único
        $existing = $this->repository->findByEmail($dto->email);
        if ($existing) {
            $this->logger->warning('Email duplicado', [
                'target_email' => $dto->email,
                ...$audit->toArray()
            ]);
            throw new EmailAlreadyExists("El email {$dto->email} ya está registrado");
        }

        // Validar negocio: Rol válido
        if (!UserRole::exists($dto->role)) {
            throw new ValidationException(['role' => ['El rol especificado no existe']]);
        }

        // Persistencia
        $passwordHash = password_hash($dto->password, PASSWORD_DEFAULT);

        try {
            $userId = $this->repository->createUserHashed(
                email: $dto->email,
                name: $dto->name,
                password_hash: $passwordHash,
                role: $dto->role
            );

            if ($userId === null) {
                throw new \RuntimeException('Error al crear usuario en base de datos');
            }

            $this->logger->info('✅ Usuario creado exitosamente', [
                'action' => self::ACTION_USER_CREATED,
                'target_user_id' => $userId,
                'target_email' => $dto->email,
                'target_name' => $dto->name,
                'target_role' => $dto->role,
                ...$audit->toArray()
            ]);

            return $userId;

        } catch (PDOException $e) {
            if ($this->isDuplicateKeyError($e)) {
                throw new EmailAlreadyExists("El email {$dto->email} ya está registrado");
            }

            error_log("=== PDO ERROR CREAR USUARIO ===");
            error_log("Mensaje: " . $e->getMessage());
            error_log("Email: " . $dto->email);
            error_log("===============================");

            $this->logger->error('❌ Error al crear usuario', [
                'target_email' => $dto->email,
                'error' => $e->getMessage(),
                ...$audit->toArray()
            ]);
            throw $e;
        }
    }

    public function update(UpdateUserDTO $dto, ?AuditContext $audit = null): bool
    {
        $audit = $audit ?? AuditContext::fromSession();

        $this->logger->info('Intentando actualizar usuario', [
            'target_user_id' => $dto->id,
            ...$audit->toArray()
        ]);

        // Validar: Usuario existe
        $targetUser = $this->repository->findById($dto->id);
        if (!$targetUser) {
            $this->logger->warning('Usuario no encontrado', [
                'target_user_id' => $dto->id,
                ...$audit->toArray()
            ]);
            throw new \RuntimeException('Usuario no encontrado');
        }

        $oldValues = [
            'old_email' => $targetUser->getEmail(),
            'old_name' => $targetUser->getName(),
            'old_role' => $targetUser->getRole(),
        ];

        // Obtener actor
        $actor = $this->getActorFromAudit($audit);

        // Autorización: ¿Puede editar a ESTE usuario?
        if (!$this->auth->can($actor, 'update', 'user', $targetUser)) {
            $this->logger->warning('❌ Usuario sin permisos intentó editar usuario', [
                'target_user_id' => $dto->id,
                ...$audit->toArray()
            ]);
            throw new UnauthorizedException('No tienes permisos para editar este usuario');
        }

        // Autorización: Si cambió el rol, ¿puede asignarlo?
        if ($dto->role !== $targetUser->getRole()) {
            // PROTECCIÓN: Admin no puede cambiar su propio rol
            if ($actor && $actor->getId() === $targetUser->getId() &&
                $targetUser->getRole() === UserRole::ADMIN) {
                $this->logger->warning('❌ Admin intentó cambiar su propio rol', [
                    'target_user_id' => $dto->id,
                    'old_role' => $targetUser->getRole(),
                    'new_role' => $dto->role,
                    ...$audit->toArray()
                ]);
                throw new UnauthorizedException("No puedes cambiar tu propio rol de administrador");
            }

            if (!$this->auth->canAssignRole($actor, $dto->role)) {
                $this->logger->warning('❌ Usuario intentó cambiar rol sin permisos', [
                    'target_user_id' => $dto->id,
                    'old_role' => $targetUser->getRole(),
                    'new_role' => $dto->role,
                    ...$audit->toArray()
                ]);
                throw new UnauthorizedException("No tienes permisos para asignar el rol: {$dto->role}");
            }
        }

        // Validación: Email único
        $existingEmail = $this->repository->findByEmail($dto->email);
        if ($existingEmail && $existingEmail->getId() !== $dto->id) {
            $this->logger->warning('Email duplicado en actualización', [
                'target_user_id' => $dto->id,
                'target_email' => $dto->email,
                'conflicting_user_id' => $existingEmail->getId(),
                ...$audit->toArray()
            ]);
            throw new EmailAlreadyExists("El email {$dto->email} ya está registrado");
        }

        // Persistencia
        $passwordHash = $dto->password !== null
            ? password_hash($dto->password, PASSWORD_DEFAULT)
            : null;

        try {
            $success = $this->repository->updateUserHashed(
                id: $dto->id,
                name: $dto->name,
                email: $dto->email,
                password_hash: $passwordHash,
                role: $dto->role
            );

            if (!$success) {
                throw new \RuntimeException('Error al actualizar usuario');
            }

            $this->logger->info('✅ Usuario actualizado exitosamente', [
                'action' => self::ACTION_USER_UPDATED,
                'target_user_id' => $dto->id,
                'new_email' => $dto->email,
                'new_name' => $dto->name,
                'new_role' => $dto->role,
                'password_changed' => $dto->password !== null,
                ...$oldValues,
                ...$audit->toArray()
            ]);

            return true;

        } catch (PDOException $e) {
            if ($this->isDuplicateKeyError($e)) {
                throw new EmailAlreadyExists("El email {$dto->email} ya está registrado");
            }

            error_log("=== PDO ERROR ACTUALIZAR USUARIO ===");
            error_log("Mensaje: " . $e->getMessage());
            error_log("User ID: " . $dto->id);
            error_log("====================================");

            $this->logger->error('❌ Error al actualizar usuario', [
                'target_user_id' => $dto->id,
                'error' => $e->getMessage(),
                ...$audit->toArray()
            ]);
            throw $e;
        }
    }

    /**
     * Elimina un usuario (soft delete)
     */
    public function delete(int $userId, ?AuditContext $audit = null): bool
    {
        $audit = $audit ?? AuditContext::fromSession();

        $this->logger->info('Intentando eliminar usuario', [
            'target_user_id' => $userId,
            ...$audit->toArray()
        ]);

        // Validar que existe
        $targetUser = $this->repository->findById($userId);
        if (!$targetUser) {
            $this->logger->warning('Usuario no encontrado', [
                'target_user_id' => $userId,
                ...$audit->toArray()
            ]);
            throw new \RuntimeException('Usuario no encontrado');
        }

        // REGLA DE NEGOCIO: No se pueden eliminar admins
        if ($targetUser->getRole() === UserRole::ADMIN) {
            $this->logger->warning('Intento de eliminar administrador', [
                'target_user_id' => $userId,
                ...$audit->toArray()
            ]);
            throw new \RuntimeException('No se pueden eliminar administradores');
        }

        // REGLA DE NEGOCIO: No puedes eliminarte a ti mismo
        $currentUserId = $_SESSION['user_id'] ?? 0;
        if ($userId === $currentUserId) {
            $this->logger->warning('Usuario intentó eliminarse a sí mismo', [
                'target_user_id' => $userId,
                ...$audit->toArray()
            ]);
            throw new \RuntimeException('No puedes eliminar tu propia cuenta');
        }

        // Eliminar (soft delete)
        try {
            $success = $this->repository->softDeleteUser($userId);

            if (!$success) {
                throw new \RuntimeException('Error al eliminar usuario');
            }

            $this->logger->info('✅ Usuario eliminado exitosamente', [
                'target_user_id' => $userId,
                'target_email' => $targetUser->getEmail(),
                ...$audit->toArray()
            ]);

            return true;

        } catch (PDOException $e) {
            $this->logger->error('❌ Error al eliminar usuario', [
                'target_user_id' => $userId,
                'error' => $e->getMessage(),
                ...$audit->toArray()
            ]);
            throw $e;
        }
    }

    /**
     * ✅ MÉTODO ARREGLADO: Usar userId en vez de actorId
     */
    private function getActorFromAudit(AuditContext $audit): ?\Enoc\Login\models\Users
    {
        // ✅ CORRECCIÓN: La propiedad se llama 'userId' no 'actorId'
        $actorId = $audit->userId;  // ← CAMBIO CRÍTICO AQUÍ

        if ($actorId === null) {
            return null;
        }

        return $this->repository->findById($actorId);
    }

    private function isDuplicateKeyError(PDOException $e): bool
    {
        return str_contains($e->getMessage(), 'Duplicate entry') ||
            str_contains($e->getMessage(), 'UNIQUE constraint');
    }
}