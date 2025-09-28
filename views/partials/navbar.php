<?php
// views/partials/navbar.php
$current = $_SERVER['REQUEST_URI'] ?? '/';

function is_active(string $pattern, string $uri): string {
    // Marca como activo si la URL actual empieza con el patrón (ej: "/admin/users")
    return (strpos($uri, $pattern) === 0) ? 'active' : '';
}
?>
<nav class="navbar">
    <div class="nav-inner">
        <a class="brand" href="/">Mi Admin</a>
        <ul class="nav-links">
            <li><a class="<?= is_active('/admin', $current) ?>" href="/admin">Dashboard</a></li>
            <li><a class="<?= is_active('/admin/users', $current) ?>" href="/admin/users">Usuarios</a></li>
            <li><a class="<?= is_active('/admin/roles', $current) ?>" href="/admin/roles">Roles</a></li>
            <li><a class="<?= is_active('/admin/settings', $current) ?>" href="/admin/settings">Ajustes</a></li>
        </ul>
        <div class="nav-right">
            <!-- Enlaces vacíos por ahora -->
            <a href="/profile">Perfil</a>
            <a href="/logout">Salir</a>
        </div>
    </div>
</nav>

