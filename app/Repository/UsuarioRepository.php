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

    public function createUser(string $name, string $email, string $password): ? int;
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

    public function findById(int $id): ?Users{
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
    public function findByEmail(string $email): ?Users{
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



    public function createUser(string $email, string $password_hash, string $name): ?int
    {
        $cleanEmail = trim(strtolower($email));
        $cleanPass = trim($password_hash);
        $cleanName = trim($name);

        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) || empty($cleanPass) || strlen($cleanPass) < 6) {
            error_log('Registro falló: Validación input inválida - Email: ' . $cleanEmail . ', Pass len: ' . strlen($cleanPass));  // DEBUG TEMPORAL
            return null;
        }

        try {
            // Chequea unique email
            if ($this->findByEmail($cleanEmail)) {
              //  error_log('Registro falló: Email ya existe - ' . $cleanEmail);  // DEBUG TEMPORAL
                return null;
            }

            $hashedPassword = password_hash($cleanPass, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare('INSERT INTO users (email, name, password_hash, created_at) VALUES (:email, :name, :password_hash, NOW())');
            $stmt->execute([
                'email' => $cleanEmail,
                'name' => $cleanName,
                'password_hash' => $hashedPassword
            ]);

            $newId = (int)$this->pdo->lastInsertId();
          //  error_log('Registro éxito: ID nuevo = ' . $newId . ' para email ' . $cleanEmail);  // DEBUG TEMPORAL
            return $newId;
        } catch (PDOException $e) {
           // error_log('Registro falló: PDO Error - ' . $e->getMessage() . ' | Email: ' . $cleanEmail);  // DEBUG TEMPORAL
            return null;
        }
    }
}