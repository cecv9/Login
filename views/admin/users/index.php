<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>

<?php
/** @var int $afterId */
/** @var Users[] $users */
?>

<div class="admin-container">

    <h1>Usuarios</h1>
    <a href="/admin/users/create">Crear Nuevo</a>
    <a href="/admin/audit">← Panel de Auditoria</a>
    <form action="/logout" method="POST">
        <?php if (!empty($csrfToken)): ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <button type="submit" class="btn-logout">Cerrar sesión</button>
    </form>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success"><?= htmlspecialchars($_SESSION['success']) ?></div>
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

    <!-- Cursor-based pagination -->
    <div class="pagination">
        <?php if (!empty($showNext) && $nextAfter !== null): ?>
            <a class="btn" href="?after=<?= (int)$nextAfter ?>&limit=<?= (int)$limit ?>">Siguiente →</a>
        <?php endif; ?>

        <?php if (!empty($showPrev) && $prevBefore !== null): ?>
            <a class="btn" href="?before=<?= (int)$prevBefore ?>&limit=<?= (int)$limit ?>">← Anterior</a>
        <?php endif; ?>
    </div>



</div>
</body>
</html>
