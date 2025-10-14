<?php
declare(strict_types=1);

namespace Enoc\Login\Controllers;

use Enoc\Login\Core\LogAnalyzer;
use Enoc\Login\Core\PdoConnection;

class AuditDashboardController extends BaseController
{
    private LogAnalyzer $analyzer;

    public function __construct(PdoConnection $pdoConnection){
        // BaseController no tiene constructor, así que no necesitas llamar parent::__construct()
        $logPath = $_ENV['LOG_PATH'] ?? dirname(__DIR__, 2) . '/logs';
        $this->analyzer = new LogAnalyzer($logPath);
    }

    private function sanitizeDate(?string $value): ?string{
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim($value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function ensureValidRange(string $start, string $end): bool
    {
        return $start <= $end;
    }

    private function redirectWithInvalidDate(string $target): void
    {
        $_SESSION['error'] = 'El formato de fecha proporcionado no es válido.';
        $this->redirect($target);
    }

    private function assertAdminAccess(): void
    {
        if (($_SESSION['user_role'] ?? null) === 'admin') {
            return;
        }

        $_SESSION['error'] = 'Acceso denegado';
        $target = empty($_SESSION['user_id']) ? '/login' : '/dashboard';
        $this->redirect($target);
    }

    /**
     * Vista principal del dashboard
     * GET /admin/audit
     */
    public function index(): string
    {
        $this->assertAdminAccess();

        $startInput = $_GET['start'] ?? null;
        $endInput = $_GET['end'] ?? null;

        $startDate = $this->sanitizeDate($startInput) ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $this->sanitizeDate($endInput) ?? date('Y-m-d');

        if (($startInput !== null && $this->sanitizeDate($startInput) === null) ||
            ($endInput !== null && $this->sanitizeDate($endInput) === null) ||
            !$this->ensureValidRange($startDate, $endDate)) {
            $this->redirectWithInvalidDate('/admin/audit');
        }
       

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
        $this->assertAdminAccess();

        $userId = (int)($_GET['id'] ?? 0);
        if ($userId <= 0) {
            $_SESSION['error'] = 'ID de usuario inválido';
            $this->redirect('/admin/audit');
        }

       $dateInput = $_GET['date'] ?? null;
        $date = $this->sanitizeDate($dateInput);

        if ($dateInput !== null && $date === null) {
            $this->redirectWithInvalidDate('/admin/audit/user?id=' . urlencode((string) $userId));
        }
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
        $this->assertAdminAccess();

        $targetId = (int)($_GET['id'] ?? 0);
        if ($targetId <= 0) {
            $_SESSION['error'] = 'ID de usuario inválido';
            $this->redirect('/admin/audit');
        }

        $startInput = $_GET['start'] ?? null;
        $endInput = $_GET['end'] ?? null;

        $start = $this->sanitizeDate($startInput) ?? date('Y-m-d', strtotime('-30 days'));
        $end = $this->sanitizeDate($endInput) ?? date('Y-m-d');

        if (($startInput !== null && $this->sanitizeDate($startInput) === null) ||
            ($endInput !== null && $this->sanitizeDate($endInput) === null) ||
            !$this->ensureValidRange($start, $end)) {
            $this->redirectWithInvalidDate('/admin/audit/history?id=' . urlencode((string) $targetId));
        }

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
        if (($_SESSION['user_role'] ?? null) !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acceso denegado']);
            exit;
        }

        $startInput = $_GET['start'] ?? null;
        $endInput = $_GET['end'] ?? null;

        $start = $this->sanitizeDate($startInput) ?? date('Y-m-d', strtotime('-7 days'));
        $end = $this->sanitizeDate($endInput) ?? date('Y-m-d');

        if (($startInput !== null && $this->sanitizeDate($startInput) === null) ||
            ($endInput !== null && $this->sanitizeDate($endInput) === null) ||
            !$this->ensureValidRange($start, $end)) {
            $this->redirectWithInvalidDate('/admin/audit');
        }

        $report = $this->analyzer->generateAuditReport($start, $end);

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="audit-report-' . $start . '-to-' . $end . '.json"');
        echo json_encode($report, JSON_PRETTY_PRINT);
        exit;
    }
}

// No necesitas definir render() ni redirect() porque ya están en BaseController