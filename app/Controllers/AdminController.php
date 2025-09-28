<?php

namespace Enoc\Login\Controllers;

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
        $this->repository = new UsuarioRepository($pdoConnection);
        // ← Chequea auth + role (middleware-like)
        if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') !== 'admin') {
            $this->redirect('/login');
        }
    }

    // Listar users (con paginación básica)
    public function index(): string
    {
        $limit = 10;
        $page = (int)($_GET['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        $users = $this->repository->findAllUsers($limit, $offset);  // Implementa en repo
        $total = $this->repository->countUsers();  // Para paginación
        $pages = ceil($total / $limit);

        return $this->view('admin.users.index', [
            'title' => 'admin - Usuarios',
            'users' => $users,
            'page' => $page,
            'pages' => $pages,
            'csrfToken' => $this->generateCsrfToken()  // Si usas CSRF en forms
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
    public function store(): string
    {
        // CSRF igual...
        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
            $_SESSION['error'] = 'Token inválido';
            return $this->redirect('/admin/users/create');
        }

        // Extrae datos (trim ya en trait)
        $name = $_POST['name'] ?? '';
        $email = strtolower($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';  // ← FIX: snake_case
        $role = trim($_POST['role'] ?? '');  // ← FIX: Trim primero, sin default aquí (default en form)
        if (empty($role)) $role = 'user';  // ← Default solo si vacío post-trim
        // ← LIMPIO: Solo trait – define reglas una vez
        $rules = [
            'name' => ['required', 'min:2'],
            'email' => ['required', 'email', 'unique'],  // Pre-repo chequeo duplicado
            'password' => ['required', 'min:6'],
            'confirm_password' => ['required', 'min:6', 'match:password'],  // Bidireccional en trait
            'role' => ['required', 'in:user,admin']
        ];

        $data = compact('name', 'email', 'password', 'confirm_password', 'role');  // ← FIX: Keys consistentes
        $errors = $this->validateUserData($data, $rules);

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;  // Por campo para UX
            // Prefill (solo campos no erróneos, pero simple: todos)
            $_SESSION['input_name'] = $name;
            $_SESSION['input_email'] = $email;
            return $this->redirect('/admin/users/create');
        }

        // Repo (sin validación extra – trait ya hizo 'unique')
        $userId = $this->repository->createUser($email, $name, $password, $role);

        if ($userId) {
            $_SESSION['success'] = 'Usuario creado exitosamente';
            // Limpia prefill en success
            unset($_SESSION['input_name'], $_SESSION['input_email']);
        } else {
            $_SESSION['error'] = 'Error interno al crear usuario (contacta admin).';  // Genérico, ya que trait filtró comunes
            $_SESSION['input_name'] = $name;
            $_SESSION['input_email'] = $email;
            return $this->redirect('/admin/users/create');
        }

        return $this->redirect('/admin/users');
    }

    // Form edit
    public function edit(): string
    {
        $id = (int)($_GET['id'] ?? 0);
        $user = $this->repository->findById($id);
        if (!$user) return $this->redirect('/admin/users');  // 404 simple
        $error = $_SESSION['error'] ?? ''; unset($_SESSION['error']);  // ← NUEVO: Flashes
        $success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);

        return $this->view('admin.users.edit', [
            'title' => 'admin - Editar Usuario',
            'user' => $user,
            'csrfToken' => $this->generateCsrfToken()
        ]);
    }

    // Update (POST)
    public function update(): string {
        // CSRF + validaciones similares a store
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $role = $_POST['role'] ?? 'user';
        $password = $_POST['password'] ?? null;  // Opcional

        // ... validaciones ...
        $password = $_POST['password'] ?? null;
        $confirmPassword = $_POST['confirm_password'] ?? null;
        if ($password && $password !== $confirmPassword) {
            $errors[] = 'Contraseñas no coinciden';
        }
        if ($password && strlen($password) < 6) {
            $errors[] = 'Contraseña mínima 6 chars';
        }

        if ($this->repository->updateUser($id, $name, $email, $password, $role)) {
            $_SESSION['success'] = 'Usuario actualizado!';
        } else {
            $_SESSION['error'] = 'Error al actualizar';
        }

        return $this->redirect('/admin/users/');
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

        if ($this->repository->deleteUser($id)) {  // Ya soft-delete
            $_SESSION['success'] = 'Usuario borrado exitosamente';
        } else {
            $_SESSION['error'] = 'Error al borrar usuario';
        }

        return $this->redirect('/admin/users');  // Back a lista con flash
    }
}