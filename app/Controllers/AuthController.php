<?php

namespace Enoc\Login\Controllers;

class AuthController extends BaseController
{
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
        $password = $this->getPost('password');

        // Validaciones básicas
        if (empty($email) || empty($password)) {
            $_SESSION['error'] = 'Email y contraseña son requeridos';
            return $this->redirect('/login');
        }

        // Aquí validarías contra la base de datos
        // Por ahora, usuario de prueba
        if ($email === 'admin@test.com' && $password === '123456') {
            session_regenerate_id(true); //xd
            $_SESSION['user_id'] = 1;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = 'Administrador';

            return $this->redirect('/dashboard');
        }

        $_SESSION['error'] = 'Credenciales incorrectas';
        return $this->redirect('/login');
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




}