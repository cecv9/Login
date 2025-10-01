<?php

namespace Enoc\Login\Core;

final class LogManager {
    public const LEVEL_DEBUG = 100;
    public const LEVEL_INFO = 200;
    public const LEVEL_WARNING = 300;
    public const LEVEL_ERROR = 400;
    public const LEVEL_CRITICAL = 500;

    private static int $minLevel;
    private static string $logPath;
    private static bool $initialized = false;

    public static function init(string $logPath = null, int $minLevel = null): void {
        self::$initialized = true;
        self::$minLevel = $minLevel ?? (int)($_ENV['LOG_LEVEL'] ?? self::LEVEL_ERROR);
        self::$logPath = $logPath ?? ($_ENV['LOG_PATH'] ?? dirname(__DIR__, 2) . '/logs');

        // Crear directorio si no existe
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
    }

    public static function debug(string $message, array $context = []): void {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    public static function info(string $message, array $context = []): void {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    public static function critical(string $message, array $context = []): void {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }

    private static function log(int $level, string $message, array $context = []): void {
        if (!self::$initialized) {
            self::init();
        }

        if ($level < self::$minLevel) {
            return;
        }

        $levelName = match($level) {
            self::LEVEL_DEBUG => 'DEBUG',
            self::LEVEL_INFO => 'INFO',
            self::LEVEL_WARNING => 'WARNING',
            self::LEVEL_ERROR => 'ERROR',
            self::LEVEL_CRITICAL => 'CRITICAL',
            default => 'UNKNOWN'
        };

        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = self::formatMessage($message, $context);
        $logEntry = "[$timestamp] [$levelName] $formattedMessage" . PHP_EOL;

        // Determinar archivo de log
        $logFile = self::$logPath . '/' . date('Y-m-d') . '.log';

        // Escribir en archivo
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        // Para errores críticos, también usar error_log nativo
        if ($level >= self::LEVEL_ERROR) {
            error_log("[$levelName] $formattedMessage");
        }
    }

    private static function formatMessage(string $message, array $context): string {
        // Reemplazar placeholders {key} con valores del contexto
        foreach ($context as $key => $value) {
            $placeholder = "{{$key}}";
            if (strpos($message, $placeholder) !== false) {
                $value = is_scalar($value) ? (string)$value : json_encode($value);
                $message = str_replace($placeholder, $value, $message);
            }
        }

        // Añadir contexto como JSON si hay valores no usados
        if (!empty($context)) {
            $unusedContext = [];
            foreach ($context as $key => $value) {
                if (strpos($message, "{{$key}}") === false) {
                    $unusedContext[$key] = $value;
                }
            }
            if (!empty($unusedContext)) {
                $message .= " " . json_encode($unusedContext);
            }
        }

        return $message;
    }
}