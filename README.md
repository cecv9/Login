# 🔐 Login MVC PHP - Sistema de Autenticación Profesional

Sistema de login y gestión de usuarios construido desde cero en PHP puro, con arquitectura MVC, Router personalizado, patrón Repository y seguridad avanzada. Ideal como base profesional para proyectos web modernos.

---

## Tabla de Contenidos

- [Características](#características)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Configuración](#configuración)
- [Estructura del Proyecto](#estructura-del-proyecto)
- [Flujo y Funcionamiento](#flujo-y-funcionamiento)
- [Sistema de Rutas y Ejemplos](#sistema-de-rutas-y-ejemplos)
- [Ejemplos de Código](#ejemplos-de-código)
- [Seguridad](#seguridad)
- [Testing](#testing)
- [Contribuir](#contribuir)
- [Licencia](#licencia)
- [Autor](#autor)

---

## Características

- **Arquitectura MVC limpia** (Model-View-Controller)
- **Router personalizado** con protección de rutas y roles
- **Gestión de usuarios**: registro, login, dashboard, administración (roles)
- **Sesiones seguras** y manejo avanzado de cookies
- **Password hashing** y validación robusta de datos
- **Protección CSRF** automática
- **Validación y sanitización** reutilizables mediante Traits
- **Variables de entorno** (.env) con php-dotenv
- **Autoload PSR-4** y namespaces organizados
- **100% PHP nativo, sin frameworks pesados**

---

## Requisitos

- PHP >= 8.0 (con soporte para namespaces y strict_types)
- MySQL/MariaDB
- Composer
- Servidor web Apache/Nginx (con mod_rewrite o reglas equivalentes)
- Extensiones PHP: pdo, pdo_mysql, mbstring, openssl

---

## Instalación

1. **Clona el repositorio**
   ```bash
   git clone https://github.com/cecv9/Login.git
   cd Login
   ```

2. **Instala dependencias**
   ```bash
   composer install
   ```

3. **Configura el servidor web**
   - El DocumentRoot debe apuntar a la carpeta `public/`.
   - Apache: El proyecto incluye `.htaccess` para reescribir todas las rutas a `public/index.php`.
   - Nginx: Configura el root a `public/` y pasa todas las rutas a `index.php`.

4. **Crea la base de datos**
   ```sql
   CREATE DATABASE login_mvc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   -- Crea la tabla de usuarios según el esquema definido en models/Users.php
   ```

---

## Configuración

1. **Copia y edita el archivo .env**
   ```bash
   cp .env.example .env
   ```
   Ajusta los valores de conexión a base de datos, entorno y seguridad:
   ```env
   DB_DRIVER=mysql
   DB_HOST=localhost
   DB_USER=tu_usuario
   DB_PASS=tu_contraseña
   DB_NAME=login_mvc
   DB_CHARSET=utf8mb4
   DB_PORT=3306
   APP_DEBUG=true
   APP_TZ=America/Mexico_City
   APP_DOMAIN=localhost
   ```

2. **Opcional:** Puedes definir la configuración de BD en `app/Config/database.php`, sobreescribiendo los valores del .env.

---

## Estructura del Proyecto

```
Login/
├── app/
│   ├── Config/         # Configuración, rutas, DTO de BD
│   ├── Controllers/    # Controladores MVC (Auth, Admin, Dashboard, etc)
│   ├── Core/           # Router, conexión PDO, excepciones
│   ├── Repository/     # DAO/Repository para usuarios y más
│   ├── Traits/         # Validación reutilizable
│   └── models/         # Modelos de entidad, ej: Users.php
├── views/              # Vistas agrupadas por módulo (auth, admin, dashboard)
├── public/             # Punto de entrada (index.php), assets y .htaccess
├── test/               # Pruebas unitarias y de integración
├── vendor/             # Dependencias Composer
├── .env, .gitignore, composer.json, README.md, etc.
```

---

## Flujo y Funcionamiento

1. **Front Controller**: `public/index.php` carga entorno, configura errores, conecta a BD, inicia sesión, genera token CSRF y despacha la petición con el router.
2. **Router personalizado** (`app/Core/Router.php`): 
   - Lee rutas desde `app/Config/routes.php` (por método y path, ej: `/login`, `/dashboard`)
   - Protege rutas que requieren login y/o rol admin.
   - Invoca el controlador y método correspondiente con la conexión PDO inyectada.
3. **Controladores**: Reciben la conexión PDO y gestionan la lógica de negocio (registro, login, dashboard, admin). Usan el Repository para acceso a datos y Traits para validación.
4. **Vistas**: Renderizadas directamente desde los controladores, siguiendo la estructura modular (partials, layouts, etc).
5. **Repository**: Capa de abstracción para operaciones sobre la base de datos (usuarios, roles, etc).

---

## Sistema de Rutas y Ejemplos

Las rutas se definen en `app/Config/routes.php` agrupadas por método HTTP:

```php
return [
    'GET' => [
        '/' => 'AuthController@index',
        '/login' => 'AuthController@loginForm',
        '/register' => 'AuthController@registerForm',
        '/dashboard' => 'DashboardController@index',
        '/admin' => 'AdminController@index',
    ],
    'POST' => [
        '/login' => 'AuthController@login',
        '/register' => 'AuthController@register',
        '/logout' => 'AuthController@logout',
        '/admin/users/create' => 'AdminController@createUser',
    ]
];
```

**Protección de rutas:**

En `public/index.php`, el router protege rutas así:
```php
$router->protectRoute('/dashboard');         // Solo autenticados
$router->protectRoute('/admin', 'admin');    // Solo usuarios con rol admin
```
Si un usuario no autenticado accede, será redirigido automáticamente a `/login`.

---

## Ejemplos de Código

### Ejemplo: Registrar usuario (Controller → Repository)

```php
// app/Controllers/AuthController.php
public function register() {
    $data = [
        'username' => $_POST['username'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? ''
    ];
    // Validar datos (ValidatorTrait)
    if (!$this->validateRegister($data)) {
        return $this->render('auth/register', ['error' => 'Datos inválidos']);
    }
    // Guardar usuario (Repository)
    $userRepo = new UsuarioRepository($this->pdo);
    $userRepo->createUser($data);
    header('Location: /login');
}
```

### Ejemplo: Login y manejo de sesión

```php
// app/Controllers/AuthController.php
public function login() {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $userRepo = new UsuarioRepository($this->pdo);
    $user = $userRepo->findByUsername($username);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        header('Location: /dashboard');
    } else {
        return $this->render('auth/login', ['error' => 'Credenciales incorrectas']);
    }
}
```

### Ejemplo: Validación reutilizable

```php
// app/Traits/ValidatorTrait.php
trait ValidatorTrait {
    public function validateRegister(array $data): bool {
        // Validación de email, longitud, caracteres, etc.
        return filter_var($data['email'], FILTER_VALIDATE_EMAIL) &&
               strlen($data['username']) > 3 &&
               strlen($data['password']) >= 8;
    }
}
```

### Ejemplo: Definir nueva ruta protegida

```php
// app/Config/routes.php
'GET' => [
    '/perfil' => 'DashboardController@profile',
]
// En index.php
$router->protectRoute('/perfil');
```

---

## Seguridad

- **Sesiones seguras**: Cookies HttpOnly, SameSite, Secure, dominio y path configurados dinámicamente.
- **CSRF**: Token generado en la sesión y verificado en formularios.
- **Password hashing**: `password_hash()` y `password_verify()`
- **PDO + prepared statements**: Previene SQL injection.
- **Validación/sanitización**: Centralizada en ValidatorTrait y controladores.
- **Protección de archivos sensibles**: `.htaccess` en raíz y en public.

---

## Testing

- Estructura lista en `test/` para pruebas unitarias/integración (se recomienda usar PHPUnit).
- Puedes agregar pruebas para controladores, repository y lógica de rutas.

---

## Contribuir

1. Haz un fork de este repositorio
2. Crea una rama feature (`git checkout -b feature/nueva-funcionalidad`)
3. Haz tus cambios y sus pruebas
4. Haz commit y push (`git push origin feature/nueva-funcionalidad`)
5. Abre un Pull Request

---

## Licencia

MIT

---

## Autor

**Enoc Castillo**  
GitHub: [@cecv9](https://github.com/cecv9)

---

¿Te ha sido útil? ¡Dale una estrella! ⭐
