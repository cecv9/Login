<?php
declare(strict_types=1);

namespace Enoc\Login\Repository;

use Enoc\Login\Core\PdoConnection;
use Enoc\Login\models\Users;
use PDO;
use PDOException;

interface UserRepositoryInterface{
    /**
     * Busca un usuario por su ID.
     *
     * @param int $id El ID del usuario.
     * @return Users|null El usuario encontrado o null si no existe.
     */
    public function findById(int $id): ?Users;

    /**
     * Busca un usuario por su email.
     *
     * @param string $email El email del usuario.
     * @return Users|null El usuario encontrado o null si no existe.
     */
    public function findByEmail(string $email): ?Users;
}

class UsuarioRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    /**
     * Constructor.
     *
     * @param PdoConnection $conector La conexión PDO.
     */
    public function __construct(PdoConnection $conector)
    {
        $this->pdo = $conector->getPdo();
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Users
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id, email,  password_hash FROM users WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $stmt->setFetchMode(PDO::FETCH_CLASS, Users::class);
            $user = $stmt->fetch();
            return $user === false ? null : $user;
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
            $stmt = $this->pdo->prepare('SELECT id, email,  password_hash FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            $stmt->setFetchMode(PDO::FETCH_CLASS, Users::class);
            $user = $stmt->fetch();
            return $user === false ? null : $user;
        } catch (PDOException $e) {
            error_log('Error al buscar usuario por email: ' . $e->getMessage());
            return null;
        }
    }
}