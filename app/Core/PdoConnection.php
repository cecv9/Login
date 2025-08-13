<?php


namespace Enoc\Login\Core;

use Enoc\Login\Config\DatabaseConfig;

use PDO;
use PDOException;

final class PdoConnection implements DatabaseConnectionInterface
{
    private PDO $pdo;

    public function __construct(DatabaseConfig $config)
    {
        try {
            $this->pdo = new PDO(
                $config->getDsn(),
                $config->getUser(),
                $config->getPassword(),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            throw new DatabaseConnectionException($e->getMessage(), (int) $e->getCode());
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

}