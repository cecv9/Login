<?php

declare(strict_types=1);

/**
 * DependencyContainer - Inversión de Control (IoC)
 *
 * ============================================================================
 * 🎯 PROPÓSITO UNO: Inyección de Dependencias
 * ============================================================================
 *
 * Este componente NO es un Service Manager (como Symfony's Container).
 * Este es un Dependency Injector SIMPLE y EFICIENTE:
 *
 * 1️⃣  BIND: Registrar cómo crear servicios
 * 2️⃣  GET: Obtener/construir instancias con dependencias
 * 3️⃣  AUTO-RESOLVE: Constructor Injection automático
 *
 * ============================================================================
 * 🚀 EJEMPLOS DE USO
 * ============================================================================
 *
 * // 1) Bind explícito (recommended)
 * $container->bind(PdoConnection::class, fn() => new PdoConnection($config));
 *
 * // 2) Auto-resolve (con type hints)
 * class Service {
 *     public function __construct(
 *         private readonly PdoConnection $pdo,  // Auto-injectado
 *         private readonly Logger $logger       // Auto-injectado
 *     ) {}
 * }
 * $service = $container->get(Service::class);
 *
 * ============================================================================
 * ⚡ CARACTERÍSTICAS
 * ============================================================================
 *
 * ✅ Thread-Safe Singleton Pattern
 * ✅ Lazy Loading (solo instancia cuando se necesita)
 * ✅ Instance Caching (evita múltiples objetos iguales)
 * ✅ Auto-Dependency Resolution
 * ✅ Type Safety con strict_types
 * ✅ PHPDoc generics para IDE support
 *
 * @package Enoc\Login\Core
 * @author Enoc (DI Container Implementation)
 * @version 1.0.0
 */
namespace Enoc\Login\Core;

/**
 * DependencyContainer - Inversión de Control
 *
 * Implementación simple de IoC Container para Dependency Injection
 * Siguiendo patrones PSR-11 (compatible para futuro upgrade)
 */
class DependencyContainer
{
    /**
     * Singleton Instance (Thread-safe)
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Service Bindings (abstract → concrete factory)
     *
     * @var array<string, callable> Mapeo de interfaces/classes a factories
     */
    private array $bindings = [];

    /**
     * Cached Instances (singleton behavior por defecto)
     *
     * @var array<string, object> Instancias cacheadas por completed
     */
    private array $instances = [];

    /**
     * ✅ Singleton Pattern - Obtener única instancia
     *
     * @return self La instancia única del container
     */
    public static function getInstance(): self
    {
        // Lazy initialization - solo crea la primera vez
        return self::$instance ??= new self();
    }

    /**
     * ✅ Registrar Servicio - Bind interface/class → factory
     *
     * @param string $abstract Nombre de la implementación/clase
     * @param callable $concrete Factory function que retorna la instancia
     * @return void
     *
     * @example
     * $container->bind(Logger::class, function($container) {
     *     return new FileLogger('/tmp/app.log');
     * });
     */
    public function bind(string $abstract, callable $concrete): void
    {
        $this->bindings[$abstract] = $concrete;

        // Si ya existe una instancia cacheada, limpiarla
        // Útil para testing/development hot-reload
        unset($this->instances[$abstract]);
    }

    /**
     * ✅ Obtener Instancia - Con auto-resolution y cache
     *
     * @template T
     * @param class-string<T> $abstract Clase o interfaz a resolver
     * @return T La instancia del servicio
     *
     * @throws \InvalidArgumentException Si no puede resolver clase
     *
     * @example
     * $pdo = $container->get(PdoConnection::class);
     * $service = $container->get(UserService::class);
     */
    public function get(string $abstract): mixed
    {
        // 1️⃣ CACHE HIT - Retornar instancia ya creada
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // 2️⃣ BINDING FOUND - Usar factory registrada
        if (isset($this->bindings[$abstract])) {
            $this->instances[$abstract] = $this->bindings[$abstract]($this);
            return $this->instances[$abstract];
        }

        // 3️⃣ AUTO-RESOLVE - Constructor injection automático
        $instance = $this->autoResolve($abstract);
        $this->instances[$abstract] = $instance;

        return $instance;
    }

    /**
     * ✅ Auto-Dependency Resolution - La magia del container
     *
     * Analiza constructor y resuelve dependencias automáticamente
     * Solo funciona con type hints en constructor (¡buena práctica obligada!)
     *
     * @param string $className Clase a instanciar
     * @return object Instancia con dependencias injectadas
     *
     * @throws \InvalidArgumentException Si no puede resolver una dependencia
     * @throws \ReflectionException Si clase no existe
     */
    private function autoResolve(string $className): object
    {
        // Validación: clase debe existir ser instanciable
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Class '{$className}' not found");
        }

        $reflection = new \ReflectionClass($className);

        // Clases abstractas/interfaces no pueden ser instanciadas
        if ($reflection->isAbstract() || $reflection->isInterface()) {
            throw new \InvalidArgumentException(
                "Cannot instantiate abstract/interface '{$className}'. Bind it first."
            );
        }

        $constructor = $reflection->getConstructor();

        // Sin constructor = sin dependencias = new Class()
        if ($constructor === null) {
            return new $className();
        }

        // Resolver cada parámetro del constructor
        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $dependencies[] = $this->resolveParameter($param, $className);
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * ✅ Resolver un Parámetro del Constructor
     *
     * @param \ReflectionParameter $param Parámetro del constructor
     * @param string $className Clase contenedora (para context de error)
     * @return mixed Valor resuelto del parámetro
     * @throws \InvalidArgumentException Si no puede resolver
     */
    private function resolveParameter(\ReflectionParameter $param, string $className): mixed
    {
        $type = $param->getType();

        // Caso 1: TYPE HINT -> Auto-resolve recursivo
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }

        // Caso 2: DEFAULT VALUE -> Usar valor por defecto
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        // Caso 3: OPTIONAL NULL -> Permitir null marcado como nullable
        if ($param->allowsNull()) {
            return null;
        }

        // Caso 4: ERROR - No se puede resolver
        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed';
        throw new \InvalidArgumentException(
            "Cannot resolve parameter '{$param->getName()}' in class '{$className}'. " .
            "Type: {$typeName}. Consider: " .
            "- Add a binding: \$container->bind({$typeName}::class, fn() => new ...); " .
            "- Set default value: ?{$typeName} \${$param->getName()} = null"
        );
    }
}