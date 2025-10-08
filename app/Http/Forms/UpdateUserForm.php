<?php
declare(strict_types=1);

namespace Enoc\Login\Http\Forms;

use Enoc\Login\Dto\UpdateUserDTO;
use Enoc\Login\Enums\UserRole;
use Enoc\Login\Traits\ValidatorTrait;

/**
 * 🎯 RESPONSABILIDAD: Validación sintáctica para EDICIÓN
 *
 * DIFERENCIAS CON CreateUserForm:
 * 1. Requiere ID del usuario
 * 2. Contraseña es OPCIONAL (solo si quieren cambiarla)
 * 3. Retorna UpdateUserDTO en vez de CreateUserDTO
 */
final class UpdateUserForm
{
    use ValidatorTrait;

    public function __construct(private array $post) {}

    public function handle(): array
    {
        // ══════════════════════════════════════════
        // NORMALIZACIÓN (igual que create)
        // ══════════════════════════════════════════

        $id    = (int)($this->post['id'] ?? 0);  // ⭐ DIFERENCIA: Necesita ID
        $name  = trim((string)($this->post['name'] ?? ''));
        $email = strtolower(trim((string)($this->post['email'] ?? '')));
        $pass  = (string)($this->post['password'] ?? '');
        $cpass = (string)($this->post['confirm_password'] ?? '');
        $role  = trim((string)($this->post['role'] ?? UserRole::USER));

        // ══════════════════════════════════════════
        // VALIDACIÓN DE SEGURIDAD (igual que create)
        // ══════════════════════════════════════════

        if (!UserRole::exists($role)) {
            $role = UserRole::USER;
        }

        $data = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'password' => $pass,
            'confirm_password' => $cpass,
            'role' => $role,
        ];

        // ══════════════════════════════════════════
        // REGLAS DE VALIDACIÓN
        // ══════════════════════════════════════════

        $rules = [
            'name'  => ['required', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255'],

            // ⭐ CAMBIO CRÍTICO: Igual que CreateUserForm
            'role'  => ['required', 'in:' . UserRole::forValidation()],
        ];

        // ⭐ DIFERENCIA: Contraseña OPCIONAL
        // Solo validar si el usuario la envió
        if ($pass !== '') {
            // Si envió contraseña, validarla
            $rules['password'] = ['min:6', 'max:255'];
            $rules['confirm_password'] = ['min:6', 'match:password'];
        }
        // Si NO envió contraseña, no validarla
        // (significa que no quiere cambiarla)

        $errors = $this->validateUserData($data, $rules);

        // ══════════════════════════════════════════
        // VALIDACIÓN ADICIONAL: ID válido
        // ══════════════════════════════════════════

        if ($id <= 0) {
            // ID debe ser positivo
            $errors['id'][] = 'ID de usuario inválido';
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        // ══════════════════════════════════════════
        // CREAR DTO
        // ══════════════════════════════════════════

        return ['dto' => new UpdateUserDTO(
            id: $id,
            name: $name,
            email: $email,
            password: $pass !== '' ? $pass : null,  // ⭐ null si no cambió
            role: $role
        )];
    }
}