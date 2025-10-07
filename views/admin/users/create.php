<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Crear Usuario') ?></title>
    <link rel="stylesheet" href="/css/admin.css">  <!-- Consistente -->
</head>
<body>
<?php
// Navbar reutilizable
$navbarPath = dirname(__DIR__, 2) . '/partials/navbar.php';
if (is_file($navbarPath)) {
    include $navbarPath;
}
?>


<div class="admin-container">
    <h1>Crear Nuevo Usuario</h1>
    <a href="/admin/users">← Volver a Lista</a>

    <!-- ← NUEVO: Mensajes Globales (success/error genérico si no por campo) -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- ← CLAVE: Loop Errores por Campo del Trait -->
    <?php if (isset($_SESSION['errors']) && !empty($_SESSION['errors'])): ?>
        <?php foreach ($_SESSION['errors'] as $field => $fieldErrors): ?>
            <div class="field-error">
                <?= htmlspecialchars(implode('<br>', $fieldErrors)) ?>  <!-- Multi-errores si hay -->
            </div>
        <?php endforeach; ?>
        <?php unset($_SESSION['errors']); ?>  <!-- Limpia post-mostrar -->
    <?php endif; ?>

    <form class="admin-form" method="POST" action="/admin/users">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

        <div class="form-group <?= (isset($_SESSION['errors']['name']) || isset($_SESSION['errors']['name'])) ? 'has-error' : '' ?>">  <!-- ← Resalto si error en name -->
            <label for="name">Nombre:</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($name ?? '') ?>" required minlength="2">
            <?php if (isset($_SESSION['errors']['name'])): ?>  <!-- ← Error específico por campo -->
                <small class="error-text"><?= htmlspecialchars(end($_SESSION['errors']['name'])) ?></small>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($_SESSION['errors']['email']) ? 'has-error' : '' ?>">  <!-- ← Para email (duplicado aquí brilla) -->
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
            <?php if (isset($_SESSION['errors']['email'])): ?>
                <small class="error-text"><?= htmlspecialchars(end($_SESSION['errors']['email'])) ?></small>  <!-- e.g., "Email ya registrado" -->
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($_SESSION['errors']['role']) ? 'has-error' : '' ?>">
            <label for="role">Rol:</label>
            <select id="role" name="role" required>
                <?php foreach ($availableRoles as $value => $label): ?>  <!-- ← Vista solo itera datos -->
                    <option value="<?= htmlspecialchars($value) ?>">
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($_SESSION['errors']['role'])): ?>
                <small class="error-text"><?= htmlspecialchars(end($_SESSION['errors']['role'])) ?></small>  <!-- Muestra "Rol inválido" o "requerido" -->
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($_SESSION['errors']['password']) ? 'has-error' : '' ?>">
            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required minlength="6">
            <?php if (isset($_SESSION['errors']['password'])): ?>
                <small class="error-text"><?= htmlspecialchars(end($_SESSION['errors']['password'])) ?></small>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($_SESSION['errors']['confirm_password']) ? 'has-error' : '' ?>">  <!-- ← Para match -->
            <label for="confirm_password">Confirmar Contraseña:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            <?php if (isset($_SESSION['errors']['confirm_password'])): ?>
                <small class="error-text"><?= htmlspecialchars(end($_SESSION['errors']['confirm_password'])) ?></small>  <!-- e.g., "Confirm password no coincide con Password" -->
            <?php endif; ?>
        </div>

        <button type="submit">Crear Usuario</button>
    </form>
</div>

<!-- JS para UX (match client-side, opcional) -->
<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const pass = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        if (pass !== confirm) {
            alert('Contraseñas no coinciden');
            e.preventDefault();
        }
    });
</script>
</body>
</html>