<?php
declare(strict_types=1);
namespace Enoc\Login\Http\Forms;

use Enoc\Login\Dto\CreateUserDTO;
use Enoc\Login\Traits\ValidatorTrait;
use Enoc\Login\Enums\UserRole;

/**
 * RESPONSABILIDAD:
 * - Validar formato/sintaxis de entrada del usuario
 * - Normalizar datos (trim, lowercase)
 * - Crear DTO si es válido
 *
 * NO valida reglas de negocio (unique, exists, permisos, etc.)
 */
final class CreateUserForm
{
    use ValidatorTrait;

    public function __construct(private array $post) {}

    /** @return array{dto?: CreateUserDTO, errors?: array} */
    public function handle(): array
    {
        // Normalización
        $name  = trim((string)($this->post['name'] ?? ''));
        $email = strtolower(trim((string)($this->post['email'] ?? '')));
        $pass  = (string)($this->post['password'] ?? '');
        $cpass = (string)($this->post['confirm_password'] ?? '');
        $role  = trim((string)($this->post['role'] ?? 'user'));

        // ← VALIDACIÓN ROBUSTA
        if (!UserRole::exists($role)) {
            $role = UserRole::USER; // Fallback seguro
        }

        $data = [
            'name' => $name,
            'email' => $email,
            'password' => $pass,
            'confirm_password' => $cpass,
            'role' => $role,
        ];

        // ✅ SOLO validaciones de SINTAXIS
        $rules = [
            'name'             => ['required', 'min:2'],
            'email'            => ['required', 'email'],
            'password'         => ['required', 'min:6'],
            'confirm_password' => ['required', 'min:6', 'match:password'],
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