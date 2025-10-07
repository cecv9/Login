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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #333; margin-bottom: 10px; }
        .filter-form { display: flex; gap: 10px; align-items: end; margin-top: 15px; }
        .filter-form label { display: flex; flex-direction: column; font-size: 14px; color: #666; }
        .filter-form input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px; }
        .filter-form button { padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .filter-form button:hover { background: #0056b3; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card h3 { color: #666; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; }
        .card .value { font-size: 32px; font-weight: bold; color: #333; }
        .section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section h2 { color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #007bff; }
        table { width: 100%; border-collapse: collapse; }
        table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #666; border-bottom: 2px solid #dee2e6; }
        table td { padding: 12px; border-bottom: 1px solid #dee2e6; }
        table tr:hover { background: #f8f9fa; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .actions { display: flex; gap: 10px; margin-bottom: 20px; }
        .btn { padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; display: inline-block; }
        .btn:hover { background: #218838; }
        .empty { text-align: center; padding: 40px; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔍 Dashboard de Auditoría</h1>
        <p style="color: #666;">Monitoreo y análisis de actividad del sistema</p>

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
        <a href="/admin/users" class="btn" style="background: #6c757d;">
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
            <div class="value" style="color: #dc3545;"><?= number_format($report['failedAttempts'] ?? 0) ?></div>
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
                            <a href="/admin/audit/user?id=<?= $user['userId'] ?? 0 ?>" style="color: #007bff;">Ver acciones</a>
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