<?php
declare(strict_types=1);
namespace Enoc\Login\Http\Forms;

use Enoc\Login\Dto\UpdateUserDTO;
use Enoc\Login\Enums\UserRole;
use Enoc\Login\Traits\ValidatorTrait;

/**
 * RESPONSABILIDAD: Solo validación sintáctica
 * NO consulta la base de datos
 */
final class UpdateUserForm
{
    use ValidatorTrait;

    public function __construct(private array $post) {}

    /** @return array{dto?: UpdateUserDTO, errors?: array} */
    public function handle(): array
    {
        // Normalización
        $id    = (int)($this->post['id'] ?? 0);
        $name  = trim((string)($this->post['name'] ?? ''));
        $email = strtolower(trim((string)($this->post['email'] ?? '')));
        $pass  = (string)($this->post['password'] ?? '');
        $cpass = (string)($this->post['confirm_password'] ?? '');
        $role  = trim((string)($this->post['role'] ?? 'user'));

        if (!UserRole::exists($role)) {
            $role = UserRole::USER; // Fallback seguro
        }

        $data = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'password' => $pass,
            'confirm_password' => $cpass,
            'role' => $role,
        ];

        // ✅ SOLO validaciones de SINTAXIS
        $rules = [
            'name'  => ['required', 'min:2'],
            'email' => ['required', 'email'],
            'role'  => ['required', 'in:user,admin'],
        ];

        // Si se proporciona contraseña, validar formato
        if ($pass !== '') {
            $rules['password'] = ['min:6'];
            $rules['confirm_password'] = ['min:6', 'match:password'];
        }

        $errors = $this->validateUserData($data, $rules);

        // Validación adicional: ID debe ser válido
        if ($id <= 0) {
            $errors['id'][] = 'ID de usuario inválido';
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        return ['dto' => new UpdateUserDTO(
            id: $id,
            name: $name,
            email: $email,
            password: $pass !== '' ? $pass : null,
            role: $role
        )];
    }
}