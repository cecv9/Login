<?php
declare(strict_types=1);

namespace Enoc\Login\Services\Exceptions;

final class EmailAlreadyExists extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('EMAIL_EXISTS');
    }
}
