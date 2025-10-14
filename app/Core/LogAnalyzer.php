<?php
declare(strict_types=1);

namespace Enoc\Login\Core;

/**
 * Analizador de logs de auditoría
 * Útil para reportes de seguridad y compliance
 */
final class LogAnalyzer
{
    private string $logPath;

    public function __construct(string $logPath)
    {
        $realPath = realpath($logPath);

        if ($realPath === false) {
            if (!is_dir($logPath) && !@mkdir($logPath, 0775, true)) {
                throw new \InvalidArgumentException('Ruta de logs inválida');
            }

            $realPath = realpath($logPath);
        }

        if ($realPath === false || !is_dir($realPath)) {
            throw new \InvalidArgumentException('Ruta de logs inválida');
        }

        $this->logPath = $realPath;
    }

    /**
     * Obtiene todas las acciones de un usuario específico
     */
    public function getUserActions(int $userId, ?string $date = null): array
    {
        $logFile = $this->getLogFile($date);
        if (!file_exists($logFile)) {
            return [];
        }

        $actions = [];
       

        foreach ($this->readLogLines($logFile) as $line){
            if (preg_match('/\[.*?\] \[.*?\] (.*) (\{.*\})/', $line, $matches)) {
                $context = json_decode($matches[2], true);

                if (isset($context['actor_user_id']) && (int)$context['actor_user_id'] === $userId) {
                    $actions[] = [
                        'message' => $matches[1],
                        'context' => $context,
                        'timestamp' => $context['timestamp'] ?? null
                    ];
                }
            }
        }

        return $actions;
    }

    /**
     * Obtiene el historial de cambios de un usuario objetivo
     */
    public function getTargetUserHistory(int $targetUserId, ?string $startDate = null, ?string $endDate = null): array
    {
        $history = [];
        $dates = $this->getDateRange($startDate, $endDate);

        foreach ($dates as $date) {
            $logFile = $this->getLogFile($date);
            if (!file_exists($logFile)) {
                continue;
            }

            

           foreach ($this->readLogLines($logFile) as $line) {
                if (preg_match('/\[.*?\] \[(.*?)\] (.*) (\{.*\})/', $line, $matches)) {
                    $level = $matches[1];
                    $message = $matches[2];
                    $context = json_decode($matches[3], true);

                    if (isset($context['target_user_id']) && (int)$context['target_user_id'] === $targetUserId) {
                        $history[] = [
                            'level' => $level,
                            'message' => $message,
                            'context' => $context,
                            'date' => $date
                        ];
                    }
                }
            }
        }

        return $history;
    }

    /**
     * Detecta actividad sospechosa (múltiples intentos fallidos)
     */
    public function detectSuspiciousActivity(?string $date = null): array
    {
        $logFile = $this->getLogFile($date);
        if (!file_exists($logFile)) {
            return [];
        }

        $attempts = [];
        

       foreach ($this->readLogLines($logFile) as $line) {
            if (str_contains($line, '[WARNING]') || str_contains($line, '[ERROR]')) {
                if (preg_match('/\[.*?\] \[.*?\] (.*) (\{.*\})/', $line, $matches)) {
                    $context = json_decode($matches[2], true);

                    if (isset($context['ip_address'])) {
                        $ip = $context['ip_address'];
                        $attempts[$ip] = ($attempts[$ip] ?? 0) + 1;
                    }
                }
            }
        }

        // Filtrar IPs con más de 5 intentos fallidos
        return array_filter($attempts, fn($count) => $count > 5);
    }

    /**
     * Genera reporte de auditoría
     */
    public function generateAuditReport(string $startDate, string $endDate): array
    {
        $report = [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'totalEvents' => 0,
            'uniqueUsers' => 0,
            'modifications' => 0,
            'failedAttempts' => 0,
            'topUsers' => [],
            'recentEvents' => [],
            'byAction' => [],
            'byUser' => []
        ];

        $dates = $this->getDateRange($startDate, $endDate);
        $userSet = [];
        $userActions = [];
        $allEvents = [];

        foreach ($dates as $date) {
            $logFile = $this->getLogFile($date);
            if (!file_exists($logFile)) {
                continue;
            }

           

             foreach ($this->readLogLines($logFile) as $line)  {
                if (preg_match('/\[(.*?)\] \[(.*?)\] (.*?) (\{.*\})/', $line, $matches)) {
                    $timestamp = $matches[1];
                    $level = $matches[2];
                    $message = $matches[3];
                    $contextJson = $matches[4];

                    $context = json_decode($contextJson, true);
                    if (!$context) {
                        continue;
                    }

                    // Si tiene un campo 'action', es una operación completada (exitosa)
                    $hasAction = isset($context['action']);

                    // Contar evento total solo si tiene action (operaciones completadas)
                    if ($hasAction) {
                        $report['totalEvents']++;
                    }

                    // Rastrear usuarios únicos
                    if (isset($context['actor_user_id'])) {
                        $userId = $context['actor_user_id'];
                        $userSet[$userId] = true;

                        if (!isset($userActions[$userId])) {
                            $userActions[$userId] = [
                                'userId' => $userId,
                                'username' => $context['actor_username'] ?? "User $userId",
                                'count' => 0,
                                'lastActivity' => $timestamp
                            ];
                        }

                        // Solo contar si tiene action (operación completada)
                        if ($hasAction) {
                            $userActions[$userId]['count']++;
                            $userActions[$userId]['lastActivity'] = $timestamp;
                        }
                    }

                    // Procesar acciones completadas
                    if ($hasAction) {
                        $action = $context['action'];
                        $report['byAction'][$action] = ($report['byAction'][$action] ?? 0) + 1;

                        // Contar modificaciones (UPDATE y DELETE)
                        if (in_array($action, ['USER_UPDATED', 'USER_DELETED'])) {
                            $report['modifications']++;
                        }

                        // Agregar a eventos recientes
                        // Una operación con 'action' es exitosa, independiente del nivel
                        $allEvents[] = [
                            'timestamp' => $timestamp,
                            'userId' => $context['actor_username'] ?? ($context['actor_user_id'] ?? 'N/A'),
                            'action' => $action,
                            'target' => $context['target_email'] ?? ($context['target_user_id'] ?? '-'),
                            'success' => true // Si tiene action, es exitosa
                        ];
                    }

                    // Contar intentos fallidos: WARNING/ERROR SIN campo 'action'
                    // Esto captura intentos bloqueados, validaciones fallidas, etc.
                    if (in_array($level, ['WARNING', 'ERROR']) && !$hasAction) {
                        $report['failedAttempts']++;
                    }
                }
            }
        }

        $report['uniqueUsers'] = count($userSet);

        usort($userActions, fn($a, $b) => $b['count'] <=> $a['count']);
        $report['topUsers'] = array_slice($userActions, 0, 10);

        usort($allEvents, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
        $report['recentEvents'] = $allEvents;

        return $report;
    }

    private function getLogFile(?string $date = null): string
    {
       $normalizedDate = $this->normalizeDate($date);

        return $this->logPath . DIRECTORY_SEPARATOR . $normalizedDate . '.log';
    }

    private function getDateRange(?string $startDate, ?string $endDate): array
    {
        $start = $startDate ? strtotime($startDate) : strtotime('-7 days');
        $end = $endDate ? strtotime($endDate) : time();

        $dates = [];
        for ($i = $start; $i <= $end; $i += 86400) {
            $dates[] = date('Y-m-d', $i);
        }

        return $dates;
    }

      private function normalizeDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return date('Y-m-d');
        }

        $trimmed = trim($date);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            throw new \InvalidArgumentException('Formato de fecha inválido');
        }

        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d', $trimmed);

        if ($dateTime === false) {
            throw new \InvalidArgumentException('Fecha inválida');
        }

        return $dateTime->format('Y-m-d');
    }

    /**
     * @return iterable<int, string>
     */
    private function readLogLines(string $logFile): iterable
    {
        $file = new \SplFileObject($logFile, 'r');

        while (!$file->eof()) {
            $line = $file->fgets();

            if ($line === false) {
                break;
            }

            yield rtrim($line, "\r\n");
        }
    }

}