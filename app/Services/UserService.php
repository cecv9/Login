<?php
declare(strict_types=1);

namespace Enoc\Login\Services;

use Enoc\Login\Dto\UpdateUserDTO;
use Enoc\Login\Dto\CreateUserDTO;
use Enoc\Login\Repository\UsuarioRepository;
use Enoc\Login\Services\Exceptions\ValidationException;
use Enoc\Login\Services\Exceptions\EmailAlreadyExists;
use Enoc\Login\Traits\ValidatorTrait;
use PDOException;

final class UserService
{
    use ValidatorTrait;

    public function __construct(private readonly UsuarioRepository $repository) {}

    public function create(CreateUserDTO $dto): int
    {
        // Revalidación defensiva (puedes quitar si confías 100% en el Form)
        $data = [
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
            'confirm_password' => $dto->confirm_password ?? $dto->password,
            'role' => $dto->role,
        ];
        $rules = [
            'name'             => ['required','min:2'],
            'email'            => ['required','email','unique'],
            'password'         => ['required','min:6'],
            'confirm_password' => ['required','min:6','match:password'],
            'role'             => ['required','in:user,admin'],
        ];
        $errors = $this->validateUserData($data, $rules);
        if ($errors) {
            throw new ValidationException($errors);
        }

        // Unicidad fuerte (por si hay carrera)
        if ($this->repository->findByEmail($dto->email)) {
            throw new EmailAlreadyExists();
        }

        // Hash
        $passwordHash = password_hash($dto->password, PASSWORD_DEFAULT);

        // Persistir
        try {
            return $this->repository->createUserHashed(
                email: $dto->email,
                name:  $dto->name,
                password_hash: $passwordHash,
                role:  $dto->role
            );
        } catch (PDOException $e) {
            if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
                throw new EmailAlreadyExists();
            }
            throw $e;
        }
    }
    public function update(UpdateUserDTO $dto): bool
    {
        // Validación defensiva (puedes omitir si confías 100% en el Form)
        $data = [
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password ?? '',
            'confirm_password' => $dto->password ?? '', // si viene password, ya lo validó el form
            'role' => $dto->role,
        ];
        $rules = [
            'name'  => ['required','min:2'],
            'email' => ['required','email',"unique:id={$dto->id}"],
            'role'  => ['required','in:user,admin'],
        ];
        if ($dto->password !== null && $dto->password !== '') {
            $rules['password'] = ['min:6'];
            $rules['confirm_password'] = ['min:6','match:password'];
        }

        $errors = $this->validateUserData($data, $rules);
        if ($errors) {
            throw new ValidationException($errors);
        }

        // Cargar usuario (existe?)
        $user = $this->repository->findById($dto->id);
        if (!$user) {
            throw new \RuntimeException('USER_NOT_FOUND');
        }

        // Si hay cambio de password, hashear
        $hash = null;
        if ($dto->password !== null && $dto->password !== '') {
            $hash = password_hash($dto->password, PASSWORD_DEFAULT);
        }

        // Persistir
        try {
            return $this->repository->updateUserHashed(
                id: $dto->id,
                name: $dto->name,
                email: $dto->email,
                password_hash: $hash, // null => no cambia
                role: $dto->role
            );
        } catch (\PDOException $e) {
            if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
                throw new EmailAlreadyExists();
            }
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $user = $this->repository->findById($id);
        if (!$user) return false;
        return $this->repository->softDeleteUser($id);
    }


}
