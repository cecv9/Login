<?php
declare(strict_types=1);
namespace Enoc\Login\Dto;

final class CreateUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $role = 'user',
        public readonly ?string $confirm_password = null
    ) {}
}