# 🔐 Login MVC - Sistema de Autenticación

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![Status](https://img.shields.io/badge/Status-Active-brightgreen)

Un sistema de login robusto y seguro implementado con el patrón MVC (Modelo-Vista-Controlador) en PHP puro, diseñado siguiendo las mejores prácticas de desarrollo.

## 📋 Tabla de Contenidos

- [Características](#-características)
- [Requisitos](#-requisitos)
- [Instalación](#-instalación)
- [Configuración](#-configuración)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Uso](#-uso)
- [API Endpoints](#-api-endpoints)
- [Seguridad](#-seguridad)
- [Testing](#-testing)
- [Contribuir](#-contribuir)
- [Licencia](#-licencia)

## ✨ Características

- 🏗️ **Arquitectura MVC**: Separación clara de responsabilidades
- 🔒 **Autenticación Segura**: Hash de contraseñas con bcrypt
- 🎯 **Autoloader PSR-4**: Carga automática de clases
- 🌐 **URLs Amigables**: Sistema de enrutamiento personalizado
- 📱 **Responsive Design**: Interfaz adaptable a dispositivos móviles
- 🔧 **Variables de Entorno**: Configuración mediante .env
- 🧪 **Testing Ready**: Estructura preparada para pruebas
- 📝 **Validación de Datos**: Validación tanto client-side como server-side

## 🛠️ Requisitos

- PHP >= 7.4
- MySQL >= 5.7 o MariaDB >= 10.2
- Composer
- Servidor web (Apache/Nginx)
- Extensiones PHP:
  - PDO
  - PDO_MySQL
  - mbstring
  - openssl

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
   
   **Apache (.htaccess ya incluido):**
   ```apache
   DocumentRoot /ruta/al/proyecto/public
   ```
   
   **Nginx:**
   ```nginx
   server {
       listen 80;
       server_name tu-dominio.com;
       root /ruta/al/proyecto/public;
       index index.php;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }
       
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }
   ```

4. **Configurar la base de datos**
   ```sql
   CREATE DATABASE login_mvc;
   USE login_mvc;
   
   CREATE TABLE users (
       id INT AUTO_INCREMENT PRIMARY KEY,
       username VARCHAR(50) UNIQUE NOT NULL,
       email VARCHAR(100) UNIQUE NOT NULL,
       password VARCHAR(255) NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   );
   ```

## ⚙️ Configuración

1. **Crear archivo de configuración**
   ```bash
   cp .env.example .env
   ```

2. **Configurar variables de entorno (.env)**
   ```env
   # Base de datos
   DB_HOST=localhost
   DB_NAME=login_mvc
   DB_USER=tu_usuario
   DB_PASS=tu_contraseña
   DB_PORT=3306
   
   # Aplicación
   APP_NAME="Login MVC"
   APP_URL=http://localhost
   APP_ENV=development
   
   # Seguridad
   APP_KEY=tu_clave_secreta_aqui
   SESSION_LIFETIME=120
   ```

## 📁 Estructura del Proyecto

```
Login/
├── 📁 app/
│   ├── 📁 Config/          # Configuraciones de la aplicación
│   ├── 📁 Controllers/     # Controladores MVC
│   ├── 📁 Core/           # Núcleo del framework
│   ├── 📁 Models/         # Modelos de datos
│   ├── 📁 Repository/     # Patrón Repository
│   └── 📁 Traits/         # Traits reutilizables
├── 📁 public/             # Punto de entrada público
│   ├── 📁 css/           # Hojas de estilo
│   ├── 📁 js/            # Scripts JavaScript
│   └── index.php         # Front controller
├── 📁 views/              # Vistas/Templates
│   ├── 📁 auth/          # Vistas de autenticación
│   ├── 📁 layouts/       # Layouts base
│   └── 📁 partials/      # Componentes reutilizables
├── 📁 test/               # Pruebas unitarias
├── 📁 vendor/             # Dependencias de Composer
├── .env                   # Variables de entorno
├── .gitignore            # Archivos ignorados por Git
├── .htaccess             # Configuración Apache
├── composer.json         # Dependencias PHP
└── README.md             # Documentación
```

## 🚀 Uso

### Registro de Usuario

```php
// Ejemplo de uso del controlador
$userController = new \Enoc\Login\Controllers\UserController();
$result = $userController->register([
    'username' => 'nuevo_usuario',
    'email' => 'usuario@email.com',
    'password' => 'contraseña_segura'
]);
```

### Iniciar Sesión

```php
$result = $userController->login([
    'username' => 'nuevo_usuario',
    'password' => 'contraseña_segura'
]);
```

### Middleware de Autenticación

```php
// Proteger rutas que requieren autenticación
if (!AuthMiddleware::check()) {
    header('Location: /login');
    exit;
}
```

## 🌐 API Endpoints

| Método | Endpoint | Descripción | Parámetros |
|--------|----------|-------------|------------|
| `GET` | `/` | Página principal | - |
| `GET` | `/login` | Formulario de login | - |
| `POST` | `/login` | Procesar login | `username`, `password` |
| `GET` | `/register` | Formulario de registro | - |
| `POST` | `/register` | Procesar registro | `username`, `email`, `password` |
| `GET` | `/dashboard` | Panel de usuario | - |
| `POST` | `/logout` | Cerrar sesión | - |

## 🔒 Seguridad

### Medidas Implementadas

- **Hash de Contraseñas**: Uso de `password_hash()` con `PASSWORD_DEFAULT`
- **Prevención SQL Injection**: Prepared statements con PDO
- **Validación de Entrada**: Sanitización y validación de todos los inputs
- **Protección CSRF**: Tokens CSRF en formularios
- **Sesiones Seguras**: Configuración segura de sesiones PHP
- **Headers de Seguridad**: Content Security Policy, X-Frame-Options, etc.

### Ejemplo de Validación

```php
class Validator
{
    public static function validateEmail($email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePassword($password): bool
    {
        return strlen($password) >= 8 && 
               preg_match('/[A-Za-z]/', $password) &&
               preg_match('/[0-9]/', $password);
    }
}
```

## 🧪 Testing

Ejecutar las pruebas:

```bash
# Pruebas unitarias
composer test

# Cobertura de código
composer test:coverage
```

Estructura de pruebas:
```
test/
├── Unit/
│   ├── Controllers/
│   ├── Models/
│   └── Core/
└── Integration/
    └── Auth/
```

## 🤝 Contribuir

1. **Fork** el proyecto
2. **Crear** una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. **Commit** tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. **Push** a la rama (`git push origin feature/AmazingFeature`)
5. **Abrir** un Pull Request

### Estándares de Código

- Seguir PSR-12 para el estilo de código
- Documentar todas las funciones públicas
- Escribir pruebas para nuevas funcionalidades
- Mantener la cobertura de pruebas > 80%

## 📸 Screenshots

### Login Form
![Login](https://via.placeholder.com/600x400/2563eb/ffffff?text=Login+Form)

### Dashboard
![Dashboard](https://via.placeholder.com/600x400/059669/ffffff?text=User+Dashboard)

## 🚀 Roadmap

- [ ] Implementar autenticación con redes sociales
- [ ] Añadir sistema de roles y permisos
- [ ] Implementar recuperación de contraseña por email
- [ ] Agregar autenticación de dos factores (2FA)
- [ ] API REST completa
- [ ] Documentación con Swagger

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo [LICENSE](LICENSE) para más detalles.

## 👨‍💻 Autor

**Enoc** - [@cecv9](https://github.com/cecv9)

## 🙏 Agradecimientos

- Inspirado en las mejores prácticas de desarrollo PHP
- Comunidad de desarrolladores de PHP
- Contribuidores del proyecto

---

⭐ **¡Si te gusta este proyecto, no olvides darle una estrella!** ⭐
