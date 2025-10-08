<?php
declare(strict_types=1);

namespace Enoc\Login\Repository;

use Enoc\Login\Core\LogManager;
use Enoc\Login\Core\PdoConnection;
use Enoc\Login\models\Users;
use Enoc\Login\Enums\UserRole;
use PDO;
use PDOException;

interface UserRepositoryInterface {
    public function findById(int $id): ?Users;
    public function findByEmail(string $email): ?Users;

    // Plain (compatibilidad legacy)
    public function createUser(string $name, string $email, string $password, string $role='user'): ?int;
    public function updateUser(int $id, string $name, string $email, ?string $password, string $role): bool;
    public function deleteUser(int $id): bool;

    // Hash-ready (usados por el Service)
    public function createUserHashed(string $email, string $name, string $password_hash, string $role='user'): ?int;
    public function updateUserHashed(int $id, string $name, string $email, ?string $password_hash, string $role): bool;
    public function softDeleteUser(int $id): bool;

    // Listado / métricas
    public function findAllUsers(int $limit = 10, int $offset = 0): array;
    public function countUsers(): int;
}


class UsuarioRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PdoConnection $conector)
    {
        $this->pdo = $conector->getPdo();
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    /**
     * {@inheritDoc}
     * Metodo para buscar usuario por ID.
     * Retorna null si no lo encuentra o si el ID es inválido (<=0).
     * Captura errores de BD y los loguea.
     * Usa hydrateUser() para mapear fila a entidad Users.
     */

    public function findById(int $id): ?Users
    {
        if ($id <= 0) return null;

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, email, name, role, password_hash, deleted_at
               FROM users
              WHERE id = :id
                AND deleted_at IS NULL
              LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->hydrateUser($row) : null;
        } catch (PDOException $e) {
            LogManager::logError('Error al buscar usuario por ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mapea una fila de BD (array asociativo) a una entidad Users.
     * Maneja campos opcionales y valores por defecto.
     * @param array $row
     * @return Users
     * @throws \Exception si hay error en setters (opcional)
     * TODO: ajustar según tu entidad Users
     * Usa setPasswordHash() si existe, sino setPassword() (renombrar este método en la entidad).
     * Maneja deletedAt si tu entidad lo soporta.
     * Captura errores de setters si es necesario.
     * Considera usar un mapper externo si la lógica crece.
     * Usa null coalesce y casting para seguridad.
     * Evita inyección al usar solo datos ya validados de BD.
     * No loguea ni lanza excepciones aquí; lo hace el método que llama (findById, findByEmail).
     * No hace queries ni lógica de negocio; solo mapea datos.
     */
    private function hydrateUser(array $row): Users
    {
        $user = new Users();
        $user->setId((int)($row['id'] ?? 0));
        $user->setEmail((string)($row['email'] ?? ''));
        $user->setName((string)($row['name'] ?? ''));
        $user->setRole((string)($row['role'] ?? 'user'));

        // Este valor es el HASH. Si puedes, usa setPasswordHash().
        if (method_exists($user, 'setPasswordHash')) {
            $user->setPasswordHash((string)($row['password_hash'] ?? ''));
        } else {
            $user->setPassword((string)($row['password_hash'] ?? '')); // TODO: renombrar método
        }

        // Si tu entidad soporta deletedAt:
        if (method_exists($user, 'setDeletedAt')) {
            $user->setDeletedAt($row['deleted_at'] ?? null);
        }

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function findByEmail(string $email): ?Users
    {
        $cleanEmail = strtolower(trim($email));
        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) return null;

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, email, name, role, password_hash, deleted_at
               FROM users
              WHERE email = :email
                AND deleted_at IS NULL
              LIMIT 1'
            );
            $stmt->execute(['email' => $cleanEmail]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->hydrateUser($row) : null;
        } catch (PDOException $e) {
            LogManager::logError('Error al buscar usuario por email: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualiza usuario con password ya hasheado (opcional)
     * CAMBIO: Ahora acepta TODOS los roles
     */
    public function updateUserHashed(
        int $id,
        string $name,
        string $email,
        ?string $password_hash,
        string $role
    ): bool {
        $email = strtolower(trim($email));
        $name  = trim($name);

        // ✅ CAMBIO AQUÍ: Validación con UserRole::exists() + fallback seguro
        $role = UserRole::exists($role) ? $role : UserRole::USER;  // ← LÍNEA CAMBIADA

        try {
            if ($password_hash === null) {
                // Sin cambio de contraseña
                $sql = 'UPDATE users
                    SET name = :name,
                        email = :email,
                        role  = :role
                    WHERE id = :id';
                $params = [
                    'name'  => $name,
                    'email' => $email,
                    'role'  => $role,
                    'id'    => $id,
                ];
            } else {
                // Con cambio de contraseña
                $sql = 'UPDATE users
                    SET name = :name,
                        email = :email,
                        password_hash = :password_hash,
                        role  = :role
                    WHERE id = :id';
                $params = [
                    'name'          => $name,
                    'email'         => $email,
                    'password_hash' => $password_hash,
                    'role'          => $role,
                    'id'            => $id,
                ];
            }

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);

        } catch (PDOException $e) {
            LogManager::logError('Error actualizando user (hashed): ' . $e->getMessage());
            return false;
        }
    }



    /**
     * Wrapper legacy: recibe password en texto plano
     * CAMBIO: Ahora acepta TODOS los roles
     */
    public function createUser(
        string $name,
        string $email,
        string $password_plain,
        string $role = 'user'
    ): ?int {
        $cleanEmail = trim(strtolower($email));
        $cleanName  = trim($name);
        $plain      = (string) $password_plain;

        // ✅ CAMBIO AQUÍ: Usar UserRole::exists()
        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) ||
            mb_strlen($plain) < 6 ||
            !UserRole::exists($role)) {  // ← LÍNEA CAMBIADA
            LogManager::logError('Registro falló: Validación input inválida (repo wrapper).');
            return null;
        }

        // Hash y delegar
        $password_hash = password_hash($plain, PASSWORD_DEFAULT);
        return $this->createUserHashed($cleanEmail, $cleanName, $password_hash, $role);
    }

    /**
     * Inserta un usuario recibiendo YA el password_hash (no hash aquí).
     * Ideal para uso desde UserService.
     */
/**
* Crea usuario con password ya hasheado
* CAMBIO: Ahora acepta TODOS los roles
*/
    public function createUserHashed(
        string $email,
        string $name,
        string $password_hash,
        string $role = 'user'
    ): ?int {
        $cleanEmail = trim(strtolower($email));
        $cleanName  = trim($name);
        $hash       = trim($password_hash);

        // ✅ CAMBIO AQUÍ: Usar UserRole::exists() en vez de array hardcodeado
        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) ||
            $hash === '' ||
            !UserRole::exists($role)) {  // ← LÍNEA CAMBIADA
            LogManager::logError('Datos inválidos en createUserHashed', [
                'email' => $cleanEmail,
                'role' => $role
            ]);
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (email, name, password_hash, role, created_at)
             VALUES (:email, :name, :password_hash, :role, NOW())'
            );

            $stmt->execute([
                'email'         => $cleanEmail,
                'name'          => $cleanName,
                'password_hash' => $hash,
                'role'          => $role,
            ]);

            return (int) $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            LogManager::logError('Error al crear usuario (Hashed): ' . $e->getMessage());
            return null;
        }
    }

    /*****************************************************************
     * 2) INICIO PAGINACION POR CURSOR (SIN OFFSET)
     *    - findPageAfter($lastId, $limit)
     *    - findPageBefore($firstId, $limit)
     *   - hasMoreOlder($lastId)
     *  - hasMoreNewer($firstId)
     *****************************************************************/

    /**
     * Cursor-based pagination (sin OFFSET)
     * @param int $lastId  id del último registro de la página anterior
     * @param int $limit   cuántos traer
     * @return Users[]
     */
    public function findPageAfter(int $lastId, int $limit = 10): array
    {
        try {
            $sql = 'SELECT id, email, name, role, created_at
                FROM users
                WHERE deleted_at IS NULL
                  AND id < :last_id
                ORDER BY id DESC
                LIMIT :limit';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':last_id', $lastId, PDO::PARAM_INT);
            $stmt->bindValue(':limit',   $limit,   PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $users = [];
            foreach ($rows as $row) {
                $user = new Users();
                $user->setId((int)$row['id']);
                $user->setEmail($row['email']);
                $user->setName($row['name']);
                $user->setRole($row['role'] ?? 'user');
                $users[] = $user;
            }
            return $users;
        } catch (PDOException $e) {
            LogManager::logError('Error paginando: ' . $e->getMessage());
            return [];
        }
    }


    public function findPageBefore(int $firstId, int $limit = 10): array
    {
        try {
            $sql = 'SELECT id, email, name, role, created_at
                FROM users
                WHERE deleted_at IS NULL
                  AND id > :first_id
                ORDER BY id ASC
                LIMIT :limit';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':first_id', $firstId, \PDO::PARAM_INT);
            $stmt->bindValue(':limit',    $limit,   \PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Invertimos para mostrar en DESC en la vista
            $rows = array_reverse($rows);

            $users = [];
            foreach ($rows as $row) {
                $u = new Users();
                $u->setId((int)$row['id']);
                $u->setEmail($row['email']);
                $u->setName($row['name']);
                $u->setRole($row['role'] ?? 'user');
                $users[] = $u;
            }
            return $users;
        } catch (\PDOException $e) {
            LogManager::logError('Error paginando (before): ' . $e->getMessage());
            return [];
        }
    }

    public function hasMoreOlder(int $lastId): bool
    {
        try {
            $sql = 'SELECT id
                FROM users
                WHERE deleted_at IS NULL
                  AND id < :last_id
                ORDER BY id DESC
                LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':last_id', $lastId, \PDO::PARAM_INT);
            $stmt->execute();
            return (bool)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            LogManager::logError('Error en hasMoreOlder: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Opcional: para saber si hay registros más nuevos que el primero de la página actual.
     * Así “← Anterior” no manda a una página vacía.
     */
    public function hasMoreNewer(int $firstId): bool
    {
        try {
            $sql = 'SELECT id
                FROM users
                WHERE deleted_at IS NULL
                  AND id > :first_id
                ORDER BY id ASC
                LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':first_id', $firstId, \PDO::PARAM_INT);
            $stmt->execute();
            return (bool)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            LogManager::logError('Error en hasMoreNewer: ' . $e->getMessage());
            return false;
        }
    }

    /*****************************************************************
     * 2)  FIN PAGINACION POR CURSOR (SIN OFFSET)
     *    - findPageAfter($lastId, $limit)
     *    - findPageBefore($firstId, $limit)
     *   - hasMoreOlder($lastId)
     *  - hasMoreNewer($firstId)
     *****************************************************************/





    public function findAllUsers(int $limit = 10, int $offset = 0): array {
        try {
            // 2) Sanitizar/topes
            $limit  = min(100, max(1, $limit));
            $offset = max(0, $offset);

            // 3) Query
            $sql = 'SELECT id, email, name, role, created_at
                FROM users
                WHERE deleted_at IS NULL
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 4) Map a entidades
            $users = [];
            foreach ($rows as $row) {
                $user = new Users();
                $user->setId((int)$row['id']);
                $user->setEmail($row['email']);
                $user->setName($row['name']);
                $user->setRole($row['role'] ?? 'user');
                $users[] = $user;
            }
            return $users;

        } catch (PDOException $e) {
            LogManager::logError('Error listando users: ' . $e->getMessage());
            return [];
        }
    }

    public function countUsers(): int {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'
            );
            $stmt->execute();
            $count = (int)$stmt->fetchColumn();

            // Debug opcional: Solo en modo dev, sin exit/var_dump para no romper flujo
         //   if (defined('DEBUG_MODE') && DEBUG_MODE) {
               // error_log('DEBUG countUsers: ' . $count);
                // En lugar de var_dump/exit, usa trigger_error o un logger real para tests
           // }
            return $count;
        } catch (PDOException $e) {
            LogManager::logError('Error contando users: ' . $e->getMessage());
            return 0;
        }
    }


    public function updateUser(int $id, string $name, string $email, ?string $password, string $role): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        try {
            // Evita doble query
            $existing = $this->findByEmail($email);
            if ($existing && $existing->getId() !== $id) {
                return false; // duplicado
            }

            $hash = null;
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
            }

            return $this->updateUserHashed($id, $name, $email, $hash, $role);
        } catch (PDOException $e) {
            LogManager::logError('Error actualizando user: ' . $e->getMessage());
            return false;
        }
    }

    public function updateUserRole(int $id, string $role): bool {
        try {
            $stmt = $this->pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
            return $stmt->execute(['role' => $role, 'id' => $id]) && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            LogManager::logError('Error actualizando role: ' . $e->getMessage());
            return false;
        }
    }

    public function softDeleteUser(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE users SET deleted_at = NOW() WHERE id = :id'
            );
            return $stmt->execute(['id' => $id]) && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            LogManager::logError('Error soft-deleting user: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteUser(int $id): bool
    {
        // wrapper de compatibilidad
        return $this->softDeleteUser($id);
    }

}