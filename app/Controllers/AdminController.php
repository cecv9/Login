<?php

namespace Enoc\Login\Controllers;

use Enoc\Login\Http\Forms\UpdateUserForm;
use Enoc\Login\Dto\UpdateUserDTO;
use Enoc\Login\Core\LogManager;
use Enoc\Login\Http\Forms\CreateUserForm;
use Enoc\Login\Services\UserService;
use Enoc\Login\Services\Exceptions\EmailAlreadyExists;
use Enoc\Login\Services\Exceptions\ValidationException;
use Enoc\Login\Core\PdoConnection;
use Enoc\Login\Repository\UsuarioRepository;
use Enoc\Login\models\Users;
use Enoc\Login\Traits\ValidatorTrait;

class AdminController extends BaseController
{
    private UsuarioRepository $repository;
    use ValidatorTrait;

    public function __construct(PdoConnection $pdoConnection)
    {
        $pdo = $pdoConnection->getPdo();
        $this->repository = new UsuarioRepository($pdoConnection);
        $this->userService = new UserService($this->repository);

        // Verificación adicional de seguridad
        if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') !== 'admin') {
            header('Location: /login');
            exit('Unauthorized access');
        }

    }

    // Listar users (con paginación básica)
    public function index(): string
    {
        $limit  = min(100, max(1, (int)($_GET['limit'] ?? 10)));
        $after  = isset($_GET['after'])  ? (int)$_GET['after']  : null; // id < after (más antiguos)
        $before = isset($_GET['before']) ? (int)$_GET['before'] : null; // id > before (más nuevos)

        if ($before !== null) {
            $users = $this->repository->findPageBefore($before, $limit);
        } else {
            $cursor = $after ?? PHP_INT_MAX; // interno, nunca lo enlaces
            $users  = $this->repository->findPageAfter($cursor, $limit);
        }

        $has = !empty($users);
        $firstId = $has ? $users[0]->getId() : null;                       // mayor id mostrado
        $lastId  = $has ? $users[array_key_last($users)]->getId() : null;  // menor id mostrado

        $isFirstPage = ($after === null && $before === null);

        // ---- Aquí usamos las funciones nuevas para evitar páginas vacías ----
        $hasMoreOlder = $has && $lastId  !== null ? $this->repository->hasMoreOlder((int)$lastId)   : false;
        $hasMoreNewer = $has && $firstId !== null ? $this->repository->hasMoreNewer((int)$firstId) : false;

        // “Siguiente” solo si realmente hay más antiguos
        $showNext = $has && $hasMoreOlder;

        // “Anterior” solo si no es la primera página y realmente hay más nuevos
        $showPrev = $has && !$isFirstPage && $hasMoreNewer;

        return $this->view('admin.users.index', [
            'title'      => 'admin - Usuarios',
            'users'      => $users,
            'showNext'   => $showNext,
            'showPrev'   => $showPrev,
            'nextAfter'  => $lastId,    // para ?after=
            'prevBefore' => $firstId,   // para ?before=
            'limit'      => $limit,
            'csrfToken'  => $this->generateCsrfToken(),
        ]);
    }



    // Form create
    public function create(): string
    {
        // Flashes
        $error = $_SESSION['error'] ?? ''; unset($_SESSION['error']);
        $success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);

        // Para prefill si redirect back (SRP: Controller pasa input sanitizado)
        $name = $_SESSION['input_name'] ?? ''; unset($_SESSION['input_name']);
        $email = $_SESSION['input_email'] ?? ''; unset($_SESSION['input_email']);

        return $this->view('admin.users.create', [
            'title' => 'admin - Crear Usuario',
            'csrfToken' => $this->generateCsrfToken(),
            'error' => $error,
            'success' => $success,
            'name' => $name,  // Prefill
            'email' => $email // Prefill
        ]);
    }

    // Store (POST create)
    public function store(): string {
        // CSRF igual...
        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
            $_SESSION['error'] = 'Token inválido';
            return $this->redirect('/admin/users/create');
        }


        // 1) Form (usa tu trait vía CreateUserForm)
        $form   = new CreateUserForm($_POST, $this->repository);
        $result = $form->handle();

        if (isset($result['errors'])) {
            $_SESSION['errors']      = $result['errors'];
            $_SESSION['input_name']  = $_POST['name']  ?? '';
            $_SESSION['input_email'] = strtolower($_POST['email'] ?? '');
            return $this->redirect('/admin/users/create');
        }

        $dto = $result['dto'];

        // 2) Autorización defensiva para admin
        if ($dto->role === 'admin' && (($_SESSION['user_role'] ?? 'user') !== 'admin')) {
            $_SESSION['error']       = 'No autorizado para crear administradores.';
            $_SESSION['input_name']  = $dto->name;
            $_SESSION['input_email'] = $dto->email;
            return $this->redirect('/admin/users/create');
        }

        // 3) Service
        try {
            $userId = $this->userService->create($dto);
            $_SESSION['success'] = 'Usuario creado exitosamente (#'.$userId.')';
            unset($_SESSION['input_name'], $_SESSION['input_email']);
            $this->rotateCsrf();
            return $this->redirect('/admin/users');
        } catch (ValidationException $e) {
            $_SESSION['errors']      = $e->errors;
            $_SESSION['input_name']  = $dto->name;
            $_SESSION['input_email'] = $dto->email;
            return $this->redirect('/admin/users/create');
        } catch (EmailAlreadyExists $e) {
            $_SESSION['errors']      = ['email' => 'Ese email ya está registrado.'];
            $_SESSION['input_name']  = $dto->name;
            $_SESSION['input_email'] = $dto->email;
            return $this->redirect('/admin/users/create');
        } catch (\Throwable $e) {
            LogManager::error('AdminController::store error: '.$e->getMessage());
            $_SESSION['error']       = 'Error interno…';
            $_SESSION['input_name']  = $dto->name ?? ($_POST['name'] ?? '');
            $_SESSION['input_email'] = $dto->email ?? (strtolower($_POST['email'] ?? ''));
            return $this->redirect('/admin/users/create');
        }

    }

    // Form edit
    public function edit(): string {
        $id = (int)($_GET['id'] ?? 0);
        $user = $this->repository->findById($id);
        if (!$user) return $this->redirect('/admin/users');  // 404 simple
        $error = $_SESSION['error'] ?? ''; unset($_SESSION['error']);  // ← NUEVO: Flashes
        $success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);

        return $this->view('admin.users.edit', [
            'title' => 'admin - Editar Usuario',
            'user' => $user,
            'csrfToken' => $this->generateCsrfToken(),
            'error' => $error,
            'success' => $success
        ]);
    }

    // Update (POST)
    public function update(): string {
        // CSRF + validaciones similares a store
        // CSRF
        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
            $_SESSION['error'] = 'Token inválido';
            return $this->redirect('/admin/users');
        }

        // Form (valida + normaliza + arma DTO)
        $form = new UpdateUserForm($_POST, $this->repository);
        $result = $form->handle();
        $id = (int)($_POST['id'] ?? 0);

        if (isset($result['errors'])) {
            $_SESSION['errors'] = $result['errors'];
            $_SESSION['input_name']  = $_POST['name']  ?? '';
            $_SESSION['input_email'] = strtolower($_POST['email'] ?? '');
            return $this->redirect("/admin/users/edit?id={$id}");
        }

        /** @var UpdateUserDTO $dto */
        $dto = $result['dto'];

        // Autorización extra: solo admin puede subir rol a admin
        if ($dto->role === 'admin' && (($_SESSION['user_role'] ?? 'user') !== 'admin')) {
            $_SESSION['error'] = 'No autorizado para asignar rol administrador.';
            $_SESSION['input_name']  = $dto->name;
            $_SESSION['input_email'] = $dto->email;
            return $this->redirect("/admin/users/edit?id={$dto->id}");
        }

        try {
            $ok = $this->userService->update($dto);
            if (!$ok) {
                $_SESSION['error'] = 'Error al actualizar';
                return $this->redirect("/admin/users/edit?id={$dto->id}");
            }
            $_SESSION['success'] = 'Usuario actualizado!';
            $this->rotateCsrf();
            return $this->redirect('/admin/users');
        } catch (ValidationException $e) {
            $_SESSION['errors'] = $e->errors;
            $_SESSION['input_name']  = $dto->name;
            $_SESSION['input_email'] = $dto->email;
            return $this->redirect("/admin/users/edit?id={$dto->id}");
        } catch (EmailAlreadyExists $e) {
            $_SESSION['errors'] = ['email' => 'Ese email ya está registrado.'];
            $_SESSION['input_name']  = $dto->name;
            $_SESSION['input_email'] = $dto->email;
            return $this->redirect("/admin/users/edit?id={$dto->id}");
        } catch (\Throwable $e) {
            LogManager::error('AdminController::update error: '.$e->getMessage());
            $_SESSION['error'] = 'Error interno…';
            return $this->redirect("/admin/users/edit?id={$dto->id}");
        }
    }

    // Delete confirm
    public function delete(): string
    {
        $id = (int)($_GET['id'] ?? 0);
        $user = $this->repository->findById($id);
        if (!$user) return $this->redirect('/admin/users');
       // Flashes (SRP: Controller maneja estado temporal)
    $error = $_SESSION['error'] ?? ''; unset($_SESSION['error']);
    $success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);  // En caso de redirect back

        return $this->view('admin.users.delete', [  // Ruta: admin.users.delete
            'title' => 'admin - Borrar Usuario',
            'user' => $user,
            'csrfToken' => $this->generateCsrfToken(),
            'error' => $error,
            'success' => $success
        ]);
    }

    // Destroy (POST)
    public function destroy(): string
    {
        // CSRF (ya lo tienes)
        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
            $_SESSION['error'] = 'Token inválido';
            return $this->redirect('/admin/users/delete?id=' . ($_POST['id'] ?? ''));  // Back con ID
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = 'ID inválido';
            return $this->redirect('/admin/users');
        }

        // Chequea si existe (opcional, pero SRP: Valida antes de repo)
        $user = $this->repository->findById($id);
        if (!$user) {
            $_SESSION['error'] = 'Usuario no encontrado';
            return $this->redirect('/admin/users');
        }

        // Service soft-delete
        if ($this->userService->delete($id)) {
            $_SESSION['success'] = 'Usuario borrado exitosamente';
            $this->rotateCsrf();
        } else {
            $_SESSION['error'] = 'Error al borrar usuario';
        }

        return $this->redirect('/admin/users');
    }
}