<?php
declare(strict_types=1);

namespace Enoc\Login\Http\Forms;

use Enoc\Login\Dto\UpdateUserDTO;
use Enoc\Login\Traits\ValidatorTrait;
use Enoc\Login\Repository\UsuarioRepository;

final class UpdateUserForm
{
    use ValidatorTrait;

    public function __construct(
        private array $post,
        private UsuarioRepository $repository
    ) {}

    /** @return array{dto?: UpdateUserDTO, errors?: array} */
    public function handle(): array
    {
        $id    = (int)($this->post['id'] ?? 0);
        $name  = trim((string)($this->post['name'] ?? ''));
        $email = strtolower(trim((string)($this->post['email'] ?? '')));
        $pass  = (string)($this->post['password'] ?? '');
        $cpass = (string)($this->post['confirm_password'] ?? '');
        $role  = trim((string)($this->post['role'] ?? 'user'));
        if (!in_array($role, ['user','admin'], true)) $role = 'user';

        // Reglas: password opcional; unique con excepción del propio ID
        $rules = [
            'name'             => ['required','min:2'],
            'email'            => ['required','email',"unique:id={$id}"],
            'password'         => $pass !== '' ? ['min:6'] : [], // si viene, validar
            'confirm_password' => $pass !== '' ? ['min:6','match:password'] : [],
            'role'             => ['required','in:user,admin'],
        ];
        $data = [
            'name' => $name,
            'email' => $email,
            'password' => $pass,
            'confirm_password' => $cpass,
            'role' => $role,
        ];

        // El trait usa $this->repository para 'unique'
        $errors = $this->validateUserData($data, $rules);
        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        return ['dto' => new UpdateUserDTO(
            id: $id,
            name: $name,
            email: $email,
            password: ($pass === '' ? null : $pass),
            role: $role
        )];
    }
}
