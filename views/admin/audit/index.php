<?php
/**
 * Vista: Dashboard de Auditoría
 * Archivo: app/views/admin/audit/index.php
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Auditoría</title>
    <link rel="stylesheet" href="/css/audit.css">
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔍 Dashboard de Auditoría</h1>
        
        <p class="text-muted">Monitoreo y análisis de actividad del sistema</p>
        <form method="GET" action="/admin/audit" class="filter-form">
            <label>
                Fecha Inicio
                <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>" required>
            </label>
            <label>
                Fecha Fin
                <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>" required>
            </label>
            <button type="submit">Filtrar</button>
        </form>
    </div>

    <div class="actions">
        <a href="/admin/audit/export?start=<?= urlencode($startDate) ?>&end=<?= urlencode($endDate) ?>" class="btn">
            📥 Exportar JSON
        </a>
         <a href="/admin/users" class="btn btn-secondary">
            ← Volver a Usuarios
        </a>
    </div>

    <div class="grid">
        <div class="card">
            <h3>Total de Eventos</h3>
            <div class="value"><?= number_format($report['totalEvents'] ?? 0) ?></div>
        </div>
        <div class="card">
            <h3>Usuarios Activos</h3>
            <div class="value"><?= number_format($report['uniqueUsers'] ?? 0) ?></div>
        </div>
        <div class="card">
            <h3>Acciones de Modificación</h3>
            <div class="value"><?= number_format($report['modifications'] ?? 0) ?></div>
        </div>
        <div class="card">
            <h3>Intentos Fallidos</h3>
           <div class="value value-danger"><?= number_format($report['failedAttempts'] ?? 0) ?></div>
        </div>
    </div>

    <?php if (!empty($report['topUsers'])): ?>
        <div class="section">
            <h2>👥 Usuarios Más Activos</h2>
            <table>
                <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Total de Acciones</th>
                    <th>Última Actividad</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($report['topUsers'] as $user): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($user['username'] ?? "Usuario {$user['userId']}") ?></strong></td>
                        <td><?= number_format($user['count'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($user['lastActivity'] ?? '') ?></td>
                        <td>
                            <a href="/admin/audit/user?id=<?= $user['userId'] ?? 0 ?>" class="link-primary">Ver acciones</a>
                        </td>
                    </tr>

                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($report['recentEvents'])): ?>
        <div class="section">
            <h2>📋 Eventos Recientes</h2>
            <table>
                <thead>
                <tr>
                    <th>Fecha/Hora</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Objetivo</th>
                    <th>Estado</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($report['recentEvents'], 0, 50) as $event): ?>
                    <tr>
                        <td><?= htmlspecialchars($event['timestamp'] ?? '') ?></td>
                        <td><?= htmlspecialchars($event['userId'] ?? '') ?></td>
                        <td><?= htmlspecialchars($event['action'] ?? '') ?></td>
                        <td><?= htmlspecialchars($event['target'] ?? '-') ?></td>
                        <td>
                    <span class="badge badge-<?= ($event['success'] ?? true) ? 'info' : 'danger' ?>">
                        <?= ($event['success'] ?? true) ? 'Éxito' : 'Fallo' ?>
                    </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="section">
            <div class="empty">
                <p>No hay eventos registrados en el período seleccionado</p>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>