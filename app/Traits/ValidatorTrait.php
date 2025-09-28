<?php
declare(strict_types=1);

namespace Enoc\Login\Traits;

use Enoc\Login\Repository\UsuarioRepository;

trait ValidatorTrait
{
    /**
     * Valida datos de usuario con reglas escalables.
     * @param array $data Datos POST (name, email, password, etc.)
     * @param array $rules Reglas por campo: ['email' => ['required', 'email', 'unique']]
     * @return array Errores (campo => mensaje)
     */
    private function validateUserData(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = trim($data[$field] ?? '');

            foreach ($fieldRules as $rule) {
                switch ($rule) {
                    case 'required':
                        if (empty($value)) {
                            $fieldName = ucfirst(str_replace('_', ' ', $field));
                            $errors[$field][] = $fieldName . ' es requerido';
                        }
                        break;
                    case 'min:2':
                        if (strlen($value) < 2) {
                            $fieldName = ucfirst(str_replace('_', ' ', $field));
                            $errors[$field][] = $fieldName . ' mínimo 2 caracteres';
                        }
                        break;
                    case 'min:6':
                        if (strlen($value) < 6) {
                            $fieldName = ucfirst(str_replace('_', ' ', $field));
                            $errors[$field][] = $fieldName . ' mínimo 6 caracteres';
                        }
                        break;
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = 'Email inválido';
                        }
                        break;
                    case 'unique':
                        if (isset($this->repository)) {
                            $existing = $this->repository->findByEmail($value);
                            if ($existing) {
                                $errors[$field][] = 'Email ya registrado';
                            }

                        }
                        $ruleParts = explode(':', $rule);  // ['unique', 'id=123']
                        $exceptId = null;
                        if (count($ruleParts) > 1 && strpos($ruleParts[1], 'id=') === 0) {
                            $exceptId = (int)substr($ruleParts[1], 3);  // Extrae 123
                        }
                        $existing = $this->repository->findByEmail($value);
                        if ($existing && $existing->getId() !== $exceptId) {  // Skip si es el mismo ID
                            $errors[$field][] = 'Email ya registrado';
                        }

                        break;
                    case 'in:user,admin':  // ← FIX: Case específico para 'in' – no general 'in:'
                        // Corta 'in:' → 'user,admin'
                        $allowedStr = substr($rule, 3);  // 'user,admin'
                        $allowed = array_map('trim', explode(',', $allowedStr));  // ['user', 'admin']
                        if (!in_array($value, $allowed)) {
                            $errors[$field][] = 'Rol inválido';
                        }
                        break;
                    case 'match:':  // ← Tu case intacto
                        $targetField = explode(':', $rule)[1];
                        $targetValue = trim($data[$targetField] ?? '');
                        if ($value !== $targetValue) {
                            $fieldName = ucfirst(str_replace('_', ' ', $field));
                            $targetName = ucfirst(str_replace('_', ' ', $targetField));
                            $errors[$field][] = $fieldName . ' no coincide con ' . $targetName;
                        }
                        // Bidireccional...
                        if (isset($rules[$targetField]) && in_array("match:$field", $rules[$targetField])) {
                            // Ya chequeado, OK
                        }
                        break;
                    default:
                        error_log("Regla desconocida: $rule para campo $field");
                        break;
                }
            }
        }

        return $errors;
    }
}