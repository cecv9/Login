<?php
declare(strict_types=1);

namespace Enoc\Login\Services;

use Enoc\Login\Controllers\AuditContext;
use Enoc\Login\Core\LogManager;
use Enoc\Login\Dto\CreateUserDTO;
use Enoc\Login\Dto\UpdateUserDTO;
use Enoc\Login\Repository\UsuarioRepository;
use Enoc\Login\Services\Exceptions\EmailAlreadyExists;
use Enoc\Login\Services\Exceptions\ValidationException;
use PDOException;


/**
 * RESPONSABILIDAD:
 * - Validar reglas de NEGOCIO (unique, permisos, estado)
 * - Ejecutar operaciones con la BD
 * - Manejar transacciones y errores de persistencia
 *
 * Asume que el DTO ya viene con datos sintácticamente válidos
 */

final class UserService
{
    private const VALID_ROLES = ['user', 'admin','facturador', 'bodeguero', 'liquidador', 'vendedor_sistema'];
    private const ACTION_USER_CREATED = 'USER_CREATED';
    private const ACTION_USER_UPDATED = 'USER_UPDATED';
    private const ACTION_USER_DELETED = 'USER_DELETED';

    private LogManager $logger;

    public function __construct(
        private readonly UsuarioRepository $repository,
        ?LogManager $logger = null
    ) {
        $this->logger = $logger ?? LogManager::getInstance();
    }

    public function create(CreateUserDTO $dto, ?AuditContext $audit = null): int {
        $audit = $audit ?? AuditContext::fromSession();

        $this->logger->info('Intentando crear usuario', [
            'target_email' => $dto->email,
            'target_role' => $dto->role,
            ...$audit->toArray()
        ]);

        // Validación: Email único
        $existing = $this->repository->findByEmail($dto->email);
        if ($existing) {
            $this->logger->warning('Intento de crear usuario con email duplicado', [
                'target_email' => $dto->email,
                ...$audit->toArray()
            ]);
            throw new EmailAlreadyExists("El email {$dto->email} ya está registrado");
        }

        // Validación: Rol válido
        try {
            $this->validateRole($dto->role);
        } catch (ValidationException $e) {
            $this->logger->error('Intento de crear usuario con rol inválido', [
                'target_email' => $dto->email,
                'invalid_role' => $dto->role,
                ...$audit->toArray()
            ]);
            throw $e;
        }

        // Hash de contraseña
        $passwordHash = password_hash($dto->password, PASSWORD_DEFAULT);

        //Persistencia
        try {
            $userId = $this->repository->createUserHashed(
                email: $dto->email,
                name: $dto->name,
                password_hash: $passwordHash,
                role: $dto->role
            );

            // ✅ AUDITORÍA: Usuario creado exitosamente
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
                $this->logger->warning('Race condition: email duplicado', [
                    'target_email' => $dto->email,
                    ...$audit->toArray()
                ]);
                throw new EmailAlreadyExists("El email {$dto->email} ya está registrado");
            }

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
            'target_email' => $dto->email,
            'has_password_change' => $dto->password !== null,
            ...$audit->toArray()
        ]);

        // Validación: Usuario existe
        $user = $this->repository->findById($dto->id);
        if (!$user) {
            $this->logger->warning('Usuario no encontrado', [
                'target_user_id' => $dto->id,
                ...$audit->toArray()
            ]);
            throw new \RuntimeException('Usuario no encontrado');
        }

        // Captura valores antiguos para auditoría
        $oldValues = [
            'old_email' => is_array($user) ? $user['email'] : $user->getEmail(),
            'old_name' => is_array($user) ? $user['name'] : $user->getName(),
            'old_role' => is_array($user) ? $user['role'] : $user->getRole(),
        ];


        $oldValues = $this->extractUserValues($user, 'old_');

        // Validación: Email único
        $existingEmail = $this->repository->findByEmail($dto->email);
        if ($existingEmail) {
            $existingId = $this->extractUserId($existingEmail);
            if ($existingId !== null && $existingId !== $dto->id) {
                $this->logger->warning('Email duplicado en actualización', [
                    'target_user_id' => $dto->id,
                    'target_email' => $dto->email,
                    'conflicting_user_id' => $existingId,
                    ...$audit->toArray()
                ]);
                throw new EmailAlreadyExists("El email {$dto->email} ya está registrado");
            }
        }

        // Validación: Rol válido
        try {
            $this->validateRole($dto->role);
        } catch (ValidationException $e) {
            $this->logger->error('Rol inválido en actualización', [
                'target_user_id' => $dto->id,
                'invalid_role' => $dto->role,
                ...$audit->toArray()
            ]);
            throw $e;
        }

        $hash = null;
        if ($dto->password !== null && $dto->password !== '') {
            $hash = password_hash($dto->password, PASSWORD_DEFAULT);
        }

        try {
            $success = $this->repository->updateUserHashed(
                id: $dto->id,
                name: $dto->name,
                email: $dto->email,
                password_hash: $hash,
                role: $dto->role
            );

            if ($success) {
                $this->logger->info('✅ Usuario actualizado', [
                    'action' => self::ACTION_USER_UPDATED,
                    'target_user_id' => $dto->id,
                    'target_email' => $dto->email,
                    'target_name' => $dto->name,
                    'target_role' => $dto->role,
                    'password_changed' => $hash !== null,
                    ...$oldValues,
                    ...$audit->toArray()
                ]);
            }

            return $success;

        } catch (PDOException $e) {
            if ($this->isDuplicateKeyError($e)) {
                $this->logger->warning('Race condition en actualización', [
                    'target_user_id' => $dto->id,
                    ...$audit->toArray()
                ]);
                throw new EmailAlreadyExists("El email {$dto->email} ya está registrado");
            }

            $this->logger->error('❌ Error al actualizar usuario', [
                'target_user_id' => $dto->id,
                'error' => $e->getMessage(),
                ...$audit->toArray()
            ]);
            throw $e;
        }
    }


    /**
     * Elimina (soft delete) un usuario
     * @return bool True si se eliminó, false si no se encontró
     * @throws \RuntimeException si ocurre un error inesperado
     * o en la base de datos
     * @throws ValidationException si el ID es inválido
     */

    public function delete(int $id, ?AuditContext $audit = null): bool
    {
        $audit = $audit ?? AuditContext::fromSession();

        $this->logger->info('Intentando eliminar usuario', [
            'target_user_id' => $id,
            ...$audit->toArray()
        ]);

        $user = $this->repository->findById($id);
        if (!$user) {
            $this->logger->warning('Usuario no encontrado para eliminar', [
                'target_user_id' => $id,
                ...$audit->toArray()
            ]);
            return false;
        }

        $userEmail = is_array($user) ? $user['email'] : $user->getEmail();
        $success = $this->repository->softDeleteUser($id);

        if ($success) {
            $this->logger->warning('⚠️ Usuario eliminado (soft delete)', [
                'action' => self::ACTION_USER_DELETED,
                'target_user_id' => $id,
                'target_email' => $userEmail,
                ...$audit->toArray()
            ]);
        }

        return $success;
    }

    private function validateRole(string $role): void
    {
        if (!in_array($role, self::VALID_ROLES, true)) {
            throw new ValidationException([
                'role' => ['El rol debe ser: ' . implode(', ', self::VALID_ROLES)]
            ]);
        }
    }

    /**
     * Verifica si el error PDO es por clave duplicada (MySQL error 1062)
     */
    private function isDuplicateKeyError(PDOException $e): bool
    {
        return isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062;
    }

    /**
     * Extrae el ID de un usuario (array o objeto)
     */
    private function extractUserId(mixed $user): ?int
    {
        if (is_array($user) && isset($user['id'])) {
            return (int)$user['id'];
        }
        if (is_object($user) && method_exists($user, 'getId')) {
            return (int)$user->getId();
        }
        return null;
    }


    private function extractUserValues(mixed $user, string $prefix = ''): array
    {
        if (is_array($user)) {
            return [
                "{$prefix}email" => $user['email'] ?? null,
                "{$prefix}name" => $user['name'] ?? null,
                "{$prefix}role" => $user['role'] ?? null,
            ];
        }

        if (is_object($user)) {
            return [
                "{$prefix}email" => method_exists($user, 'getEmail') ? $user->getEmail() : null,
                "{$prefix}name" => method_exists($user, 'getName') ? $user->getName() : null,
                "{$prefix}role" => method_exists($user, 'getRole') ? $user->getRole() : null,
            ];
        }

        return [];
    }
}