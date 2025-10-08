<?php

declare(strict_types=1);

namespace Enoc\Login\Controllers;

use Enoc\Login\Core\PdoConnection;
use Enoc\Login\Repository\UsuarioRepository;
use Enoc\Login\models\Users;
use Enoc\Login\Traits\ValidatorTrait;
use Enoc\Login\Core\LogManager;



class AuthController extends BaseController{

    private UsuarioRepository $repository;
    use ValidatorTrait;

    public function __construct (PdoConnection $pdoConnection){
        $this->repository = new UsuarioRepository($pdoConnection);

    }

    /**
     * Get client IP address considering proxies
     * @return string IP address
     */
    private function getClientIp(): string
    {
        // Check for proxy headers (in order of trust)
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
            'HTTP_X_FORWARDED_FOR',     // Standard proxy header
            'HTTP_X_REAL_IP',           // Nginx proxy
            'REMOTE_ADDR'               // Direct connection
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated list (X-Forwarded-For can have multiple IPs)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0'; // Fallback
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
    public function processLogin(): string {
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
        $password = trim($password);

        // Validaciones básicas
        if (empty($email) || empty($password)) {
            $_SESSION['error'] = 'Email y contraseña son requeridos';
            return $this->redirect('/login');
        }

        // Rate limiting: Check both email and IP
        $clientIp = $this->getClientIp();
        $isEmailLimited = $this->repository->isRateLimited($email, 'login', 5, 15);
        $isIpLimited = $this->repository->isRateLimited($clientIp, 'login', 10, 15);

        if ($isEmailLimited || $isIpLimited) {
            $_SESSION['error'] = 'Demasiados intentos fallidos. Por favor, intenta de nuevo en 15 minutos.';
            LogManager::logWarning("Rate limit exceeded for login", [
                'email' => $email,
                'ip' => $clientIp
            ]);
            return $this->redirect('/login');
        }

        // Buscar usuario en BD
        $user = $this->repository->findByEmail($email);

        if (!$user || !password_verify($password, $user->getPassword())) {
            // Record failed attempt for both email and IP
            $this->repository->recordFailedAttempt($email, 'login', $clientIp);
            $this->repository->recordFailedAttempt($clientIp, 'login', $clientIp);
            
            $_SESSION['error'] = 'Credenciales incorrectas';
            LogManager::logWarning("Failed login attempt", ['email' => $email, 'ip' => $clientIp]);
            return $this->redirect('/login');
        } else {
            // Login exitoso: Clear failed attempts and regenerate session
            $this->repository->clearFailedAttempts($email, 'login');
            $this->repository->clearFailedAttempts($clientIp, 'login');
            
            session_regenerate_id(true);
            $this->rotateCsrf();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['user_email'] = $user->getEmail();
            $_SESSION['user_name'] = $user->getName();
            $_SESSION['user_role'] = $user->getRole();
            
            LogManager::logInfo("Successful login", ['email' => $email]);
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

        // Validate CSRF token using hash_equals for timing-safe comparison
        $submittedToken = $_POST['csrf_token'] ?? null;
        
        if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
            http_response_code(400);
            exit('Invalid CSRF token');
        }

        // Destroy session securely
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

        $name = $_SESSION['input_name'] ?? ''; unset($_SESSION['input_name']);
        $email = $_SESSION['input_email'] ?? ''; unset($_SESSION['input_email']);

        return $this->view('auth.register', [
            'title' => 'Registro de Usuario',
            'error' => $error,
            'success' => $success,
            'csrfToken' => $_SESSION['csrf_token'] ?? '',  // Pasa CSRF
            'name' => $name,
            'email' => $email
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

        // Rate limiting: Check both email and IP
        $clientIp = $this->getClientIp();
        $isEmailLimited = $this->repository->isRateLimited($email, 'register', 5, 15);
        $isIpLimited = $this->repository->isRateLimited($clientIp, 'register', 10, 15);

        if ($isEmailLimited || $isIpLimited) {
            $_SESSION['register_error'] = 'Demasiados intentos de registro. Por favor, intenta de nuevo en 15 minutos.';
            LogManager::logWarning("Rate limit exceeded for registration", [
                'email' => $email,
                'ip' => $clientIp
            ]);
            return $this->redirect('/register');
        }

        // ← TRAIT: Reglas sin role
        $rules = [
            'name' => ['required', 'min:2'],
            'email' => ['required', 'email', 'unique'],  // Duplicado pre-repo
            'password' => ['required', 'min:6'],
            'confirmPassword' => ['required', 'min:6', 'match:password']
        ];

        $data = compact('name', 'email', 'password', 'confirmPassword');
        $errors = $this->validateUserData($data, $rules);  // ← LLAMA TRAIT

        if (!empty($errors)) {
            // Record failed registration attempt
            $this->repository->recordFailedAttempt($email, 'register', $clientIp);
            $this->repository->recordFailedAttempt($clientIp, 'register', $clientIp);
            
            $_SESSION['register_errors'] = $errors;
            $_SESSION['input_name'] = $name;
            $_SESSION['input_email'] = $email;
            return $this->redirect('/register');
        }

        // Repo (default role 'user')
        $userId = $this->repository->createUser($name, $email, $password, 'user');

        if ($userId) {
            // Clear failed attempts on successful registration
            $this->repository->clearFailedAttempts($email, 'register');
            $this->repository->clearFailedAttempts($clientIp, 'register');
            
            $_SESSION['register_success'] = 'Usuario creado exitosamente. <a href="/login">Inicia sesión</a>';
            $this->rotateCsrf();
            LogManager::logInfo("Successful registration", ['email' => $email]);
            return $this->redirect('/register');
        } else {
            // Record failed attempt
            $this->repository->recordFailedAttempt($email, 'register', $clientIp);
            $this->repository->recordFailedAttempt($clientIp, 'register', $clientIp);
            
            $_SESSION['register_error'] = 'Error al crear usuario (inténtalo de nuevo).';
            $_SESSION['input_name'] = $name;
            $_SESSION['input_email'] = $email;
            return $this->redirect('/register');
        }
    }




}