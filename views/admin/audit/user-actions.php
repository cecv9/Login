<?php
/**
 * Vista: Acciones de un usuario específico
 * Archivo: views/admin/audit/user-actions.php
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acciones del Usuario</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #333; margin-bottom: 10px; }
        .breadcrumb { color: #666; font-size: 14px; margin-bottom: 15px; }
        .breadcrumb a { color: #007bff; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .filter-form { display: flex; gap: 10px; align-items: end; margin-top: 15px; }
        .filter-form label { display: flex; flex-direction: column; font-size: 14px; color: #666; }
        .filter-form input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px; }
        .filter-form button { padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .filter-form button:hover { background: #0056b3; }
        .actions { display: flex; gap: 10px; margin-bottom: 20px; }
        .btn { padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block; }
        .btn:hover { background: #5a6268; }
        .section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section h2 { color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #007bff; }
        table { width: 100%; border-collapse: collapse; }
        table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #666; border-bottom: 2px solid #dee2e6; }
        table td { padding: 12px; border-bottom: 1px solid #dee2e6; }
        table tr:hover { background: #f8f9fa; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .empty { text-align: center; padding: 40px; color: #999; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #666; font-size: 12px; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card .value { font-size: 24px; font-weight: bold; color: #333; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="breadcrumb">
            <a href="/admin/users">Usuarios</a> /
            <a href="/admin/audit">Dashboard de Auditoría</a> /
            Usuario ID <?= htmlspecialchars($userId) ?>
        </div>
        <h1>Acciones del Usuario ID <?= htmlspecialchars($userId) ?></h1>
        <p style="color: #666;">Historial completo de operaciones realizadas</p>

        <form method="GET" action="/admin/audit/user" class="filter-form">
            <input type="hidden" name="id" value="<?= htmlspecialchars($userId) ?>">
            <label>
                Fecha específica (opcional)
                <input type="date" name="date" value="<?= htmlspecialchars($date ?? '') ?>">
            </label>
            <button type="submit">Filtrar</button>
            <?php if (!empty($date)): ?>
                <a href="/admin/audit/user?id=<?= htmlspecialchars($userId) ?>" class="btn">Limpiar filtro</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="actions">
        <a href="/admin/audit" class="btn">← Volver al Dashboard</a>
    </div>

    <?php if (!empty($actions)): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total de Acciones</h3>
            <div class="value"><?= count($actions) ?></div>
        </div>
        <div class="stat-card">
            <h3>Período</h3>
            <div class="value" style="font-size: 14px; margin-top: 5px;">
                <?= $date ?? 'Todos los registros' ?>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Historial de Acciones</h2>
        <table>
            <thead>
            <tr>
                <th>Fecha/Hora</th>
                <th>Acción</th>
                <th>Usuario Objetivo</th>
                <th>Email Objetivo</th>
                <th>Dirección IP</th>
                <th>Navegador</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($actions as $action): ?>
            <tr>
                <td><?= htmlspecialchars($action['context']['timestamp'] ?? 'N/A') ?></td>
                <td>
                    <?php
                    $actionType = $action['context']['action'] ?? 'N/A';
                    $badgeClass = match($actionType) {
                        'USER_CREATED' => 'badge-success',
                        'USER_UPDATED' => 'badge-info',
                        'USER_DELETED' => 'badge-danger',
                        default => 'badge-warning'
                    };
                    ?>
                    <span class="badge <?= $badgeClass ?>">
                        <?= htmlspecialchars($actionType) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($action['context']['target_user_id'] ?? '-') ?></td>
                <td>
                    <?php $targetId = $action['context']['target_user_id'] ?? null; ?>
                    <?php if ($targetId): ?>
                        <a href="/admin/audit/history?id=<?= $targetId ?>"
                           style="color: #007bff;">
                            <?= htmlspecialchars($action['context']['target_email'] ?? '-') ?>
                        </a>
                    <?php else: ?>
                        <?= htmlspecialchars($action['context']['target_email'] ?? '-') ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($action['context']['ip_address'] ?? 'N/A') ?></td>
                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                    title="<?= htmlspecialchars($action['context']['user_agent'] ?? '') ?>">
                    <?= htmlspecialchars(substr($action['context']['user_agent'] ?? 'N/A', 0, 50)) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="section">
        <div class="empty">
            <h2>No se encontraron acciones</h2>
            <p>El usuario con ID <?= htmlspecialchars($userId) ?> no ha realizado ninguna acción registrada<?= !empty($date) ? " en la fecha {$date}" : '' ?>.</p>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>