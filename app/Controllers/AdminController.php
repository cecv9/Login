<?php

namespace Enoc\Login\Controllers;

use Enoc\Login\Core\PdoConnection;
use Enoc\Login\Repository\UsuarioRepository;
use Enoc\Login\models\Users;

class AdminController extends BaseController
{
    private UsuarioRepository $repository;

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
        return $this->view('admin.users.create', [
            'title' => 'admin - Crear Usuario',
            'csrfToken' => $this->generateCsrfToken()
        ]);
    }

    // Store (POST create)
    public function store(): string
    {
        // Validar CSRF si lo tienes
        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
            $_SESSION['error'] = 'Token inválido';
            return $this->redirect('/admin/users/create');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';

        // Validaciones (similar a register)
        $errors = [];
        if (empty($name) || strlen($name) < 2) $errors[] = 'Nombre inválido';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
        if (strlen($password) < 6) $errors[] = 'Password mínimo 6 chars';
        if (!in_array($role, ['user', 'admin'])) $errors[] = 'Rol inválido';

        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            return $this->redirect('/admin/users/create');
        }

        $userId = $this->repository->createUser($email, $name, $password,$role);  // Usa el existente, pero agrega role si lo extiendes
        if ($userId) {
            // Update role si separas
           // $this->repository->updateUserRole($userId, $role);
            $_SESSION['success'] = 'Usuario creado!';
        } else {
            $_SESSION['error'] = 'Error al crear';
        }

        return $this->redirect('/admin/users');
    }

    // Form edit
    public function edit(): string
    {
        $id = (int)($_GET['id'] ?? 0);
        $user = $this->repository->findById($id);
        if (!$user) return $this->redirect('/admin/users');  // 404 simple

        return $this->view('admin.users.edit', [
            'title' => 'admin - Editar Usuario',
            'user' => $user,
            'csrfToken' => $this->generateCsrfToken()
        ]);
    }

    // Update (POST)
    public function update(): string
    {
        // CSRF + validaciones similares a store
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $role = $_POST['role'] ?? 'user';
        $password = $_POST['password'] ?? null;  // Opcional

        // ... validaciones ...

        if ($this->repository->updateUser($id, $name, $email, $password, $role)) {
            $_SESSION['success'] = 'Usuario actualizado!';
        } else {
            $_SESSION['error'] = 'Error al actualizar';
        }

        return $this->redirect('/admin/users');
    }

    // Delete confirm
    public function delete(): string
    {
        $id = (int)($_GET['id'] ?? 0);
        $user = $this->repository->findById($id);
        if (!$user) return $this->redirect('/admin/users');

        return $this->view('admin.users.delete', [
            'title' => 'admin - Borrar Usuario',
            'user' => $user,
            'csrfToken' => $this->generateCsrfToken()
        ]);
    }

    // Destroy (POST)
    public function destroy(): string
    {
        // CSRF
        $id = (int)($_POST['id'] ?? 0);
        if ($this->repository->deleteUser($id)) {
            $_SESSION['success'] = 'Usuario borrado!';
        } else {
            $_SESSION['error'] = 'Error al borrar';
        }

        return $this->redirect('/admin/users');
    }
}