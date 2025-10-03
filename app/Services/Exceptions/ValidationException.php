<?php
declare(strict_types=1);

namespace Enoc\Login\Services\Exceptions;

final class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('VALIDATION_ERROR');
    }
}
