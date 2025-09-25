<?php

declare(strict_types=1);

namespace Enoc\Login\Controllers;

use Enoc\Login\Core\PdoConnection;
use Enoc\Login\Repository\UsuarioRepository;
use Enoc\Login\models\Users;



class AuthController extends BaseController{

    private UsuarioRepository $repository;

    public function __construct (PdoConnection $pdoConnection){
        $this->repository = new UsuarioRepository($pdoConnection);

    }


    /**
     * Mostrar formulario de login
     */
    public function showLogin(): string
    {
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['error']);
        return $this->view('auth.login', [
            'title' => 'Iniciar Sesión',
            'error' => $error
        ]);
    }

    /**
     * Procesar login
     */
    public function processLogin(): string
    {
        // Limpiar errores anteriores
        unset($_SESSION['error']);

        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
            http_response_code(400);
            exit('Invalid CSRF token');
        }

        // Obtener datos del formulario
        $email = $this->getPost('email');
        $email=trim(strtolower($email));
        $password = $this->getPost('password');
        $password = trim($password);  // ← FIX: Elimina espacios leading/trailing

        // Validaciones básicas
        if (empty($email) || empty($password)) {
            $_SESSION['error'] = 'Email y contraseña son requeridos';
            return $this->redirect('/login'); //http://localhost/login
        }

        // Aquí validarías contra la base de datos
         // Por ahora, usuario de prueba
       //if ($email === 'admin@test.com' && $password === '123456') {
        //session_regenerate_id(true); //xd
          //$_SESSION['user_id'] = 1;
           //$_SESSION['user_email'] = $email;
            //$_SESSION['user_name'] = 'Administrador';

             //return $this->redirect('/dashboard');
       //}

        // Lógica real: Buscar usuario en BD


        $user = $this->repository->findByEmail($email);

// DEBUG TEMPORAL - QUITAR EN PROD
        // DEBUG TEMPORAL - QUITAR EN PROD
        //if ($user) {
            //$fetchedHash = $user->getPassword();
            //$verifyResult = password_verify($password, $fetchedHash);
            //echo "<pre style='background: #f0f0f0; padding: 10px; border:1px solid #ccc;'>";
            //echo "DEBUG LOGIN:\n";
            //echo "- Email buscado: $email\n";
            //echo "- ID: " . $user->getId() . "\n";
            //echo "- Email fetched: " . $user->getEmail() . "\n";
            //echo "- Hash fetched (length): " . strlen($fetchedHash) . " chars\n";
            //echo "- Hash preview: " . substr($fetchedHash, 0, 20) . "...\n";
            //echo "- Password input EXACT (length): '" . addslashes($password) . "' (" . strlen($password) . " chars)\n";  // ← NUEVO: Muestra full con escapes
            //echo "- Verify result: " . ($verifyResult ? 'TRUE ✅' : 'FALSE ❌') . "\n";
           // echo "</pre>";
           // exit;
      //  } else {
         //   echo "<pre>DEBUG: User null</pre>";
           // exit;
       // }









































        // var_dump($email, $user ? $user->getPassword() : 'User null'); // Debug
        if (!$user || !password_verify($password, $user->getPassword())) {
            $_SESSION['error'] = 'Credenciales incorrectas xd';
            return $this->redirect('/login');
        }else{
            // Login exitoso: Regenerar sesión y guardar datos
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['user_email'] = $user->getEmail();
            $_SESSION['user_name'] = $user->getName();  // Usa getName() si el modelo lo tiene
            return $this->redirect('/dashboard');
        }











    }

    /**
     * Cerrar sesión
     */
    public function logout(): void
    {

        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';

        if ($requestMethod !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Method Not Allowed');
        }

       // $submittedToken = $_POST['csrf_token'] ?? '';
       // $sessionToken = $_SESSION['csrf_token'] ?? null;
        $submittedToken = $_POST['csrf_token'] ?? null;

       // if (!is_string($sessionToken) || $sessionToken === '' ||
         //   !is_string($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
           // http_response_code(400);
            //exit('Invalid CSRF token');
        //}
        if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
            http_response_code(400);
            exit('Invalid CSRF token');
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_unset();
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }

        $this->redirect('/login');
    }


    /**
     * Mostrar formulario de registro
     */
    public function showRegister(): string
    {
        $error = $_SESSION['register_error'] ?? null;
        unset($_SESSION['register_error']);
        $success = $_SESSION['register_success'] ?? null;
        unset($_SESSION['register_success']);

        return $this->view('auth.register', [
            'title' => 'Registro de Usuario',
            'error' => $error,
            'success' => $success,
            'csrfToken' => $_SESSION['csrf_token'] ?? ''  // Pasa CSRF
        ]);
    }

    /**
     * Procesar registro
     */
    public function processRegister(): string
    {
        unset($_SESSION['register_error'], $_SESSION['register_success']);

        // Validar CSRF
        $submittedToken = $_POST['csrf_token'] ?? null;
        if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
            http_response_code(400);
            exit('Invalid CSRF token');
        }

        // Obtener y sanitizar datos
        $name = trim($this->getPost('name', ''));
        $email = trim(strtolower($this->getPost('email', '')));
        $password = $this->getPost('password', '');
        $confirmPassword = $this->getPost('confirm_password', '');

        // Validaciones (SRP: Controller valida input)
        $errors = [];
        if (empty($name) || strlen($name) < 2) {
            $errors[] = 'Nombre requerido (mín. 2 chars)';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email válido requerido';
        }
        if (empty($password) || strlen($password) < 6) {
            $errors[] = 'Contraseña mínima 6 chars';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Contraseñas no coinciden';
        }

        if (!empty($errors)) {
            $_SESSION['register_error'] = implode('<br>', $errors);
            return $this->redirect('/register');
        }

        // Insert via repo
        $userId = $this->repository->createUser($email, $password, $name);
        if ($userId) {
            $_SESSION['register_success'] = 'Usuario creado exitosamente. <a href="/login">Inicia sesión</a>';
            return $this->redirect('/register');  // O directo a login
        } else {
            $_SESSION['register_error'] = 'Error al crear usuario (email ya existe?).';
            return $this->redirect('/register');
        }
    }




}