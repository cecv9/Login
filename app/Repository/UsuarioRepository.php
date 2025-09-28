<?php
declare(strict_types=1);

namespace Enoc\Login\Repository;

use Enoc\Login\Core\PdoConnection;
use Enoc\Login\models\Users;
use PDO;
use PDOException;

interface UserRepositoryInterface{
    public function findById(int $id): ?Users;
    public function findByEmail(string $email): ?Users;

    public function createUser(string $email, string $name, string $password ,string $role='user'): ? int;

    public function findAllUsers(int $limit = 10, int $offset = 0): array;

    public function countUsers(): int;
    public function updateUser(int $id, string $name, string $email, ?string $password, string $role): bool;
    public function updateUserRole(int $id, string $role): bool;
    public function deleteUser(int $id): bool;


}

class UsuarioRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PdoConnection $conector)
    {
        $this->pdo = $conector->getPdo();
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    }

    /**
     * {@inheritDoc}
     */

    public function findById(int $id): ?Users{
        if ($id <= 0) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id, email, name, role,password_hash FROM users WHERE id = :id');  // ← Agrega name
            $stmt->execute(['id' => $id]);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $row = $stmt->fetch();

            if ($row === false) {
                return null;
            }

            $user = new Users();
            $user->setId((int)($row['id'] ?? 0));
            $user->setEmail((string)($row['email'] ?? ''));
            $user->setName((string)($row['name'] ?? ''));  // ← NUEVO: Hidratación para name
            $user->setRole((string)($row['role'] ?? 'user'));  // ← NUEVO: Hidratación para role
            $user->setPassword((string)($row['password_hash'] ?? ''));

            return $user;
        } catch (PDOException $e) {
            error_log('Error al buscar usuario por ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByEmail(string $email): ?Users{
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id, email, name, role,password_hash FROM users WHERE email = :email');  // ← Agrega name
            $stmt->execute(['email' => $email]);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $row = $stmt->fetch();

            if ($row === false) {
                return null;
            }

            $user = new Users();
            $user->setId((int)($row['id'] ?? 0));
            $user->setEmail((string)($row['email'] ?? ''));
            $user->setName((string)($row['name'] ?? ''));  // ← NUEVO: Hidratación para name
            $user->setRole((string)($row['role'] ?? 'user'));  // ← NUEVO: Hidratación para role
            $user->setPassword((string)($row['password_hash'] ?? ''));

            return $user;
        } catch (PDOException $e) {
            error_log('Error al buscar usuario por email: ' . $e->getMessage());
            return null;
        }
    }



    public function createUser(string $email,string $name, string $password_hash ,string $role = 'user' ): ?int
    {
        $cleanEmail = trim(strtolower($email));
        $cleanPass = trim($password_hash);
        $cleanName = trim($name);

        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) || empty($cleanPass) || strlen($cleanPass) < 6 || !in_array($role, ['user', 'admin'])) {
            error_log('Registro falló: Validación input inválida');  // Si tienes debug
            return null;
        }

        try {
            // Chequea unique email
            if ($this->findByEmail($cleanEmail)) {
              //  error_log('Registro falló: Email ya existe - ' . $cleanEmail);  // DEBUG TEMPORAL
                return null;
            }

            $hashedPassword = password_hash($cleanPass, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare('INSERT INTO users (email, name, password_hash, role, created_at) VALUES (:email, :name, :password_hash, :role, NOW())');  // ← Agrega role
            $stmt->execute([
                'email' => $cleanEmail,
                'name' => $cleanName,
                'password_hash' => $hashedPassword,
                'role' => $role  // ← NUEVO: Inserta directo
            ]);

            return (int)$this->pdo->lastInsertId();
          //  error_log('Registro éxito: ID nuevo = ' . $newId . ' para email ' . $cleanEmail);  // DEBUG TEMPORAL
          //  return $newId;
        } catch (PDOException $e) {
           // error_log('Registro falló: PDO Error - ' . $e->getMessage() . ' | Email: ' . $cleanEmail);  // DEBUG TEMPORAL
            error_log('Error al crear usuario: ' . $e->getMessage());
            return null;
        }
    }



    public function findAllUsers(int $limit = 10, int $offset = 0): array
    {
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
            error_log('Error listando users: ' . $e->getMessage());
            return [];
        }
    }

    public function countUsers(): int
    {
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
            error_log('Error contando users: ' . $e->getMessage());
            return 0;
        }
    }


    public function updateUser(int $id, string $name, string $email, ?string $password, string $role): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        try {
            if ($this->findByEmail($email) && $this->findByEmail($email)->getId() !== $id) {
                return false;  // Email duplicado
            }

            $sql = 'UPDATE users SET name = :name, email = :email, role = :role';
            $params = ['name' => trim($name), 'email' => trim(strtolower($email)), 'role' => $role];

            if ($password) {
                $sql .= ', password_hash = :password_hash';
                $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= ' WHERE id = :id';
            $params['id'] = $id;

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params) && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Error actualizando user: ' . $e->getMessage());
            return false;
        }
    }

    public function updateUserRole(int $id, string $role): bool
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
            return $stmt->execute(['role' => $role, 'id' => $id]) && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Error actualizando role: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteUser(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE users SET deleted_at = NOW() WHERE id = :id');  // Soft-delete
            return $stmt->execute(['id' => $id]) && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Error borrando user: ' . $e->getMessage());
            return false;
        }
    }
}