# Diagrama de Flujo del Front Controller

```mermaid
flowchart TD
    A[Usuario accede a URL] --> B[public/index.php - Punto de Entrada]
    B --> C[Cargar configuración (.env)]
    C --> D[Configurar base de datos (PdoConnection)]
    D --> E[Configurar seguridad (headers, sesión, CSRF)]
    E --> F[Inicializar DependencyContainer]
    F --> G[Bindear dependencias: PdoConnection, Router, FrontController]
    G --> H[Obtener FrontController del container]
    H --> I[Llamar FrontController::handle()]

    I --> J[Crear Request::fromGlobals()]
    J --> K[Llamar Router::match(method, path)]
    K --> L{Ruta encontrada?}
    L -->|Sí| M[Llamar Router::executeHandler(handler, request)]
    L -->|No| N[Manejar 404: Response::notFound()]

    M --> O{Ejecutar handler}
    O --> P[Si es closure: call_user_func()]
    O --> Q[Si es Controller@method: Instanciar controlador con PdoConnection y llamar método]
    P --> R[Obtener respuesta del handler]
    Q --> R
    R --> S[Convertir respuesta: Response object, string o array]
    S --> T[Enviar respuesta: Response::send()]

    N --> U[Enviar respuesta 404]

    I --> V[Capturar excepciones]
    V --> W[Loggear error con LogManager]
    W --> X{Modo debug?}
    X -->|Sí| Y[Enviar respuesta JSON con detalles de error]
    X -->|No| Z[Enviar respuesta HTML genérica de error]

    T --> AA[Fin de solicitud]
    U --> AA
    Y --> AA
    Z --> AA
```

Este diagrama muestra el flujo completo desde que el usuario accede a una URL hasta que se envía la respuesta. Los componentes clave son:

- **index.php**: Bootstrap y configuración inicial.
- **FrontController**: Orquestador principal.
- **Router**: Manejo de rutas y ejecución de handlers.
- **Request/Response**: Objetos para solicitud y respuesta.
- **DependencyContainer**: Gestión de dependencias.
- **LogManager**: Logging de eventos y errores.

Para visualizar este diagrama en VS Code, instala la extensión "Mermaid Preview" y abre este archivo.
