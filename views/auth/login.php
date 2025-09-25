<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Login') ?></title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="login-container">
    <form class="login-form" method="POST" action="/login">
        <h1><?= htmlspecialchars($title ?? 'Login') ?></h1>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">



        <?php if (isset($error) && $error !== '') : ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="password">Contrasenia:</label>
            <input type="password" id="password" name="password" required>
        </div>


        <button type="submit">Iniciar Sesión</button>
        <p>¿Ya tienes cuenta? <a href="/register">Registrarse</a></p>
    </form>
</div>
</body>
</html>