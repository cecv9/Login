<?php
declare(strict_types=1);

namespace Enoc\Login\Traits;

use Enoc\Login\Enums\UserRole;  // ← Sin 'S'

trait ValidatorTrait
{
    private function validateUserData(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $raw = $data[$field] ?? '';
            $value = is_string($raw) ? trim($raw) : (string)$raw;

            if ($field === 'email') {
                $value = strtolower($value);
            }

            foreach ($fieldRules as $rule) {
                if ($rule === 'required') {
                    if ($value === '') {
                        $errors[$field][] = self::label($field) . ' es requerido';
                    }
                    continue;
                }
                if ($rule === 'email') {
                    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = 'Email inválido';
                    }
                    continue;
                }
                if (str_starts_with($rule, 'min:')) {
                    $min = (int)substr($rule, 4);
                    if (mb_strlen($value) < $min) {
                        $errors[$field][] = self::label($field) . " mínimo {$min} caracteres";
                    }
                    continue;
                }
                if (str_starts_with($rule, 'max:')) {
                    $max = (int)substr($rule, 4);
                    if (mb_strlen($value) > $max) {
                        $errors[$field][] = self::label($field) . " máximo {$max} caracteres";
                    }
                    continue;
                }
                if (str_starts_with($rule, 'in:')) {
                    if ($field === 'role') {
                        $allowed = UserRole::all();  // ← Sin 'S'
                    } else {
                        $allowed = array_map('trim', explode(',', substr($rule, 3)));
                    }
                    if (!in_array($value, $allowed, true)) {
                        $errors[$field][] = self::label($field) . ' inválido';
                    }
                    continue;
                }
                if (str_starts_with($rule, 'match:')) {
                    $targetField = substr($rule, 6);
                    $targetValue = isset($data[$targetField]) ? trim((string)$data[$targetField]) : '';
                    if ($value !== $targetValue) {
                        $errors[$field][] = self::label($field) . ' no coincide con ' . self::label($targetField);
                    }
                    continue;
                }
                if ($rule === 'unique' || str_starts_with($rule, 'unique:')) {
                    $exceptId = null;
                    if (str_starts_with($rule, 'unique:')) {
                        $after = substr($rule, 7);
                        if (str_starts_with($after, 'id=')) {
                            $exceptId = (int)substr($after, 3);
                        }
                    }
                    if (isset($this->repository) && method_exists($this->repository, 'findByEmail')) {
                        if ($value !== '') {
                            $existing = $this->repository->findByEmail($value);
                            if ($existing) {
                                $existingId = null;
                                if (is_array($existing) && isset($existing['id'])) {
                                    $existingId = (int)$existing['id'];
                                } elseif (is_object($existing) && method_exists($existing, 'getId')) {
                                    $existingId = (int)$existing->getId();
                                }
                                $same = ($exceptId !== null && $existingId === $exceptId);
                                if (!$same) {
                                    $errors[$field][] = 'Email ya registrado';
                                }
                            }
                        }
                    }
                    continue;
                }
            }
        }

        return $errors;
    }

    private static function label(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }
}