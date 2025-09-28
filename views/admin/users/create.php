<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Registro') ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="login-container">
    <form class="login-form" method="POST" action="/register">
        <h1><?= htmlspecialchars($title ?? 'Registro') ?></h1>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

        <?php if (isset($success) && $success !== '') : ?>
            <div class="success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error) && $error !== '') : ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="name">Nombre:</label>
            <input type="text" id="name" name="name" required minlength="2">
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required minlength="6">
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirmar Contraseña:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
        </div>

        <button type="submit">Registrarse</button>
        <p><a href="/login">¿Ya tienes cuenta? Inicia sesión</a></p>
    </form>
</div>
</body>
</html>
