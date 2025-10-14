<?php

declare(strict_types=1);

namespace Enoc\Login\Core;

use Enoc\Login\Core\Domain\Request;
use Enoc\Login\Core\Domain\Response;
use Enoc\Login\Middleware\MiddlewareFactory;

/**
 * FrontController - Orquestador con MODO TRANSICIÓN
 *
 * Soporta: Response (modernos) + strings + arrays (legacy)
 */
class FrontController
{
    public function __construct(
        private readonly Router $router,
        private readonly DependencyContainer $container
    ) {}

    public function handle(): void
    {
        try {
            // 1️⃣ Request
            $request = Request::fromGlobals();

            // 2️⃣ Routing
            $route = $this->router->match($request->method, $request->getPath());

               // 3) Si no hay ruta, responder 404 y salir
            if (!$route) {
                $this->handleNotFound();
                return;
            }
        
            // 4) Ejecutar los middlewares declarados para ESTA ruta
        //    Importante:
        //    - Tus middlewares actuales tienen firma ->handle() sin $next.
        //    - Si necesitan cortar el flujo (redirigir o forbiden), lo harán aquí (exit/return).
        foreach ($route->getMiddleware() as $key) {
            MiddlewareFactory::make($key)->handle();
        }

            // 3️⃣ Ejecutar handler (legacy or modern)
            $response = $this->router->executeHandler($route->handler, $request);

            // 4️⃣ ✅Respuesta con AUTO-CONVERSIÓN
            $this->convertAndSend($route->handler, $response);

        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * ✅ RESPUESTA HÍBRIDA (MODERNA + LEGACY)
     */
    private function convertAndSend(mixed $handler, mixed $response): void
    {
        // 📦 Caso MODERNO: Response object (ideal)
        if ($response instanceof Response) {
            $response->send();
            return;
        }

        // 📜 Caso LEGACY HTML: string → Response::html
        if (is_string($response)) {
            $handlerInfo = is_object($handler) ? get_class($handler) : (string)$handler;

            // Loggear para migración futura
            if (class_exists(\Enoc\Login\Core\LogManager::class)) {
                LogManager::logInfo('LegacyController', [
                    'message' => 'String response auto-converted to Response::html',
                    'handler' => $handlerInfo
                ]);
            }

            Response::html($response)->send();
            return;
        }

        // 📊 Caso LEGACY API: array → Response::json
        if (is_array($response)) {
            $handlerInfo = is_object($handler) ? get_class($handler) : (string)$handler;

            if (class_exists(\Enoc\Login\Core\LogManager::class)) {
                LogManager::logInfo('LegacyController', [
                    'message' => 'Array response auto-converted to Response::json',
                    'handler' => $handlerInfo
                ]);
            }

            Response::json($response)->send();
            return;
        }

        // ❌ Caso ERROR: tipo no soportado
        $this->handleUnsupportedResponse($handler, $response);
    }

    /**
     * ⚠️ Manejar respuesta no soportada (error de programación)
     */
    private function handleUnsupportedResponse(mixed $handler, mixed $response): void
    {
        $handlerInfo = is_object($handler) ? get_class($handler) : gettype($handler);
        $responseType = gettype($response);
        $responseValue = print_r($response, true);

        if (class_exists(\Enoc\Login\Core\LogManager::class)) {
            LogManager::logError('UnsupportedResponse', [
                'handler' => $handlerInfo,
                'response_type' => $responseType,
                'response_value' => $responseValue
            ]);
        }

        if (filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            // Modo desarrollo: mostrar detalles
            echo '<h1>Unsupported Response Type</h1>';
            echo '<p><strong>Handler:</strong> ' . htmlspecialchars($handlerInfo) . '</p>';
            echo '<p><strong>Response Type:</strong> ' . htmlspecialchars($responseType) . '</p>';
            echo '<p><strong>Expected:</strong> Response, string, or array</p>';
            echo '<h3>Response Value:</h3>';
            echo '<pre>' . htmlspecialchars($responseValue) . '</pre>';

        } else {
            // Modo producción: error genérico
            Response::internalError('Invalid response format')->send();
        }
    }

    /**
     * ❌ 404 Handler
     */
    public function handleNotFound(): void
    {
        Response::notFound($this->router->notFound())->send();
    }

    /**
     * ❌ Error Handler
     */
    private function handleError(\Throwable $exception): void
    {
        if (class_exists(\Enoc\Login\Core\LogManager::class)) {
            LogManager::logError('FrontControllerError', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);
        }

        $isDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isDebug) {
            $errorData = [
                'error' => true,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString())
            ];
            Response::json($errorData, 500)->send();
        } else {
            Response::html('<h1>Error del servidor</h1>', 500)->send();
        }
    }
}