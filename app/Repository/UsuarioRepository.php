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
}

class UsuarioRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PdoConnection $conector)
    {
        $this->pdo = $conector->getPdo();
    }

    /**
     * {@inheritDoc}
     */
    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Users
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id, email, name, password_hash FROM users WHERE id = :id');  // ← Agrega name
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
    public function findByEmail(string $email): ?Users
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id, email, name, password_hash FROM users WHERE email = :email');  // ← Agrega name
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
            $user->setPassword((string)($row['password_hash'] ?? ''));

            return $user;
        } catch (PDOException $e) {
            error_log('Error al buscar usuario por email: ' . $e->getMessage());
            return null;
        }
    }
}