<?php

namespace Enoc\Login\Controllers;

use Enoc\Login\Authorization\AuthorizationService;
use Enoc\Login\Http\Forms\UpdateUserForm;
use Enoc\Login\Http\Forms\CreateUserForm;
use Enoc\Login\Services\UserService;
use Enoc\Login\Services\Exceptions\EmailAlreadyExists;
use Enoc\Login\Services\Exceptions\UnauthorizedException;
use Enoc\Login\Services\Exceptions\ValidationException;
use Enoc\Login\Repository\UsuarioRepository;
use Enoc\Login\Core\PdoConnection;
use Enoc\Login\Enums\UserRole;

/**
 * 🎯 PROPÓSITO: Manejar requests HTTP del panel de administración
 *
 * RESPONSABILIDADES:
 * 1. Recibir datos HTTP (GET, POST)
 * 2. Validar CSRF
 * 3. Coordinar con Services
 * 4. Manejar errores y mostrar mensajes
 * 5. Retornar vistas
 *
 * NO HACE:
 * - Validación de datos (eso es del Form)
 * - Lógica de negocio (eso es del Service)
 * - Queries a BD (eso es del Repository)
 * - Decidir permisos (eso es del AuthorizationService)
 *
 * PRINCIPIO SOLID: Single Responsibility
 * Solo maneja la capa HTTP
 */
class AdminController extends BaseController
{
    private UsuarioRepository $repository;
    private UserService $userService;
    private AuthorizationService $auth;

    /**
     * 🏗️ CONSTRUCTOR
     *
     * DEPENDENCY INJECTION:
     * Recibimos PdoConnection y creamos las dependencias
     *
     * ¿POR QUÉ VERIFICAR SESIÓN AQUÍ?
     * - Protección global: TODAS las acciones requieren admin
     * - Si no fuera así, verificaríamos en cada método individualmente
     *
     * @param PdoConnection $pdoConnection
     */
    public function __construct(PdoConnection $pdoConnection)
    {
        // Instanciar dependencias
        $this->repository = new UsuarioRepository($pdoConnection);
        $this->userService = new UserService($this->repository);
        $this->auth = new AuthorizationService();

        // ══════════════════════════════════════════
        // VERIFICACIÓN DE SEGURIDAD GLOBAL
        // ══════════════════════════════════════════

        // REGLA: TODAS las acciones de este controller requieren ser admin

        // VALIDACIÓN 1: ¿Hay sesión activa?
        if (empty($_SESSION['user_id'])) {
            // Si no hay user_id en sesión = no autenticado
            header('Location: /login');
            exit('Authentication required');
        }

        // VALIDACIÓN 2: ¿Es admin?
        $userRole = $_SESSION['user_role'] ?? UserRole::USER;
        if ($userRole !== UserRole::ADMIN) {
            // Si no es admin, no puede estar aquí
            // Redirigir a home o mostrar 403
            header('Location: /');
            exit('Forbidden: Admin access required');
        }
    }

    // ══════════════════════════════════════════════════════════════
    // MÉTODO 1: Listar usuarios (GET /admin/users)
    // ══════════════════════════════════════════════════════════════

    /**
     * 📋 ACCIÓN: Mostrar lista paginada de usuarios
     *
     * RESPONSABILIDADES:
     * - Parsear parámetros de paginación
     * - Obtener usuarios del Repository
     * - Preparar datos para la vista
     * - Retornar vista
     *
     * URL: /admin/users
     * Método: GET
     */
    public function index(): string
    {
        // ══════════════════════════════════════════
        // PARSEAR PARÁMETROS DE PAGINACIÓN
        // ══════════════════════════════════════════

        // Límite de resultados (entre 1 y 100)
        // min() y max() aseguran que esté en rango válido
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));

        // Cursores de paginación
        // after = mostrar usuarios más antiguos que este ID
        // before = mostrar usuarios más nuevos que este ID
        $after  = isset($_GET['after'])  ? (int)$_GET['after']  : null;
        $before = isset($_GET['before']) ? (int)$_GET['before'] : null;

        // ══════════════════════════════════════════
        // OBTENER USUARIOS
        // ══════════════════════════════════════════

        if ($before !== null) {
            // Navegación hacia atrás (usuarios más nuevos)
            $users = $this->repository->findPageBefore($before, $limit);
        } else {
            // Navegación normal o hacia adelante
            $cursor = $after ?? PHP_INT_MAX;  // Si no hay cursor, empezar desde el más reciente
            $users = $this->repository->findPageAfter($cursor, $limit);
        }

        // ══════════════════════════════════════════
        // CALCULAR DATOS DE PAGINACIÓN
        // ══════════════════════════════════════════

        $has = !empty($users);

        // IDs de los usuarios en la página actual
        $firstId = $has ? $users[0]->getId() : null;  // Usuario más nuevo de la página
        $lastId  = $has ? $users[array_key_last($users)]->getId() : null;  // Usuario más antiguo de la página

        $isFirstPage = ($after === null && $before === null);

        // Verificar si hay más páginas
        $hasMoreOlder = $has && $lastId !== null
            ? $this->repository->hasMoreOlder((int)$lastId)
            : false;

        $hasMoreNewer = $has && $firstId !== null
            ? $this->repository->hasMoreNewer((int)$firstId)
            : false;

        // Mostrar botones de navegación solo si hay más datos
        $showNext = $has && $hasMoreOlder;  // Botón "Siguiente"
        $showPrev = $has && !$isFirstPage && $hasMoreNewer;  // Botón "Anterior"

        // ══════════════════════════════════════════
        // RETORNAR VISTA
        // ══════════════════════════════════════════

        return $this->view('admin.users.index', [
            'title'      => 'admin - Usuarios',
            'users'      => $users,
            'showNext'   => $showNext,
            'showPrev'   => $showPrev,
            'nextAfter'  => $lastId,    // Para construir URL: ?after=X
            'prevBefore' => $firstId,   // Para construir URL: ?before=X
            'limit'      => $limit,
            'csrfToken'  => $this->generateCsrfToken(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // MÉTODO 2: Formulario crear usuario (GET /admin/users/create)
    // ══════════════════════════════════════════════════════════════

    /**
     * ➕ ACCIÓN: Mostrar formulario para crear usuario
     *
     * RESPONSABILIDADES:
     * - Obtener roles que el usuario actual puede asignar
     * - Preparar datos de flash messages
     * - Preparar datos de prefill (si vienen de redirect)
     * - Retornar vista del formulario
     *
     * URL: /admin/users/create
     * Método: GET
     */
    public function create(): string
    {
        // ══════════════════════════════════════════
        // OBTENER USUARIO ACTUAL
        // ══════════════════════════════════════════

        $currentUserId = $_SESSION['user_id'] ?? null;
        $currentUser = $currentUserId
            ? $this->repository->findById((int)$currentUserId)
            : null;

        // ══════════════════════════════════════════
        // OBTENER ROLES ASIGNABLES
        // ══════════════════════════════════════════

        // PREGUNTA AL AUTHORIZATIONSERVICE:
        // "¿Qué roles puede asignar este usuario?"
        $assignableRoles = $this->auth->getAssignableRoles($currentUser);

        // Convertir a formato con labels para la vista
        // De: ['admin', 'facturador']
        // A: ['admin' => 'Administrador', 'facturador' => 'Facturador']
        $availableRoles = [];
        $allRolesWithLabels = UserRole::withLabels();
        foreach ($assignableRoles as $roleValue) {
            $availableRoles[$roleValue] = $allRolesWithLabels[$roleValue] ?? $roleValue;
        }

        // ══════════════════════════════════════════
        // FLASH MESSAGES
        // ══════════════════════════════════════════

        // Los flash messages se guardan en sesión y se consumen una sola vez
        // Patrón: Leer y luego eliminar

        $error = $_SESSION['error'] ?? '';
        unset($_SESSION['error']);

        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['success']);

        // ══════════════════════════════════════════
        // PREFILL (datos del intento anterior)
        // ══════════════════════════════════════════

        // Si hubo error de validación, pre-llenar el form
        // con lo que el usuario había escrito

        $name = $_SESSION['input_name'] ?? '';
        unset($_SESSION['input_name']);

        $email = $_SESSION['input_email'] ?? '';
        unset($_SESSION['input_email']);

        // ══════════════════════════════════════════
        // RETORNAR VISTA
        // ══════════════════════════════════════════

        return $this->view('admin.users.create', [
            'title' => 'admin - Crear Usuario',
            'csrfToken' => $this->generateCsrfToken(),
            'error' => $error,
            'success' => $success,
            'name' => $name,
            'email' => $email,
            'availableRoles' => $availableRoles,  // ⭐ Solo roles que puede asignar
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // MÉTODO 3: Crear usuario (POST /admin/users/create)
    // ══════════════════════════════════════════════════════════════

    /**
     * 💾 ACCIÓN: Procesar creación de usuario
     *
     * FLUJO (capas de validación):
     * 1. Validación CSRF (seguridad)
     * 2. Validación sintáctica (Form)
     * 3. Validación de negocio + Autorización (Service)
     * 4. Persistencia (Repository vía Service)
     *
     * URL: /admin/users/create
     * Método: POST
     */
    /**
     * Procesar creación de usuario (POST)
     */
    public function store(): string
    {
        // 1. Validación CSRF
        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
            $_SESSION['error'] = 'Token de seguridad inválido. Intenta nuevamente.';
            return $this->redirect('/admin/users/create');
        }

        // 2. Validación sintáctica (Form)
        $form = new CreateUserForm($_POST);
        $result = $form->handle();

        if (isset($result['errors'])) {
            $_SESSION['errors'] = $result['errors'];
            $_SESSION['input_name']  = $_POST['name'] ?? '';
            $_SESSION['input_email'] = strtolower($_POST['email'] ?? '');
            return $this->redirect('/admin/users/create');
        }

        /** @var \Enoc\Login\Dto\CreateUserDTO $dto */
        $dto = $result['dto'];

        // 3. Contexto de auditoría
        $audit = AuditContext::fromSession();

        // 4. Delegar al Service (validación de negocio + persistencia)
        try {
            $userId = $this->userService->create($dto, $audit);

            // Éxito
            $_SESSION['success'] = 'Usuario creado correctamente';
            $this->rotateCsrf();
            return $this->redirect('/admin/users');

        } catch (\Enoc\Login\Services\Exceptions\EmailAlreadyExists $e) {
            // Email duplicado
            $_SESSION['errors'] = ['email' => ['Ese email ya está registrado']];
            $_SESSION['input_name'] = $dto->name;
            $_SESSION['input_email'] = $dto->email;
            return $this->redirect('/admin/users/create');

        } catch (\Enoc\Login\Services\Exceptions\ValidationException $e) {
            // Validación de negocio
            $_SESSION['errors'] = $e->errors;
            $_SESSION['input_name'] = $dto->name;
            $_SESSION['input_email'] = $dto->email;
            return $this->redirect('/admin/users/create');

        } catch (\Throwable $e) {
            // Error genérico (ya logueado por el Service)
            $_SESSION['error'] = 'Error interno del servidor. Intenta nuevamente.';
            return $this->redirect('/admin/users/create');
        }
    }
    // ══════════════════════════════════════════════════════════════
    // MÉTODO 4: Formulario editar usuario (GET /admin/users/edit?id=X)
    // ══════════════════════════════════════════════════════════════

    /**
     * ✏️ ACCIÓN: Mostrar formulario para editar usuario
     *
     * URL: /admin/users/edit?id=123
     * Método: GET
     */
    public function edit(): string
    {
        // ══════════════════════════════════════════
        // OBTENER ID DEL USUARIO A EDITAR
        // ══════════════════════════════════════════

        $id = (int)($_GET['id'] ?? 0);
        $user = $this->repository->findById($id);

        // VALIDACIÓN: ¿Existe el usuario?
        if (!$user) {
            // Si no existe, redirigir a lista
            // (Podrías mostrar un 404 en vez de redirect)
            $_SESSION['error'] = 'Usuario no encontrado';
            return $this->redirect('/admin/users');
        }

        // ══════════════════════════════════════════
        // VERIFICAR PERMISOS
        // ══════════════════════════════════════════

        // Obtener usuario actual
        $currentUserId = $_SESSION['user_id'] ?? null;
        $currentUser = $currentUserId
            ? $this->repository->findById((int)$currentUserId)
            : null;

        // PREGUNTA: "¿Este usuario puede editar a ese usuario?"
        if (!$this->auth->can($currentUser, 'update', 'user', $user)) {
            // Si NO puede, denegar acceso
            $_SESSION['error'] = 'No tienes permisos para editar este usuario';
            return $this->redirect('/admin/users');
        }

        // ══════════════════════════════════════════
        // OBTENER ROLES ASIGNABLES
        // ══════════════════════════════════════════

        $assignableRoles = $this->auth->getAssignableRoles($currentUser);
        $availableRoles = [];
        $allRolesWithLabels = UserRole::withLabels();
        foreach ($assignableRoles as $roleValue) {
            $availableRoles[$roleValue] = $allRolesWithLabels[$roleValue] ?? $roleValue;
        }

        // ══════════════════════════════════════════
        // FLASH MESSAGES
        // ══════════════════════════════════════════

        $error = $_SESSION['error'] ?? '';
        unset($_SESSION['error']);

        $success = $_SESSION['success'] ?? '';
        unset($_SESSION['success']);

        // ══════════════════════════════════════════
        // RETORNAR VISTA
        // ══════════════════════════════════════════

        return $this->view('admin.users.edit', [
            'title' => 'admin - Editar Usuario',
            'user' => $user,  // Usuario a editar (pre-llena el form)
            'csrfToken' => $this->generateCsrfToken(),
            'error' => $error,
            'success' => $success,
            'availableRoles' => $availableRoles,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // MÉTODO 5: Actualizar usuario (POST /admin/users/edit)
    // ══════════════════════════════════════════════════════════════

    /**
     * 💾 ACCIÓN: Procesar actualización de usuario
     *
     * Similar a store() pero para edición
     *
     * URL: /admin/users/edit
     * Método: POST
     */
    /**
     * Procesar actualización de usuario (POST)
     */
    public function update(): string
    {
        // 1. Validación CSRF
        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
            $_SESSION['error'] = 'Token de seguridad inválido';
            return $this->redirect('/admin/users');
        }

        // 2. Validación sintáctica
        $form = new UpdateUserForm($_POST);
        $result = $form->handle();

        if (isset($result['errors'])) {
            $id = (int)($_POST['id'] ?? 0);
            $_SESSION['errors'] = $result['errors'];
            $_SESSION['input_name']  = $_POST['name']  ?? '';
            $_SESSION['input_email'] = strtolower($_POST['email'] ?? '');
            return $this->redirect("/admin/users/edit?id={$id}");
        }

        /** @var \Enoc\Login\Dto\UpdateUserDTO $dto */
        $dto = $result['dto'];

        // 3. Contexto de auditoría
        $audit = AuditContext::fromSession();

        // 4. Delegar al Service
        try {
            $this->userService->update($dto, $audit);

            // Éxito
            $_SESSION['success'] = 'Usuario actualizado correctamente';
            $this->rotateCsrf();
            return $this->redirect('/admin/users');

        } catch (\Enoc\Login\Services\Exceptions\EmailAlreadyExists $e) {
            $_SESSION['errors'] = ['email' => ['Ese email ya está registrado']];
            $_SESSION['input_name'] = $dto->name;
            $_SESSION['input_email'] = $dto->email;
            return $this->redirect("/admin/users/edit?id={$dto->id}");

        } catch (\Enoc\Login\Services\Exceptions\ValidationException $e) {
            $_SESSION['errors'] = $e->errors;
            $_SESSION['input_name'] = $dto->name;
            $_SESSION['input_email'] = $dto->email;
            return $this->redirect("/admin/users/edit?id={$dto->id}");

        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Error interno del servidor';
            return $this->redirect("/admin/users/edit?id={$dto->id}");
        }
    }


    /// Delete confirm
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