<?php

namespace Enoc\Login\Controllers;

class AuthController extends BaseController
{
    /**
     * Mostrar formulario de login
     */
    public function showLogin(): string
    {
        return $this->view('auth.login', [
            'title' => 'Iniciar Sesión',
            'error' => $_SESSION['error'] ?? null
        ]);
    }

    /**
     * Procesar login
     */
    public function processLogin(): string
    {
        // Limpiar errores anteriores
        unset($_SESSION['error']);

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
        session_destroy();
        $this->redirect('/login');
    }

}