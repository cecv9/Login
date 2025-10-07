<?php
require __DIR__ . '/vendor/autoload.php';

use Enoc\Login\Core\LogManager;
use Enoc\Login\Controllers\AuditContext;

// Inicializar el logger
$logger = LogManager::getInstance();

// Crear un contexto de auditoría de prueba
$audit = new AuditContext(
    userId: 1,
    username: 'test_user',
    userEmail: 'test@example.com',
    ipAddress: '127.0.0.1',
    userAgent: 'Test Script'
);

// Escribir un log de prueba
$logger->info('Prueba de logging', [
    'action' => 'TEST_ACTION',
    'actor_user_id' => 1,
    'test_field' => 'test_value',
    ...$audit->toArray()
]);

echo "Log de prueba escrito. Verifica /home/enoc/Login/logs/" . date('Y-m-d') . ".log\n";
