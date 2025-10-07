<?php
declare(strict_types=1);

namespace Enoc\Login\Controllers;

use Enoc\Login\Core\LogAnalyzer;
use Enoc\Login\Core\PdoConnection;

class AuditDashboardController extends BaseController
{
    private LogAnalyzer $analyzer;

    public function __construct(PdoConnection $pdoConnection)
    {
        // BaseController no tiene constructor, así que no necesitas llamar parent::__construct()
        $logPath = $_ENV['LOG_PATH'] ?? dirname(__DIR__, 2) . '/logs';
        $this->analyzer = new LogAnalyzer($logPath);
    }

    /**
     * Vista principal del dashboard
     * GET /admin/audit
     */
    public function index(): string
    {
        // Solo administradores
        if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
            $_SESSION['error'] = 'Acceso denegado';
            $this->redirect('/admin');
        }

        $startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $_GET['end'] ?? date('Y-m-d');

        $report = $this->analyzer->generateAuditReport($startDate, $endDate);
        $suspicious = $this->analyzer->detectSuspiciousActivity($endDate);

        // Usar view() con notación de puntos, igual que AdminController
        return $this->view('admin.audit.index', [
            'report' => $report,
            'suspicious' => $suspicious,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }

    /**
     * Ver acciones de un usuario específico
     * GET /admin/audit/user?id=X&date=Y
     */
    public function userActions(): string
    {
        if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
            $_SESSION['error'] = 'Acceso denegado';
            $this->redirect('/admin');
        }

        $userId = (int)($_GET['id'] ?? 0);
        if ($userId <= 0) {
            $_SESSION['error'] = 'ID de usuario inválido';
            $this->redirect('/admin/audit');
        }

        $date = $_GET['date'] ?? null;
        $actions = $this->analyzer->getUserActions($userId, $date);

        return $this->view('admin.audit.user-actions', [
            'userId' => $userId,
            'actions' => $actions,
            'date' => $date ?? date('Y-m-d')
        ]);
    }

    /**
     * Ver historial de un usuario objetivo
     * GET /admin/audit/history?id=X&start=Y&end=Z
     */
    public function userHistory(): string
    {
        if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
            $_SESSION['error'] = 'Acceso denegado';
            $this->redirect('/admin');
        }

        $targetId = (int)($_GET['id'] ?? 0);
        if ($targetId <= 0) {
            $_SESSION['error'] = 'ID de usuario inválido';
            $this->redirect('/admin/audit');
        }

        $start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $end = $_GET['end'] ?? date('Y-m-d');

        $history = $this->analyzer->getTargetUserHistory($targetId, $start, $end);

        return $this->view('admin.audit.user-history', [
            'targetId' => $targetId,
            'history' => $history,
            'startDate' => $start,
            'endDate' => $end
        ]);
    }

    /**
     * Exportar reporte a JSON
     * GET /admin/audit/export?start=X&end=Y
     */
    public function export(): void
    {
        if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acceso denegado']);
            exit;
        }

        $start = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
        $end = $_GET['end'] ?? date('Y-m-d');

        $report = $this->analyzer->generateAuditReport($start, $end);

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="audit-report-' . $start . '-to-' . $end . '.json"');
        echo json_encode($report, JSON_PRETTY_PRINT);
        exit;
    }
}

// No necesitas definir render() ni redirect() porque ya están en BaseController