# AUDITORÍA PROFUNDA: AdminController.php

## 📊 RESUMEN EJECUTIVO

**Estado General:** ✅ **BUENO** con mejoras recomendadas
**Nivel de Seguridad:** 🟢 **ALTO**
**Calidad del Código:** 🟢 **ALTA**
**Adherencia SOLID:** 🟢 **EXCELENTE**

---

## 1. ARQUITECTURA Y DEPENDENCIAS

### ✅ Fortalezas

**Separación de Responsabilidades (SRP):**
- El controlador actúa exclusivamente como capa HTTP (líneas 17-34)
- No contiene lógica de negocio (delegada a `UserService`)
- No contiene validación sintáctica (delegada a Forms)
- No contiene queries a BD (delegada a `UsuarioRepository`)
- No contiene lógica de autorización (delegada a `AuthorizationService`)

**Inyección de Dependencias:**
```php
// Constructor: línea 53-81
public function __construct(PdoConnection $pdoConnection)
{
    $this->repository = new UsuarioRepository($pdoConnection);
    $this->userService = new UserService($this->repository);
    $this->auth = new AuthorizationService();
    // ...
}
```

**Herencia apropiada:**
- Extiende `BaseController` para reutilizar funcionalidad común (CSRF, vistas, redirects)

### ⚠️ Observaciones

**Instanciación directa en constructor (líneas 56-58):**
```php
$this->repository = new UsuarioRepository($pdoConnection);
$this->userService = new UserService($this->repository);
$this->auth = new AuthorizationService();
```

**Impacto:** Dificulta testing unitario (no puedes mockear las dependencias fácilmente).

**Recomendación:** Inyectar las dependencias directamente:
```php
public function __construct(
    private readonly UsuarioRepository $repository,
    private readonly UserService $userService,
    private readonly AuthorizationService $auth
) {
    // Validaciones de seguridad...
}
```

---

## 2. SEGURIDAD

### 🔒 Autenticación (líneas 67-71)

```php
if (empty($_SESSION['user_id'])) {
    header('Location: /login');
    exit('Authentication required');
}
```

**Análisis:**
- ✅ Verificación correcta en el constructor (protege TODAS las acciones)
- ✅ Usa `exit()` después de `header()` (previene ejecución posterior)
- ⚠️ El mensaje `exit('Authentication required')` no es visible al usuario (solo en logs)

### 🔐 Autorización (líneas 74-80)

```php
$userRole = $_SESSION['user_role'] ?? UserRole::USER;
if ($userRole !== UserRole::ADMIN) {
    header('Location: /');
    exit('Forbidden: Admin access required');
}
```

**Análisis:**
- ✅ Verificación de rol ADMIN para acceso al panel
- ✅ Usa fallback seguro (`UserRole::USER`) si no existe el rol
- ⚠️ **VULNERABILIDAD POTENCIAL:** Confía en `$_SESSION['user_role']` sin re-validar contra BD

**Escenario de ataque:**
Si un atacante modifica la sesión (session fixation, session hijacking), podría elevar privilegios.

**Recomendación:**
```php
// Obtener el rol desde BD, no desde sesión
$currentUser = $this->repository->findById($_SESSION['user_id']);
if (!$currentUser || $currentUser->getRole() !== UserRole::ADMIN) {
    header('Location: /');
    exit('Forbidden: Admin access required');
}
```

### 🛡️ Protección CSRF

**Generación de token (línea 165):**
```php
'csrfToken' => $this->generateCsrfToken(),
```

**Validación de token (líneas 276-280, 425-429, 500-504):**
```php
$submittedToken = $_POST['csrf_token'] ?? null;
if (!$this->validateCsrf(is_string($submittedToken) ? $submittedToken : null)) {
    $_SESSION['error'] = 'Token de seguridad inválido. Intenta nuevamente.';
    return $this->redirect('/admin/users/create');
}
```

**Análisis:**
- ✅ Validación en TODOS los métodos POST (store, update, destroy)
- ✅ Usa `hash_equals()` internamente en `BaseController:124` (previene timing attacks)
- ✅ Rotación de token después de operaciones exitosas (líneas 305, 455, 522)
- ✅ Type-checking del token antes de validar

**Excelente implementación de CSRF.**

### 🔍 Autorización Granular

**En edit() - línea 366:**
```php
if (!$this->auth->can($currentUser, 'update', 'user', $user)) {
    $_SESSION['error'] = 'No tienes permisos para editar este usuario';
    return $this->redirect('/admin/users');
}
```

**Análisis:**
- ✅ Verifica permisos NO solo de crear/editar, sino sobre ESE usuario específico
- ✅ Previene que un admin edite a otro admin de mayor jerarquía
- ✅ Delega la lógica a `AuthorizationService` (SRP)

---

## 3. MANEJO DE ERRORES Y EXCEPCIONES

### ✅ Estrategia de Try-Catch (líneas 300-326, 450-473)

```php
try {
    $userId = $this->userService->create($dto, $audit);
    $_SESSION['success'] = 'Usuario creado correctamente';
    $this->rotateCsrf();
    return $this->redirect('/admin/users');

} catch (EmailAlreadyExists $e) {
    // Manejo específico...
} catch (ValidationException $e) {
    // Manejo específico...
} catch (\Throwable $e) {
    // Manejo genérico...
}
```

**Análisis:**
- ✅ Captura excepciones específicas antes que genéricas (orden correcto)
- ✅ Manejo diferenciado por tipo de error
- ✅ Preserva datos del formulario en sesión para prefill (UX)
- ✅ Usa `\Throwable` como último catch (captura todo)

### ⚠️ Mensajes de Error

**Mensaje genérico (línea 324):**
```php
$_SESSION['error'] = 'Error interno del servidor. Intenta nuevamente.';
```

**Análisis:**
- ✅ No expone detalles técnicos al usuario (seguridad)
- ⚠️ **PROBLEMA:** Los errores internos se loguean en `UserService` pero el controlador no verifica si el logging falló

**Recomendación:** Considerar un fallback de logging local:
```php
} catch (\Throwable $e) {
    error_log("AdminController::store error: " . $e->getMessage());
    $_SESSION['error'] = 'Error interno del servidor. Intenta nuevamente.';
    return $this->redirect('/admin/users/create');
}
```

---

## 4. LÓGICA DE PAGINACIÓN

### 📄 Implementación de Cursor-Based Pagination (líneas 99-166)

```php
$limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
$after  = isset($_GET['after'])  ? (int)$_GET['after']  : null;
$before = isset($_GET['before']) ? (int)$_GET['before'] : null;

if ($before !== null) {
    $users = $this->repository->findPageBefore($before, $limit);
} else {
    $cursor = $after ?? PHP_INT_MAX;
    $users = $this->repository->findPageAfter($cursor, $limit);
}
```

**Análisis:**
- ✅ Usa cursor-based pagination (más eficiente que offset)
- ✅ Limita el límite entre 1 y 100 (previene DoS)
- ✅ Sanitización con `(int)` cast
- ✅ Calcula correctamente `hasMoreNewer` y `hasMoreOlder`

### ⚠️ Observación

**Línea 124:**
```php
$cursor = $after ?? PHP_INT_MAX;
```

**Problema potencial:** Si el ID de usuario es auto-incremental y supera `PHP_INT_MAX` (muy improbable pero posible en sistemas de 32 bits o IDs muy grandes), podría fallar.

**Recomendación:** Documentar que asume IDs menores a PHP_INT_MAX o usar estrategia alternativa:
```php
$cursor = $after ?? $this->repository->getMaxUserId();
```

---

## 5. FLUJO DE DATOS Y SESIONES

### 🔄 Patrón PRG (Post-Redirect-Get)

**Implementación correcta en store() - líneas 303-306:**
```php
$_SESSION['success'] = 'Usuario creado correctamente';
$this->rotateCsrf();
return $this->redirect('/admin/users');
```

**Análisis:**
- ✅ Después de POST exitoso, redirige a GET (previene doble envío)
- ✅ Usa flash messages en sesión
- ✅ Rota el token CSRF (seguridad)

### 📝 Flash Messages (líneas 220-237)

```php
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
```

**Análisis:**
- ✅ Consume los mensajes (no persisten en sesión)
- ✅ Usa operador null-coalescing para defaults
- ⚠️ Podría centralizarse en un helper para evitar repetición:

```php
// BaseController
protected function getFlash(string $key): string {
    $value = $_SESSION[$key] ?? '';
    unset($_SESSION[$key]);
    return $value;
}

// AdminController
$error = $this->getFlash('error');
$success = $this->getFlash('success');
```

### 🔁 Prefill de Formularios (líneas 233-237, 288-289)

```php
$name = $_SESSION['input_name'] ?? '';
unset($_SESSION['input_name']);

$email = $_SESSION['input_email'] ?? '';
unset($_SESSION['input_email']);
```

**Análisis:**
- ✅ Excelente UX (el usuario no pierde lo que escribió)
- ✅ Sanitiza el email con `strtolower()` antes de guardar (línea 289)
- ⚠️ **SEGURIDAD:** Los valores se escapan en la vista con `htmlspecialchars()`, pero no en el controlador

**Confirmación:** Revisando BaseController:29, se confirma que se escapa en la vista. ✅ Correcto.

---

## 6. ADHERENCIA A PRINCIPIOS SOLID

### ✅ S - Single Responsibility Principle
- Cada método tiene UNA responsabilidad clara
- El controlador solo maneja HTTP, no lógica de negocio

### ✅ O - Open/Closed Principle
- Extensible mediante herencia de `BaseController`
- Nuevos roles se agregan en `UserRole` sin modificar el controlador

### ✅ L - Liskov Substitution Principle
- Respeta el contrato de `BaseController`
- Todos los métodos retornan `string` como se espera

### ✅ I - Interface Segregation Principle
- No fuerza dependencias innecesarias
- Usa solo los métodos que necesita de cada servicio

### ✅ D - Dependency Inversion Principle
- ⚠️ **MEJORA POSIBLE:** Actualmente instancia clases concretas
- **Recomendación:** Inyectar interfaces en vez de implementaciones

---

## 7. VULNERABILIDADES Y BUGS POTENCIALES

### 🐛 BUGS ENCONTRADOS

#### 1. **Inconsistencia en manejo de errores del método store()**

**Línea 297:**
```php
$audit = AuditContext::fromSession();
```

**Problema:** Si `fromSession()` falla (sesión corrupta), no hay try-catch que lo capture.

**Severidad:** 🟡 MEDIA
**Recomendación:** Validar que `AuditContext` no lance excepciones o capturarlas.

#### 2. **Falta verificación de autorización en delete()**

**Línea 478-482:**
```php
public function delete(): string
{
    $id = (int)($_GET['id'] ?? 0);
    $user = $this->repository->findById($id);
    if (!$user) return $this->redirect('/admin/users');
    // ...
}
```

**Problema:** No verifica `$this->auth->can($currentUser, 'delete', 'user', $user)` antes de mostrar el formulario.

**Severidad:** 🟡 MEDIA
**Impacto:** Un admin podría ver el formulario de borrado de otro admin (aunque el `destroy()` sí valida en el Service).

**Recomendación:**
```php
public function delete(): string
{
    $id = (int)($_GET['id'] ?? 0);
    $user = $this->repository->findById($id);
    if (!$user) return $this->redirect('/admin/users');

    // ✅ AGREGAR ESTA VALIDACIÓN
    $currentUser = $this->repository->findById($_SESSION['user_id']);
    if (!$this->auth->can($currentUser, 'delete', 'user', $user)) {
        $_SESSION['error'] = 'No tienes permisos para eliminar este usuario';
        return $this->redirect('/admin/users');
    }

    // ...
}
```

#### 3. **Posible Race Condition en update()**

**Línea 138-145 (UserService.php):**
```php
$targetUser = $this->repository->findById($dto->id);
// ...tiempo pasa...
$success = $this->repository->updateUserHashed(...);
```

**Problema:** Entre la lectura y la escritura, otro proceso podría modificar el usuario.

**Severidad:** 🟡 MEDIA
**Impacto:** Bajo en aplicaciones pequeñas, alto en aplicaciones concurrentes.

**Recomendación:** Usar transacciones en el Repository o implementar optimistic locking.

### 🔒 VULNERABILIDADES DE SEGURIDAD

#### 1. **Confianza en `$_SESSION['user_role']`** (línea 74)

**Ya descrito en sección 2 - Autorización.**
**Severidad:** 🔴 ALTA
**Recomendación:** Verificar el rol contra BD en el constructor.

#### 2. **No hay Rate Limiting**

**Problema:** No hay protección contra fuerza bruta en los formularios de creación/edición.

**Severidad:** 🟡 MEDIA
**Recomendación:** Implementar rate limiting por IP o por sesión.

#### 3. **No hay logging de intentos fallidos de autorización**

**Problema:** Las validaciones de autorización fallan silenciosamente sin dejar rastro auditable.

**Ejemplo - línea 368:**
```php
if (!$this->auth->can($currentUser, 'update', 'user', $user)) {
    $_SESSION['error'] = 'No tienes permisos para editar este usuario';
    return $this->redirect('/admin/users');
}
```

**Severidad:** 🟡 MEDIA
**Recomendación:**
```php
if (!$this->auth->can($currentUser, 'update', 'user', $user)) {
    error_log("UNAUTHORIZED ACCESS ATTEMPT: User {$currentUser->getId()} tried to edit user {$user->getId()}");
    $_SESSION['error'] = 'No tienes permisos para editar este usuario';
    return $this->redirect('/admin/users');
}
```

---

## 8. RECOMENDACIONES PRIORIZADAS

### 🔴 CRÍTICAS (Implementar inmediatamente)

1. **Verificar rol desde BD en el constructor** (AdminController.php:74)
   - Archivo: `app/Controllers/AdminController.php`
   - Línea: 74-80
   - Razón: Previene escalada de privilegios

### 🟡 IMPORTANTES (Implementar pronto)

2. **Agregar verificación de autorización en delete()** (AdminController.php:478)
   - Archivo: `app/Controllers/AdminController.php`
   - Línea: 478-494

3. **Logging de intentos fallidos de autorización**
   - Archivos: Todos los métodos con verificación `can()`
   - Razón: Auditoría y detección de ataques

4. **Inyección de dependencias en constructor**
   - Archivo: `app/Controllers/AdminController.php`
   - Línea: 53-81
   - Razón: Testabilidad

### 🟢 MEJORAS (Considerar para el futuro)

5. **Centralizar manejo de flash messages**
   - Crear helper en `BaseController`
   - Reduce código repetitivo

6. **Implementar rate limiting**
   - Usar middleware o librería como `symfony/rate-limiter`

7. **Transacciones en operaciones críticas**
   - En `UserService::update()` y `UserService::create()`

---

## 9. MÉTRICAS DE CALIDAD

| Métrica | Valor | Estado |
|---------|-------|--------|
| **Complejidad Ciclomática** | Media: 4-6 | 🟢 Bueno |
| **Lines of Code** | 529 | 🟢 Razonable |
| **Métodos por clase** | 8 | 🟢 Bien modularizado |
| **Dependencias** | 3 (Repository, Service, Auth) | 🟢 Bajo acoplamiento |
| **Cobertura CSRF** | 100% en POSTs | 🟢 Excelente |
| **Separación de Responsabilidades** | Alta | 🟢 Excelente |

---

## 10. CONCLUSIÓN

El `AdminController.php` es un **controlador bien diseñado** que sigue principios de arquitectura limpia y SOLID. La mayor parte del código es seguro y mantenible.

**Puntos destacados:**
- Excelente separación de responsabilidades
- Implementación robusta de CSRF
- Patrón PRG correctamente aplicado
- Autorización granular bien diseñada
- Paginación eficiente con cursores

**Áreas de mejora críticas:**
- Verificación de rol desde BD (no desde sesión)
- Autorización en método `delete()`
- Logging de intentos de acceso no autorizado

**Calificación general: 8.5/10**

---

## ANEXO: DETALLE DE MÉTODOS

### Método: `__construct()`
**Líneas:** 53-81
**Responsabilidad:** Inicializar dependencias y validar acceso de administrador
**Seguridad:** ✅ Autenticación y autorización global

### Método: `index()`
**Líneas:** 99-167
**Responsabilidad:** Listar usuarios paginados
**Seguridad:** ✅ Protegido por constructor
**Optimización:** ✅ Cursor-based pagination

### Método: `create()`
**Líneas:** 185-252
**Responsabilidad:** Mostrar formulario de creación
**Seguridad:** ✅ CSRF token generado
**UX:** ✅ Flash messages y prefill

### Método: `store()`
**Líneas:** 273-327
**Responsabilidad:** Procesar creación de usuario
**Seguridad:** ✅ Validación CSRF, delegación a Service para autorización
**Validación:** ✅ Sintáctica (Form) + Negocio (Service)

### Método: `edit()`
**Líneas:** 338-405
**Responsabilidad:** Mostrar formulario de edición
**Seguridad:** ✅ Autorización granular con `can()`
**UX:** ✅ Pre-llena con datos del usuario

### Método: `update()`
**Líneas:** 422-474
**Responsabilidad:** Procesar actualización de usuario
**Seguridad:** ✅ CSRF + autorización en Service
**Manejo de errores:** ✅ Excepciones específicas

### Método: `delete()`
**Líneas:** 478-494
**Responsabilidad:** Mostrar confirmación de borrado
**Seguridad:** ⚠️ FALTA verificación de autorización

### Método: `destroy()`
**Líneas:** 497-528
**Responsabilidad:** Ejecutar borrado (soft delete)
**Seguridad:** ✅ CSRF, validación en Service
**Protección:** ✅ Validaciones de negocio en UserService

---

**Fecha de auditoría:** 2025-10-15
**Auditor:** Claude Code
**Versión del archivo:** app/Controllers/AdminController.php (commit: 99b06fc)
