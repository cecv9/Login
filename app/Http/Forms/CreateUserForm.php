<?php
declare(strict_types=1);
namespace Enoc\Login\Http\Forms;

use Enoc\Login\Dto\CreateUserDTO;
use Enoc\Login\Repository\UsuarioRepository;
use Enoc\Login\Traits\ValidatorTrait;

final class CreateUserForm
{
    use ValidatorTrait;

    public function __construct(
        private array $post,
        private UsuarioRepository $repository
    ) {}

    /** @return array{dto?: CreateUserDTO, errors?: array} */
    public function handle(): array
    {
        $name  = trim((string)($this->post['name'] ?? ''));
        $email = strtolower(trim((string)($this->post['email'] ?? '')));
        $pass  = (string)($this->post['password'] ?? '');
        $cpass = (string)($this->post['confirm_password'] ?? '');
        $role  = trim((string)($this->post['role'] ?? 'user'));
        if (!in_array($role, ['user','admin'], true)) $role = 'user';

        $data = [
            'name' => $name,
            'email' => $email,
            'password' => $pass,
            'confirm_password' => $cpass,
            'role' => $role,
        ];

        // el trait requiere $this->repository -> ya está inyectado
        $rules = [
            'name'             => ['required','min:2'],
            'email'            => ['required','email','unique'],
            'password'         => ['required','min:6'],
            'confirm_password' => ['required','min:6','match:password'],
            'role'             => ['required','in:user,admin'],
        ];

        $errors = $this->validateUserData($data, $rules);
        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        return ['dto' => new CreateUserDTO(
            name: $name,
            email: $email,
            password: $pass,
            role: $role,
            confirm_password: $cpass
        )];
    }
}