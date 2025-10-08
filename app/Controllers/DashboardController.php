<?php

namespace Enoc\Login\Controllers;

class DashboardController extends BaseController
{
    public function show(): string
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }

        $userName = $_SESSION['user_name'] ?? 'Usuario';
        $userEmail = $_SESSION['user_email'] ?? '';
        $csrfToken = $_SESSION['csrf_token'] ?? '';

        return $this->view('dashboard.dashboard', [
            'title' => 'Dashboard',
            'userName' => $userName,
            'userEmail' => $userEmail,
            'userRole' => $_SESSION['user_role'] ?? 'user',
            'csrfToken' => $csrfToken,
        ]);
    }
}