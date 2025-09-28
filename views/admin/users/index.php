<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>

<?php
$navbar = dirname(__DIR__, 2) . '/partials/navbar.php';
if (is_file($navbar)) {
    include $navbar;
} else {
    error_log("Navbar no encontrado en: {$navbar}");
    // echo "<!-- navbar missing -->";
}
?>

<div class="admin-container">

    <h1>Usuarios</h1>
    <a href="/admin/users/create">Crear Nuevo</a>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <table>
        <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php if (empty($users) || !is_array($users)): ?>
            <tr><td colspan="5">No hay usuarios</td></tr>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user->getId() ?></td>
                    <td><?= htmlspecialchars($user->getName()) ?></td>
                    <td><?= htmlspecialchars($user->getEmail()) ?></td>
                    <td><?= htmlspecialchars($user->getRole() ?? 'user') ?></td>
                    <td>
                        <a href="/admin/users/edit?id=<?= htmlspecialchars((string)$user->getId()) ?>">Editar</a>
                        <a href="/admin/users/delete?id=<?= htmlspecialchars((string)$user->getId()) ?>" onclick="return confirm('¿Borrar este usuario?')">Borrar</a>  <!-- ← FIX: Mensaje más claro, escape ID -->
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Paginación simple -->
    <div class="pagination">
        <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">Anterior</a><?php endif; ?>
        Página <?= $page ?> de <?= $pages ?>
        <?php if ($page < $pages): ?><a href="?page=<?= $page + 1 ?>">Siguiente</a><?php endif; ?>
    </div>
</div>
</body>
</html>
