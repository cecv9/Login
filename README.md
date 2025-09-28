# 🔐 Login MVC - Sistema de Autenticación PHP

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![Status](https://img.shields.io/badge/Status-Active-brightgreen)

Sistema de login profesional implementado con arquitectura MVC en PHP puro, utilizando PDO, autoloader PSR-4 y patrón Repository. Diseñado con las mejores prácticas de seguridad y desarrollo moderno.

## 📋 Tabla de Contenidos

- [Características](#-características)
- [Requisitos](#-requisitos)
- [Instalación](#-instalación)
- [Configuración](#-configuración)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Uso](#-uso)
- [Seguridad](#-seguridad)
- [Testing](#-testing)
- [Contribuir](#-contribuir)
- [Licencia](#-licencia)

## ✨ Características

- 🏗️ **Arquitectura MVC Limpia**: Separación total de responsabilidades
- 🔒 **Autenticación Robusta**: Sistema de login/registro seguro
- 📦 **Autoloader PSR-4**: Namespace `Enoc\Login\`
- 🌐 **Router Personalizado**: Sistema de rutas avanzado con protección
- 🛡️ **Middleware de Autenticación**: Protección de rutas por roles
- 🔧 **Variables de Entorno**: Configuración via `.env` con phpdotenv
- 💾 **Patrón Repository**: Abstracción de acceso a datos
- 🎯 **Traits Reutilizables**: Código modular y reutilizable
- 🔐 **Sesiones Seguras**: Detección HTTPS automática y cookies seguras
- 🛡️ **Protección CSRF**: Tokens anti-CSRF integrados

## 🛠️ Requisitos

- PHP >= 8.0 (declaración strict types)
- MySQL >= 5.7 o MariaDB >= 10.2
- Composer
- Servidor web (Apache/Nginx) con mod_rewrite
- Extensiones PHP:
  - PDO
  - PDO_MySQL
  - mbstring
  - openssl
  - json

## 📦 Instalación

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/cecv9/Login.git
   cd Login
   ```

2. **Instalar dependencias**
   ```bash
   composer install
   ```

3. **Configurar el servidor web**
   
   **Punto de entrada**: `public/index.php`
   
   **Apache (DocumentRoot apuntando a /public):**
   ```apache
   <VirtualHost *:80>
       DocumentRoot /ruta/al/proyecto/public
       ServerName tu-dominio.local
   </VirtualHost>
   ```

4. **Crear la base de datos**
   ```sql
   CREATE DATABASE login_mvc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

## ⚙️ Configuración

1. **Crear archivo de entorno**
   ```bash
   cp .env.example .env
   ```

2. **Configurar variables (.env)**
   ```env
   # Base de datos
   DB_DRIVER=mysql
   DB_HOST=localhost
   DB_USER=tu_usuario
   DB_PASS=tu_contraseña
   DB_NAME=login_mvc
   DB_CHARSET=utf8mb4
   DB_PORT=3306
   
   # Aplicación
   APP_DEBUG=true
   APP_TZ=America/El_Salvador
   APP_DOMAIN=localhost
   ```

## 📁 Estructura del Proyecto Real

```
Login/
├── 📁 app/
│   ├── 📁 Config/          # Configuraciones y rutas
│   │   ├── database.php    # Config BD (opcional)
│   │   └── routes.php      # Definición de rutas
│   ├── 📁 Controllers/     # Controladores MVC
│   ├── 📁 Core/           # Núcleo del framework
│   │   ├── Router.php     # Sistema de enrutamiento
│   │   ├── PdoConnection.php
│   │   └── DatabaseConnectionException.php
│   ├── 📁 models/         # Modelos de datos (lowercase)
│   ├── 📁 Repository/     # Patrón Repository para datos
│   └── 📁 Traits/         # Traits reutilizables
├── 📁 public/             # Punto de entrada público
│   ├── 📁 css/           # Hojas de estilo
│   ├── .htaccess         # Reescritura de URLs
│   └── index.php         # Front controller principal
├── 📁 views/              # Vistas/Templates
├── 📁 test/               # Pruebas
├── 📁 vendor/             # Dependencias Composer
├── .env                   # Variables de entorno
├── composer.json          # Configuración Composer
└── composer.lock          # Dependencias bloqueadas
```

## 🚀 Cómo Funciona

### Front Controller (public/index.php)

El archivo principal realiza la siguiente secuencia:

1. **Carga autoloader** de Composer
2. **Carga variables .env** con `phpdotenv`
3. **Configura modo debug** y timezone
4. **Establece conexión BD** via `PdoConnection`
5. **Configura sesiones seguras** con detección HTTPS
6. **Genera tokens CSRF** automáticamente
7. **Inicializa Router** y carga rutas
8. **Procesa la petición** actual

### Sistema de Rutas

```php
// En app/Config/routes.php
$router->get('/', 'HomeController@index');
$router->post('/login', 'AuthController@login');
$router->get('/dashboard', 'DashboardController@index');

// Rutas protegidas por autenticación
$router->protectRoute('/dashboard');

// Rutas protegidas por rol admin
$router->protectRoute('/admin/users/index.php', 'admin');
```

### Namespace y Autoloading

```php
// Todas las clases usan el namespace base
namespace Enoc\Login\Controllers;
namespace Enoc\Login\Core;
namespace Enoc\Login\Repository;

// Autoload PSR-4 configurado en composer.json:
"autoload": {
    "psr-4": {
        "Enoc\\Login\\": "app/"
    }
}
```

## 🔒 Seguridad Implementada

### Conexión Segura a BD
- **PDO con prepared statements**
- **Configuración centralizada** via `DatabaseConfig`
- **Manejo de excepciones** específicas

### Sesiones Avanzadas
- **Detección HTTPS** automática (proxies, Cloudflare, etc.)
- **Cookies seguras** con SameSite=Lax
- **Detección de dominio** automática
- **Configuración adaptativa** según entorno

### Protección CSRF
```php
// Token generado automáticamente en cada sesión
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
```

### Middleware de Autenticación
- **Protección por rutas**
- **Roles de usuario** (user, admin)
- **Redirección automática** a login

## 🧪 Testing

El directorio `test/` está preparado para:
- Pruebas unitarias de controladores
- Pruebas de integración de Repository
- Testing del sistema de rutas

## 📝 Ejemplo de Uso

### Definir nueva ruta
```php
// En app/Config/routes.php
$router->get('/perfil', 'UserController@profile');
$router->protectRoute('/perfil'); // Requiere login
```

### Crear controlador
```php
<?php
namespace Enoc\Login\Controllers;

class UserController 
{
    public function profile() 
    {
        // Lógica del controlador
        return view('user/profile');
    }
}
```

## 🔧 Dependencias

Según `composer.json`:
- **vlucas/phpdotenv**: ^5.6 - Manejo de variables de entorno

## 🤝 Contribuir

1. Fork del proyecto
2. Crear rama feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -m 'Agregar nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request

### Estándares
- **PHP 8.0+** con `declare(strict_types=1)`
- **PSR-4** para autoloading
- **Namespaces** bajo `Enoc\Login\`

## 📄 Licencia

Proyecto bajo Licencia MIT.

## 👨‍💻 Autor

**Enoc Castillo** - [@cecv9](https://github.com/cecv9)

---

⭐ **¡Dale una estrella si te ha sido útil!** ⭐
