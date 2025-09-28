<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Borrar Usuario') ?></title>
    <link rel="stylesheet" href="/css/admin.css">  <!-- Consistente con admin -->
</head>
<body>
<?php
// Navbar como en index/edit (SRP: Reutiliza partial sin duplicar)
$navbar = dirname(__DIR__, 2) . '/partials/navbar.php';
if (is_file($navbar)) {
    include $navbar;
}
?>

<div class="admin-container">
    <h1>Confirmar Borrado</h1>
    <a href="/admin/users">← Volver a Lista</a>  <!-- Breadcrumb UX -->

    <?php if (isset($_SESSION['success'])): ?>  <!-- Flashes de sesión (e.g., post-destroy) -->
        <div class="success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($error) && $error !== ''): ?>  <!-- Vars de controller si hay -->
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($user): ?>  <!-- Asume $user de controller; si null, redirect ya hecho -->
        <div class="confirmation-box">  <!-- UX: Muestra info antes de borrar -->
            <p><strong>¿Estás seguro de borrar al usuario?</strong></p>
            <p><strong>ID:</strong> <?= htmlspecialchars((string)$user->getId()) ?></p>
            <p><strong>Nombre:</strong> <?= htmlspecialchars($user->getName()) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($user->getEmail()) ?></p>
            <p><strong>Rol:</strong> <?= htmlspecialchars($user->getRole() ?? 'user') ?></p>
            <p class="warning">Esto es un borrado suave (puede recuperarse si se implementa). No afecta datos relacionados.</p>
        </div>

        <form method="POST" action="/admin/users/delete" class="admin-form">  <!-- Action estática -->
            <input type="hidden" name="id" value="<?= htmlspecialchars((string)$user->getId()) ?>">  <!-- Hidden ID para destroy -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">  <!-- CSRF -->
            <button type="submit" class="btn-danger">Confirmar Borrado</button>  <!-- UX: Botón rojo claro -->
            <a href="/admin/users" class="btn-cancel">Cancelar</a>
        </form>
    <?php else: ?>
        <div class="error">Usuario no encontrado.</div>
        <a href="/admin/users">Volver</a>
    <?php endif; ?>
</div>

<style>  <!-- CSS inline temporal si admin.css no tiene; mueve a CSS después -->
    .confirmation-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; background: #f9f9f9; }
    .warning { color: orange; font-style: italic; }
    .btn-danger { background: #dc3545; color: white; padding: 10px; text-decoration: none; }
    .btn-cancel { background: #6c757d; color: white; padding: 10px; text-decoration: none; margin-left: 10px; }
</style>
</body>
</html>