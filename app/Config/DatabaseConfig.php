<?php

namespace Enoc\Login\Config;
final class DatabaseConfig{
    public function __construct(
        private string $driver,
        private string $host ,
        private string $user ,
        private string $password,
        private string $database,
        private string $charset,
        private int  $port = 3306
    ) {}

    public function getDsn(): string
    {
        return sprintf(
            '%s:host=%s;port=%ddbname=%s;charset=%s',
            $this->driver,
            $this->host,
            $this->port,
            $this->database,
            $this->charset

        );
    }

    public function getUser(): string { return $this->user; }
    public function getPassword(): string { return $this->password; }
}