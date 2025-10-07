<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Editar Usuario') ?></title>
    <link rel="stylesheet" href="/css/admin.css">  <!-- ← FIX: Usa admin CSS para consistencia -->
</head>
<body>
<?php
// ← NUEVO: Navbar como en index (opcional)
$navbar = dirname(__DIR__, 2) . '/partials/navbar.php';
if (is_file($navbar)) {
    include $navbar;
}
?>

<div class="admin-container">  <!-- ← FIX: Clase admin para estilo -->
    <h1>Editar Usuario</h1>
    <a href="/admin/users">← Volver a Lista</a>  <!-- ← NUEVO: Breadcrumb UX -->

    <?php if (isset($_SESSION['success'])): ?>  <!-- ← FIX: Mensajes de sesión como index -->
        <div class="success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($error) && $error !== ''): ?>  <!-- ← Mantén si controller pasa $error directo -->
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($success) && $success !== ''): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form class="admin-form" method="POST" action="/admin/users/update">  <!-- ← FIX: Action estática -->
        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$user->getId()) ?>">  <!-- ← FIX: Hidden ID para controller -->


        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

        <div class="form-group">
            <label for="name">Nombre:</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($user->getName()) ?>" required minlength="2">  <!-- ← FIX: Objeto ->getName() + escape -->
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user->getEmail()) ?>" required>  <!-- ← FIX: getEmail() -->
        </div>

        <div class="form-group">
            <label for="role">Rol:</label>
            <select id="role" name="role" required>  <!-- ← NUEVO: Select para rol -->
                <option value="user" <?= $user->getRole() === 'user' ? 'selected' : '' ?>>User</option>
                <option value="admin" <?= $user->getRole() === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="facturador" <?= $user->getRole() === 'facturador' ? 'selected' : '' ?>>Facturador</option>
                <option value="bodeguero" <?= $user->getRole() === 'bodeguero' ? 'selected' : '' ?>>Bodeguero</option>
                <option value="liquidador" <?= $user->getRole() === 'liquidador' ? 'selected' : '' ?>>Liquidador</option>
                <option value="vendedor_sistema" <?= $user->getRole() === 'vendedor_sistema' ? 'selected' : '' ?>>Vendedor con acceso al sistema</option>
            </select>
        </div>

        <div class="form-group">
            <label for="password">Nueva Contraseña (opcional):</label>
            <input type="password" id="password" name="password" minlength="6">  <!-- ← FIX: Sin required -->
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirmar Nueva Contraseña (opcional):</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="6">  <!-- ← FIX: Sintaxis (un solo <div>), sin required -->
        </div>

        <button type="submit">Actualizar Usuario</button>
    </form>
</div>

<!-- ← NUEVO: JS simple para match passwords (opcional, UX) -->
<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const pass = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        if (pass && pass !== confirm) {
            alert('Contraseñas no coinciden');
            e.preventDefault();
        }
    });
</script>
</body>
</html>