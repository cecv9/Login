<?php
declare(strict_types=1);

namespace Enoc\Login\Authorization\Policies;

use Enoc\Login\models\Users;

/**
 * 📜 PROPÓSITO: Definir el CONTRATO que toda política debe cumplir
 *
 * ANALOGÍA: Es como las reglas de un juego de mesa
 * "Todo jugador DEBE poder: mover ficha, lanzar dado, pasar turno"
 *
 * ¿QUÉ ES UNA INTERFAZ?
 * Es un CONTRATO que dice: "Si implementas esta interfaz,
 * DEBES tener estos métodos, te guste o no"
 *
 * ¿POR QUÉ USAR INTERFACES? (SOLID: Dependency Inversion)
 * - Estandarización: Todos hablan el mismo idioma
 * - Flexibilidad: Puedes cambiar la implementación sin romper nada
 * - Testing: Puedes crear políticas falsas para tests
 *
 * PRINCIPIO SOLID: Interface Segregation
 * "Los clientes no deberían depender de métodos que no usan"
 * Esta interfaz tiene SOLO los métodos esenciales
 */
interface PolicyInterface
{
    /**
     * 👁️ MÉTODO: ¿Puede VER el recurso?
     *
     * EJEMPLO:
     * - ¿Juan puede VER la lista de usuarios?
     * - ¿María puede VER esta factura específica?
     *
     * @param Users|null $actor El usuario que intenta la acción
     *                          (null = usuario no autenticado)
     * @param mixed $resource El recurso a ver (puede ser un Users, Invoice, etc.)
     * @return bool True si puede ver, False si no
     */
    public function view(?Users $actor, mixed $resource): bool;

    /**
     * ➕ MÉTODO: ¿Puede CREAR el recurso?
     *
     * EJEMPLO:
     * - ¿Juan puede CREAR un nuevo usuario?
     * - ¿María puede CREAR una nueva factura?
     *
     * NOTA: No necesita $resource porque estamos CREANDO algo nuevo
     *
     * @param Users|null $actor El usuario que intenta crear
     * @return bool True si puede crear, False si no
     */
    public function create(?Users $actor): bool;

    /**
     * ✏️ MÉTODO: ¿Puede ACTUALIZAR el recurso?
     *
     * EJEMPLO:
     * - ¿Juan puede EDITAR a este usuario específico?
     * - ¿María puede EDITAR esta factura?
     *
     * @param Users|null $actor El usuario que intenta editar
     * @param mixed $resource El recurso a editar
     * @return bool True si puede editar, False si no
     */
    public function update(?Users $actor, mixed $resource): bool;

    /**
     * 🗑️ MÉTODO: ¿Puede ELIMINAR el recurso?
     *
     * EJEMPLO:
     * - ¿Juan puede ELIMINAR a este usuario?
     * - ¿María puede ELIMINAR esta factura?
     *
     * @param Users|null $actor El usuario que intenta eliminar
     * @param mixed $resource El recurso a eliminar
     * @return bool True si puede eliminar, False si no
     */
    public function delete(?Users $actor, mixed $resource): bool;
}