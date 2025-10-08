<?php
declare(strict_types=1);

namespace Enoc\Login\Http\Forms;

use Enoc\Login\Dto\CreateUserDTO;
use Enoc\Login\Traits\ValidatorTrait;
use Enoc\Login\Enums\UserRole;

/**
 * 🎯 RESPONSABILIDAD ÚNICA: Validación SINTÁCTICA
 *
 * ANALOGÍA: Detector de metales en la entrada
 * - ¿Tiene formato de email? ✅ Pasa
 * - ¿Es un email inválido sintácticamente? ❌ No pasa
 *
 * NO VALIDA:
 * - Si el email ya existe (eso es negocio → Service)
 * - Si el usuario puede crear admins (eso es autorización → Service)
 * - Si el email pertenece a un dominio válido (eso es negocio → Service)
 *
 * SÍ VALIDA:
 * - Formato correcto (email tiene @, password ≥6 chars)
 * - Campos requeridos están presentes
 * - Rol existe en el sistema
 *
 * PRINCIPIO: Fail-fast
 * Si los datos ni siquiera tienen el formato correcto,
 * ¿para qué consultar la BD o verificar permisos?
 */
final class CreateUserForm
{
    use ValidatorTrait;

    /**
     * 🏗️ CONSTRUCTOR
     *
     * ¿POR QUÉ RECIBIR $post?
     * - Inyección de dependencias (fácil testear)
     * - No depende directamente de $_POST global
     * - Puedes pasar datos de prueba en tests
     *
     * @param array $post Datos del formulario
     */
    public function __construct(private array $post) {}

    /**
     * 🎯 MÉTODO PRINCIPAL: Validar y retornar DTO
     *
     * RETORNO:
     * - Si válido: ['dto' => CreateUserDTO]
     * - Si inválido: ['errors' => [...]]
     *
     * ¿POR QUÉ NO LANZAR EXCEPCIÓN?
     * - Los errores de sintaxis son "esperados" (usuario se equivoca)
     * - Las excepciones son para casos excepcionales
     * - Retornar array es más explícito
     *
     * @return array{dto?: CreateUserDTO, errors?: array}
     */
    public function handle(): array
    {
        // ══════════════════════════════════════════════════
        // PASO 1: NORMALIZACIÓN DE DATOS
        // ══════════════════════════════════════════════════

        // ¿POR QUÉ NORMALIZAR?
        // - Usuario puede enviar: "  Juan  " → queremos "Juan"
        // - Email puede venir: "JUAN@EMAIL.COM" → queremos "juan@email.com"
        // - Prevenir "user" vs " user " (con espacios)

        $name  = trim((string)($this->post['name'] ?? ''));
        // trim(): Quita espacios al inicio/final
        // (string): Cast a string por si viene null o int
        // ?? '': Si no existe la clave, usar string vacío

        $email = strtolower(trim((string)($this->post['email'] ?? '')));
        // strtolower(): Emails son case-insensitive
        // "Juan@Email.COM" === "juan@email.com"

        $pass  = (string)($this->post['password'] ?? '');
        // Contraseña NO se hace trim() porque los espacios pueden ser intencionales

        $cpass = (string)($this->post['confirm_password'] ?? '');

        $role  = trim((string)($this->post['role'] ?? UserRole::USER));
        // Si no envían rol, usar 'user' por defecto

        // ══════════════════════════════════════════════════
        // PASO 2: VALIDACIÓN DE SEGURIDAD (Rol existe)
        // ══════════════════════════════════════════════════

        // VALIDACIÓN ROBUSTA: Fail-safe
        // Si alguien hackea el form y envía role='hacker',
        // lo cambiamos silenciosamente a 'user'

        if (!UserRole::exists($role)) {
            // Si el rol no existe en el enum, usar fallback seguro
            $role = UserRole::USER;
        }

        // ¿POR QUÉ AQUÍ Y NO EN LAS REGLAS?
        // - Porque queremos GARANTIZAR que nunca llegue un rol inválido al DTO
        // - Es una protección adicional
        // - Si falla la validación de 'in:', al menos tenemos esto

        // ══════════════════════════════════════════════════
        // PASO 3: PREPARAR DATOS PARA VALIDACIÓN
        // ══════════════════════════════════════════════════

        $data = [
            'name' => $name,
            'email' => $email,
            'password' => $pass,
            'confirm_password' => $cpass,
            'role' => $role,
        ];

        // ══════════════════════════════════════════════════
        // PASO 4: DEFINIR REGLAS DE VALIDACIÓN
        // ══════════════════════════════════════════════════

        $rules = [
            // CAMPO: name
            'name' => [
                'required',    // No puede estar vacío
                'min:2',       // Mínimo 2 caracteres
                'max:100',     // Máximo 100 caracteres (nuevo)
            ],

            // CAMPO: email
            'email' => [
                'required',    // No puede estar vacío
                'email',       // Debe tener formato email válido (contiene @, dominio, etc.)
                'max:255',     // Máximo 255 chars (límite común de BD)
            ],

            // CAMPO: password
            'password' => [
                'required',    // No puede estar vacío
                'min:6',       // Mínimo 6 caracteres
                'max:255',     // Máximo 255 (antes del hash)
            ],

            // CAMPO: confirm_password
            'confirm_password' => [
                'required',    // No puede estar vacío
                'min:6',       // Mínimo 6
                'match:password',  // Debe coincidir con 'password'
            ],

            // CAMPO: role
            // ⭐ AQUÍ ESTÁ EL CAMBIO CRÍTICO ⭐
            'role' => [
                'required',    // Rol es obligatorio

                // ✅ ANTES: 'in:user,admin'
                // ❌ Problema: Solo aceptaba 2 roles

                // ✅ DESPUÉS: 'in:' . UserRole::forValidation()
                // ✅ Solución: Acepta TODOS los roles del enum

                'in:' . UserRole::forValidation(),

                // ¿QUÉ HACE UserRole::forValidation()?
                // Retorna: "admin,facturador,bodeguero,liquidador,vendedor_sistema,user"
                //
                // Entonces la regla se convierte en:
                // 'in:admin,facturador,bodeguero,liquidador,vendedor_sistema,user'
                //
                // ¿POR QUÉ ASÍ?
                // - Single Source of Truth: Los roles se definen UNA sola vez en UserRole
                // - Si agregas un rol nuevo al enum, automáticamente se acepta aquí
                // - No hay hardcoded strings
            ],
        ];

        // ══════════════════════════════════════════════════
        // PASO 5: EJECUTAR VALIDACIÓN
        // ══════════════════════════════════════════════════

        // validateUserData() está en el Trait ValidatorTrait
        // Revisa cada campo contra sus reglas
        $errors = $this->validateUserData($data, $rules);

        // ¿QUÉ RETORNA validateUserData()?
        // Si hay errores:
        // [
        //   'email' => ['Email inválido'],
        //   'password' => ['Password mínimo 6 caracteres']
        // ]
        //
        // Si todo OK:
        // []  (array vacío)

        if (!empty($errors)) {
            // Si hay errores, retornar para que Controller los muestre
            return ['errors' => $errors];
        }

        // ══════════════════════════════════════════════════
        // PASO 6: CREAR DTO (Data Transfer Object)
        // ══════════════════════════════════════════════════

        // DTO = Objeto inmutable que transporta datos entre capas
        //
        // ¿POR QUÉ DTO Y NO ARRAY?
        // - Type safety: PHP sabe qué tipo es cada campo
        // - Autocomplete en IDE
        // - Imposible tener campos incorrectos (typos)
        // - Documentación automática

        return ['dto' => new CreateUserDTO(
            name: $name,
            email: $email,
            password: $pass,
            role: $role,
            confirm_password: $cpass
        )];
    }
}