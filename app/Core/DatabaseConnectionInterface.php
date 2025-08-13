<?php
namespace Enoc\Login\Core;

use PDO;

interface DatabaseConnectionInterface{
    public function getPdo(): PDO;
}
