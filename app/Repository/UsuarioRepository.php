<?php

namespace Enoc\Login\Repository;

use Enoc\Login\Core\PdoConnection;
use Enoc\Login\models\Users;
use PDO;

class UsuarioRepository {

    private PDO $pdo;

    public function __construct(PdoConnection $conector) {
        $this->pdo = $conector->getPdo();
    }

    public function findById(int $id): ?Users {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, Users::class);
        return $stmt->fetch();
    }

    public function findByEmail(string $email): ?Users {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, Users::class);
        return $stmt->fetch();
    }
}