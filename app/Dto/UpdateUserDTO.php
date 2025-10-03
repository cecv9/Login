<?php
declare(strict_types=1);

namespace Enoc\Login\Dto;

final class UpdateUserDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        // password puede venir vacío (no cambio) o con valor (cambiar)
        public readonly ?string $password,
        public readonly string $role
    ) {}
}
