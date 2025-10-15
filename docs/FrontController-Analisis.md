# 📚 **ANATOMÍA COMPLETA DEL FRONTCONTROLLER**

## 🎯 **1. ¿QUÉ ES Y PARA QUÉ SIRVE?**

El **FrontController** es un **patrón arquitectural** que actúa como **punto de entrada único** para todas las peticiones HTTP de tu aplicación.

### **Analogía del mundo real:**
Imagina un hotel:
- **Sin FrontController**: Los clientes entran por cualquier puerta, buscan habitaciones solos, causan caos
- **Con FrontController**: Todos pasan por **recepción** (FrontController) → validan identidad (middleware) → reciben llave (route) → van a habitación (controller)

---

## 🏗️ **2. PRINCIPIOS DE PROGRAMACIÓN APLICADOS**

### **A) SOLID Principles**

#### **S - Single Responsibility Principle (SRP)**
```php
class FrontController // ✅ UNA SOLA RESPONSABILIDAD
{
    public function handle(): void // ORQUESTAR el flujo HTTP
    {
        // 1. Crear Request
        // 2. Buscar Route
        // 3. Ejecutar Middlewares
        // 4. Ejecutar Handler
        // 5. Enviar Response
    }
}
```

**Separación de responsabilidades:**
- `FrontController` → **Orquestar** (no ejecuta lógica de negocio)
- `Router` → **Routing** (buscar rutas)
- `Request` → **Datos HTTP** (encapsular $_GET, $_POST)
- `Response` → **Respuestas HTTP** (headers, body)
- `Middleware` → **Validaciones** (auth, roles)
- `Controllers` → **Lógica de negocio**

---

#### **D - Dependency Inversion Principle (DIP)**

```php
// ✅ FrontController depende de ABSTRACCIONES, no implementaciones concretas
public function __construct(
    private readonly Router $router,              // Abstracción
    private readonly DependencyContainer $container // Abstracción
) {}
```

**Beneficio:** Si mañana cambias el Router por otro, el FrontController **no necesita modificarse**.

---

#### **O - Open/Closed Principle (OCP)**

```php
// ✅ ABIERTO A EXTENSIÓN (sin modificar código)
private function convertAndSend(mixed $handler, mixed $response): void
{
    if ($response instanceof Response) { /* ... */ }
    if (is_string($response)) { /* ... */ }  // Legacy support
    if (is_array($response)) { /* ... */ }   // Legacy support
}
```

**Extensibilidad:** Soporta **respuestas modernas** (Response) Y **legacy** (string/array) sin romper código existente.

---

### **B) Design Patterns Aplicados**

#### **1. Front Controller Pattern**
*"Centralizar el manejo de requests"*

```
ANTES (sin patrón):
/public/login.php  → código duplicado
/public/register.php → código duplicado
/public/dashboard.php → código duplicado

DESPUÉS (con patrón):
/public/index.php (FrontController)
  ↓
  Router → LoginController@login
  Router → RegisterController@register
  Router → DashboardController@index
```

---

#### **2. Strategy Pattern** (en convertAndSend)
*"Cambiar comportamiento según el tipo de respuesta"*

```php
// app/Core/FrontController.php:60
private function convertAndSend(mixed $handler, mixed $response): void
{
    // ESTRATEGIA 1: Response moderno
    if ($response instanceof Response) {
        $response->send();
        return;
    }

    // ESTRATEGIA 2: String legacy → convertir a HTML
    if (is_string($response)) {
        Response::html($response)->send();
        return;
    }

    // ESTRATEGIA 3: Array legacy → convertir a JSON
    if (is_array($response)) {
        Response::json($response)->send();
        return;
    }
}
```

---

#### **3. Chain of Responsibility** (Middlewares)
*"Cadena de validaciones que pueden detener el flujo"*

```php
// app/Core/FrontController.php:42
foreach ($route->getMiddleware() as $key) {
    MiddlewareFactory::make($key)->handle(); // Puede hacer exit() y detener todo
}
```

**Flujo:**
```
Request → [Authenticate] → [Authorize:admin] → Controller
          ↓ Si falla:      ↓ Si falla:
          redirect /login  Response 403
```

---

#### **4. Dependency Injection (DI)**

```php
// app/Core/FrontController.php:18
public function __construct(
    private readonly Router $router,              // Inyectado
    private readonly DependencyContainer $container // Inyectado
) {}
```

**Configurado en:** `public/index.php:268-274`

```php
$container->bind(FrontController::class, function($container) {
    return new FrontController(
        $container->get(Router::class),  // Auto-resolve
        $container
    );
});
```

---

## 🔗 **3. MAPA DE DEPENDENCIAS**

### **Clases de las que DEPENDE FrontController:**

```
FrontController
├─ Router (obligatorio)
│  └─ PdoConnection
├─ DependencyContainer (obligatorio)
├─ Request (usa estáticamente)
├─ Response (usa estáticamente)
├─ MiddlewareFactory (usa estáticamente)
└─ LogManager (usa estáticamente)
```

### **Clases que DEPENDEN de FrontController:**

```
index.php
└─ FrontController::handle()
```

**Solo `index.php` lo usa**, porque es el **punto de entrada único**.

---

## 🔍 **4. ANÁLISIS LÍNEA POR LÍNEA**

### **Método `handle()` - El corazón del FrontController**

```php
// app/Core/FrontController.php:23
public function handle(): void
{
    try {
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // PASO 1: CREAR REQUEST OBJECT
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        $request = Request::fromGlobals();
        // ¿Por qué?: Abstrae $_GET, $_POST, $_SERVER en objeto inmutable
        // ¿Pattern?: Factory Method + Value Object

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // PASO 2: BUSCAR RUTA (ROUTING)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        $route = $this->router->match($request->method, $request->getPath());
        // ¿Qué hace?: Busca en routes.php si existe GET /dashboard
        // ¿Retorna?: Route object (method, path, handler, middleware)

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // PASO 3: MANEJAR 404
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        if (!$route) {
            $this->handleNotFound(); // Response::notFound() → 404 HTML
            return;                   // Detiene ejecución
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // PASO 4: EJECUTAR MIDDLEWARES (SEGURIDAD)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        foreach ($route->getMiddleware() as $key) {
            MiddlewareFactory::make($key)->handle();
            // ¿Pattern?: Chain of Responsibility + Factory
            // ¿Ejemplo?: ['auth', 'role:admin']
            //   1. Authenticate::handle() → verifica sesión
            //   2. Authorize::handle('admin') → verifica rol
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // PASO 5: EJECUTAR CONTROLADOR
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        $response = $this->router->executeHandler($route->handler, $request);
        // ¿Qué hace?:
        //   - Instancia DashboardController (con DI)
        //   - Llama index($request)
        //   - Retorna Response|string|array

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // PASO 6: ENVIAR RESPUESTA (AUTO-CONVERSION)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        $this->convertAndSend($route->handler, $response);
        // ¿Pattern?: Strategy + Adapter
        // ¿Compatibilidad?: Response, string, array

    } catch (\Throwable $e) {
        $this->handleError($e); // 500 Response
    }
}
```

---

### **Método `convertAndSend()` - Adaptador de Respuestas**

```php
// app/Core/FrontController.php:60
private function convertAndSend(mixed $handler, mixed $response): void
{
    // ════════════════════════════════════════════
    // CASO MODERNO: Response object (IDEAL)
    // ════════════════════════════════════════════
    if ($response instanceof Response) {
        $response->send(); // → envía headers + body
        return;
    }

    // ════════════════════════════════════════════
    // CASO LEGACY: string → HTML
    // ════════════════════════════════════════════
    if (is_string($response)) {
        LogManager::logInfo('LegacyController', [...]);
        Response::html($response)->send();
        // ¿Por qué loggear?: Para identificar qué controllers
        //                    necesitan migración a Response
        return;
    }

    // ════════════════════════════════════════════
    // CASO LEGACY: array → JSON
    // ════════════════════════════════════════════
    if (is_array($response)) {
        LogManager::logInfo('LegacyController', [...]);
        Response::json($response)->send();
        return;
    }

    // ════════════════════════════════════════════
    // CASO ERROR: Tipo no soportado
    // ════════════════════════════════════════════
    $this->handleUnsupportedResponse($handler, $response);
}
```

**¿Por qué este diseño?**
- **Migración gradual**: Permite tener controllers antiguos (string) y nuevos (Response) conviviendo
- **Backward compatibility**: No rompe código existente
- **Logging**: Registra qué controllers necesitan actualización

---

## 🌐 **5. FLUJO COMPLETO CON EJEMPLO REAL**

**Request:** `GET /dashboard`

```
┌─────────────────────────────────────────────────────────┐
│ 1. Usuario → GET /dashboard                             │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│ 2. index.php:282 → $frontController->handle()           │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│ 3. FrontController.php:27                                │
│    Request::fromGlobals()                                │
│    → Request(method='GET', uri='/dashboard')             │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│ 4. FrontController.php:30                                │
│    Router::match('GET', '/dashboard')                    │
│    → Route(handler='DashboardController@index',          │
│            middleware=['auth'])                          │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│ 5. FrontController.php:42 (Middleware Pipeline)          │
│    MiddlewareFactory::make('auth')                       │
│    → Authenticate::handle()                              │
│       ✓ Verifica $_SESSION['user_id']                    │
│       ✗ Si falla → redirect /login + exit                │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│ 6. FrontController.php:47                                │
│    Router::executeHandler(...)                           │
│    → DependencyContainer::get(DashboardController)       │
│    → $controller = new DashboardController($pdo)         │
│    → $controller->index($request)                        │
│    → Response::html('<h1>Dashboard</h1>')                │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│ 7. FrontController.php:50                                │
│    convertAndSend($response)                             │
│    → $response instanceof Response? ✓                    │
│    → $response->send()                                   │
│       → header('HTTP/1.1 200 OK')                        │
│       → header('Content-Type: text/html; charset=utf-8') │
│       → echo '<h1>Dashboard</h1>'                        │
└───────────────┬─────────────────────────────────────────┘
                │
┌───────────────▼─────────────────────────────────────────┐
│ 8. Navegador recibe HTML                                │
└─────────────────────────────────────────────────────────┘
```

---

## 🧩 **6. VENTAJAS DE ESTA ARQUITECTURA**

### **A) Seguridad Centralizada**
```php
// ANTES (código duplicado en cada página):
// dashboard.php:
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

// users.php:
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

// DESPUÉS (centralizado):
$router->middleware('GET', '/dashboard', ['auth']);
$router->middleware('GET', '/users', ['auth']);
```

### **B) Testabilidad**
```php
// Puedes testear sin $_SERVER global:
$request = new Request('GET', '/dashboard');
$route = $router->match('GET', '/dashboard');
// assert($route->hasAuth() === true);
```

### **C) Logging Automático**
Todo pasa por un punto → fácil agregar logs, métricas, profiling

---

## 📊 **7. DIAGRAMA DE CLASES (UML Simplificado)**

```
┌─────────────────────────┐
│   FrontController       │
├─────────────────────────┤
│ - router: Router        │
│ - container: Container  │
├─────────────────────────┤
│ + handle(): void        │
│ - convertAndSend()      │
│ - handleError()         │
│ - handleNotFound()      │
└──────────┬──────────────┘
           │ usa
           ├───────────────────────┬────────────────────┐
           │                       │                    │
┌──────────▼──────────┐ ┌─────────▼────────┐ ┌─────────▼─────────┐
│   Router            │ │   Request        │ │   Response        │
├─────────────────────┤ ├──────────────────┤ ├───────────────────┤
│ + match()           │ │ + fromGlobals()  │ │ + send()          │
│ + executeHandler()  │ │ + getPath()      │ │ + json()          │
└─────────────────────┘ └──────────────────┘ │ + html()          │
                                              └───────────────────┘
```

---

## 🎓 **RESUMEN CONCEPTOS CLAVE**

| Concepto | Aplicación en FrontController |
|----------|-------------------------------|
| **Single Responsibility** | Solo orquesta, no ejecuta lógica de negocio |
| **Dependency Injection** | Recibe Router y Container en constructor |
| **Strategy Pattern** | convertAndSend() decide cómo enviar según tipo |
| **Chain of Responsibility** | Middlewares pueden detener el flujo |
| **Factory Method** | Request::fromGlobals(), Response::json() |
| **Value Object** | Request y Response son inmutables |
| **Front Controller** | Punto único de entrada para todos los requests |
| **Adapter Pattern** | Convierte string/array legacy a Response moderno |

---

## 📁 **UBICACIÓN DE ARCHIVOS CLAVE**

```
/home/enoc/Login/
├── public/index.php               # Bootstrap + configuración DI
├── app/Core/
│   ├── FrontController.php        # Orquestador principal
│   ├── Router.php                 # Sistema de routing
│   ├── DependencyContainer.php    # Inyección de dependencias
│   └── Domain/
│       ├── Request.php            # Value Object HTTP Request
│       └── Response.php           # Value Object HTTP Response
├── app/Middleware/
│   ├── MiddlewareFactory.php      # Factory para middlewares
│   ├── Authenticate.php           # Middleware de autenticación
│   └── Authorize.php              # Middleware de autorización
└── app/Config/
    └── routes.php                 # Definición de rutas
```

---

## 🔍 **CÓDIGO COMPLETO DEL FRONTCONTROLLER**

```php
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
```

---

Esta arquitectura es **production-ready**, **escalable** y sigue **best practices** de la industria (similar a Laravel, Symfony).

---

**Documento generado:** `docs/FrontController-Analisis.md`
**Ubicación:** `/home/enoc/Login/docs/FrontController-Analisis.md`
**Fecha:** 2025-10-14
