<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Dashboard', ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="login-container">
    <div class="login-form">
        <h1>Dashboard</h1>
        <p>¡Login exitoso!</p>
        <p>Usuario: <?= htmlspecialchars($userName ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (!empty($userEmail)): ?>
            <p>Email: <?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?></p>

        <?php endif; ?>
        <form action="/logout" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <?php if (($_SESSION['user_role'] ?? 'user') === 'admin'): ?>
                <a href="/admin/users">Admin Panel</a>
            <?php endif; ?>
            <button type="submit">Cerrar sesión</button>
        </form>
    </div>
</div>
</body>
</html>