<?php

namespace Enoc\Login\Core;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LoggerInterface;

final class LogManager
{
    private static ?self $instance = null;
    private LoggerInterface $logger;

    private function __construct()
    {
        $this->logger = new Logger('app');

        // Construir el nombre del archivo con la fecha actual
        // Esto creará archivos como: logs/2025-10-06.log
        $logPath = dirname(__DIR__, 2) . '/logs/' . date('Y-m-d') . '.log';

        // StreamHandler escribe en el archivo especificado
        $handler = new StreamHandler($logPath, Level::Debug);

        $handler->setFormatter(new LineFormatter(
            "[%datetime%] [%level_name%] %message% %context%\n",
            'Y-m-d H:i:s'
        ));


        $this->logger->pushHandler($handler);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ✅ Métodos estáticos de conveniencia
    public static function logInfo(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    public static function logError(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    public static function logWarning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    public static function logDebug(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }

    // Métodos de instancia originales
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
}