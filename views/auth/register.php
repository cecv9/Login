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
                <?= $success ?>  <!-- Ya escapado en controller -->
            </div>
        <?php endif; ?>

        <!-- ← NUEVO: Errores por Campo del Trait -->
        <?php if (isset($_SESSION['register_errors']) && !empty($_SESSION['register_errors'])): ?>
            <?php foreach ($_SESSION['register_errors'] as $field => $fieldErrors): ?>
                <div class="field-error"><?= htmlspecialchars(implode('<br>', $fieldErrors)) ?></div>
            <?php endforeach; ?>
            <?php unset($_SESSION['register_errors']); ?>
        <?php endif; ?>

        <?php if (isset($error) && $error !== '') : ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="form-group <?= isset($_SESSION['register_errors']['name']) ? 'has-error' : '' ?>">
            <label for="name">Nombre:</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($name ?? '') ?>" required minlength="2">
            <?php if (isset($_SESSION['register_errors']['name'])): ?>
                <small class="error-text"><?= htmlspecialchars(end($_SESSION['register_errors']['name'])) ?></small>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($_SESSION['register_errors']['email']) ? 'has-error' : '' ?>">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
            <?php if (isset($_SESSION['register_errors']['email'])): ?>
                <small class="error-text"><?= htmlspecialchars(end($_SESSION['register_errors']['email'])) ?></small>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($_SESSION['register_errors']['password']) ? 'has-error' : '' ?>">
            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required minlength="6">
            <?php if (isset($_SESSION['register_errors']['password'])): ?>
                <small class="error-text"><?= htmlspecialchars(end($_SESSION['register_errors']['password'])) ?></small>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($_SESSION['register_errors']['confirm_password']) ? 'has-error' : '' ?>">
            <label for="confirm_password">Confirmar Contraseña:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            <?php if (isset($_SESSION['register_errors']['confirm_password'])): ?>
                <small class="error-text"><?= htmlspecialchars(end($_SESSION['register_errors']['confirm_password'])) ?></small>
            <?php endif; ?>
        </div>

        <button type="submit">Registrarse</button>
        <p><a href="/login">¿Ya tienes cuenta? Inicia sesión</a></p>
    </form>
</div>

<!-- JS match passwords -->
<script>
    document.querySelector('form').addEventListener('submit', e => {
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