<?php
// views/partials/navbar.php
$current = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($current, PHP_URL_PATH) ?: '/';

function is_active_prefix(string $pattern, string $path): string {
    return str_starts_with(rtrim($path, '/'), rtrim($pattern, '/')) ? 'active' : '';
}
function is_active_exact(string $pattern, string $path): string {
    return rtrim($path, '/') === rtrim($pattern, '/') ? 'active' : '';
}
?>
<nav class="navbar">
    <div class="nav-inner">
        <a class="brand" href="/">Mi Admin</a>
        <ul class="nav-links">
            <li><a class="<?= is_active_exact('/admin', $path) ?>" href="/admin">Dashboard</a></li>
            <li><a class="<?= is_active_prefix('/admin/users', $path) ?>" href="/admin/users">Usuarios</a></li>
            <li><a class="<?= is_active_prefix('/admin/roles', $path) ?>" href="/admin/roles">Roles</a></li>
            <li><a class="<?= is_active_prefix('/admin/settings', $path) ?>" href="/admin/settings">Ajustes</a></li>
        </ul>
        <div class="nav-right">
            <a href="/profile" <?= is_active_prefix('/profile', $path) ? 'aria-current="page"' : '' ?>>Perfil</a>
            <form action="/logout" method="POST">
                <?php if (!empty($csrfToken)): ?>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <button type="submit" class="btn-logout">Cerrar sesión</button>
            </form>
        </div>
    </div>
</nav>
