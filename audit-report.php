#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Script CLI para generar reportes de auditoría
 *
 * Uso:
 *   php audit-report.php user-actions --user-id=1
 *   php audit-report.php user-history --target-id=42
 *   php audit-report.php suspicious-activity
 *   php audit-report.php generate-report --start=2025-10-01 --end=2025-10-04
 */

require __DIR__ . '/vendor/autoload.php';

use Enoc\Login\Core\LogAnalyzer;

// ===== FUNCIÓN MEJORADA PARA PARSEAR ARGUMENTOS =====
function parseArguments(array $argv): array
{
    $command = $argv[1] ?? 'help';
    $options = [];

    // Iterar sobre todos los argumentos después del comando
    for ($i = 2; $i < count($argv); $i++) {
        $arg = $argv[$i];

        // Verificar si es un argumento con formato --clave=valor
        if (preg_match('/^--([a-z-]+)=(.+)$/', $arg, $matches)) {
            $key = $matches[1];
            $value = $matches[2];
            $options[$key] = $value;

            echo "DEBUG: Parseado argumento '$key' = '$value'\n"; // Debug temporal
        }
    }

    return [$command, $options];
}

// ===== PARSEAR ARGUMENTOS DE FORMA SEGURA =====
[$command, $options] = parseArguments($argv);

echo "DEBUG: Comando recibido: $command\n"; // Debug temporal
echo "DEBUG: Opciones: " . json_encode($options) . "\n\n"; // Debug temporal

// Configuración
$logPath = $_ENV['LOG_PATH'] ?? __DIR__ . '/logs';
$analyzer = new LogAnalyzer($logPath);

// Ejecutar comando
match($command) {
    'user-actions' => showUserActions($analyzer, $options),
    'user-history' => showUserHistory($analyzer, $options),
    'suspicious-activity' => showSuspiciousActivity($analyzer, $options),
    'generate-report' => generateReport($analyzer, $options),
    default => showHelp()
};

// ========== FUNCIONES ==========

function showUserActions(LogAnalyzer $analyzer, array $options): void
{
    if (!isset($options['user-id'])) {
        echo "❌ Error: Se requiere --user-id\n";
        echo "Ejemplo: php audit-report.php user-actions --user-id=1\n";
        exit(1);
    }

    $userId = (int)$options['user-id'];
    $date = $options['date'] ?? null;

    echo "📊 Acciones del usuario ID: {$userId}\n";
    echo str_repeat('=', 60) . "\n\n";

    $actions = $analyzer->getUserActions($userId, $date);

    if (empty($actions)) {
        echo "No se encontraron acciones para este usuario.\n";
        echo "Verifica que:\n";
        echo "  1. El usuario con ID {$userId} existe\n";
        echo "  2. Hay archivos de log en: " . (__DIR__ . '/logs') . "\n";
        echo "  3. El usuario ha realizado acciones que se hayan logueado\n";
        return;
    }

    foreach ($actions as $action) {
        $timestamp = $action['context']['timestamp'] ?? 'N/A';
        $targetEmail = $action['context']['target_email'] ?? 'N/A';
        $actionType = $action['context']['action'] ?? 'N/A';

        echo "⏰ {$timestamp}\n";
        echo "📧 Usuario: {$action['context']['actor_username']}\n";
        echo "🎯 Acción: {$actionType}\n";
        echo "📨 Email objetivo: {$targetEmail}\n";
        echo "🌐 IP: {$action['context']['ip_address']}\n";
        echo str_repeat('-', 60) . "\n";
    }

    echo "\nTotal: " . count($actions) . " acciones\n";
}

function showUserHistory(LogAnalyzer $analyzer, array $options): void
{
    if (!isset($options['target-id'])) {
        echo "❌ Error: Se requiere --target-id\n";
        echo "Ejemplo: php audit-report.php user-history --target-id=42\n";
        exit(1);
    }

    $targetId = (int)$options['target-id'];
    $start = $options['start'] ?? null;
    $end = $options['end'] ?? null;

    echo "📜 Historial de cambios del usuario ID: {$targetId}\n";
    echo str_repeat('=', 60) . "\n\n";

    $history = $analyzer->getTargetUserHistory($targetId, $start, $end);

    if (empty($history)) {
        echo "No se encontró historial para este usuario.\n";
        return;
    }

    foreach ($history as $entry) {
        $level = $entry['level'];
        $emoji = match($level) {
            'INFO' => '✅',
            'WARNING' => '⚠️',
            'ERROR' => '❌',
            default => 'ℹ️'
        };

        echo "{$emoji} [{$level}] {$entry['date']}\n";
        echo "   {$entry['message']}\n";

        if (isset($entry['context']['actor_username'])) {
            echo "   👤 Por: {$entry['context']['actor_username']}\n";
        }

        if (isset($entry['context']['old_email'], $entry['context']['target_email'])) {
            echo "   📧 Email: {$entry['context']['old_email']} → {$entry['context']['target_email']}\n";
        }

        echo str_repeat('-', 60) . "\n";
    }

    echo "\nTotal: " . count($history) . " eventos\n";
}

function showSuspiciousActivity(LogAnalyzer $analyzer, array $options): void
{
    $date = $options['date'] ?? null;

    echo "🔍 Actividad sospechosa detectada\n";
    echo str_repeat('=', 60) . "\n\n";

    $suspicious = $analyzer->detectSuspiciousActivity($date);

    if (empty($suspicious)) {
        echo "✅ No se detectó actividad sospechosa.\n";
        return;
    }

    echo "⚠️ IPs con múltiples intentos fallidos:\n\n";

    foreach ($suspicious as $ip => $count) {
        echo "🚨 IP: {$ip}\n";
        echo "   Intentos fallidos: {$count}\n";
        echo str_repeat('-', 60) . "\n";
    }
}

function generateReport(LogAnalyzer $analyzer, array $options): void
{
    $start = $options['start'] ?? date('Y-m-d', strtotime('-7 days'));
    $end = $options['end'] ?? date('Y-m-d');

    echo "📊 Reporte de Auditoría\n";
    echo "Período: {$start} a {$end}\n";
    echo str_repeat('=', 60) . "\n\n";

    $report = $analyzer->generateAuditReport($start, $end);

    echo "📈 Resumen General:\n";
    echo "   Total de acciones: {$report['total_actions']}\n\n";

    if (!empty($report['by_action'])) {
        echo "📋 Por tipo de acción:\n";
        arsort($report['by_action']);
        foreach ($report['by_action'] as $action => $count) {
            echo "   {$action}: {$count}\n";
        }
        echo "\n";
    }

    if (!empty($report['by_user'])) {
        echo "👥 Por usuario:\n";
        arsort($report['by_user']);
        $top5 = array_slice($report['by_user'], 0, 5, true);
        foreach ($top5 as $userId => $count) {
            echo "   Usuario ID {$userId}: {$count} acciones\n";
        }
        echo "\n";
    }

    // Guardar reporte en JSON
    $reportFile = __DIR__ . "/logs/audit-report-{$start}-to-{$end}.json";
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
    echo "💾 Reporte guardado en: {$reportFile}\n";
}

function showHelp(): void
{
    echo <<<HELP
🔧 Herramienta de Análisis de Auditoría

Uso:
  php audit-report.php <comando> [opciones]

Comandos:
  user-actions           Ver todas las acciones de un usuario
  user-history           Ver historial de cambios de un usuario
  suspicious-activity    Detectar actividad sospechosa
  generate-report        Generar reporte completo

Opciones:
  --user-id=<id>        ID del usuario actor
  --target-id=<id>      ID del usuario objetivo
  --date=<YYYY-MM-DD>   Fecha específica (default: hoy)
  --start=<YYYY-MM-DD>  Fecha inicio para reportes
  --end=<YYYY-MM-DD>    Fecha fin para reportes

Ejemplos:
  php audit-report.php user-actions --user-id=1
  php audit-report.php user-history --target-id=42 --start=2025-10-01
  php audit-report.php suspicious-activity --date=2025-10-04
  php audit-report.php generate-report --start=2025-10-01 --end=2025-10-04

HELP;
}