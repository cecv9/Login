<?php
/**
 * Vista: Historial de cambios de un usuario objetivo
 * Archivo: views/admin/audit/user-history.php
 *
 * Esta vista muestra todos los cambios que se le han hecho a un usuario específico
 * desde su creación hasta las modificaciones y posible eliminación.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial del Usuario</title>
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
        .timeline { position: relative; padding-left: 30px; }
        .timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: #dee2e6; }
        .timeline-item { position: relative; padding-bottom: 30px; }
        .timeline-item::before { content: ''; position: absolute; left: -24px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: white; border: 2px solid #007bff; }
        .timeline-item.created::before { background: #28a745; border-color: #28a745; }
        .timeline-item.updated::before { background: #17a2b8; border-color: #17a2b8; }
        .timeline-item.deleted::before { background: #dc3545; border-color: #dc3545; }
        .timeline-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .timeline-date { font-size: 14px; color: #666; }
        .timeline-content { background: #f8f9fa; padding: 15px; border-radius: 4px; border-left: 3px solid #007bff; }
        .timeline-content.created { border-left-color: #28a745; }
        .timeline-content.updated { border-left-color: #17a2b8; }
        .timeline-content.deleted { border-left-color: #dc3545; }
        .timeline-meta { font-size: 14px; color: #666; margin-bottom: 10px; }
        .timeline-meta strong { color: #333; }
        .changes { margin-top: 10px; }
        .change-item { padding: 8px; background: white; border-radius: 4px; margin-bottom: 5px; font-size: 14px; }
        .change-label { color: #666; display: inline-block; min-width: 120px; }
        .change-old { color: #dc3545; text-decoration: line-through; }
        .change-new { color: #28a745; font-weight: 500; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-danger { background: #f8d7da; color: #721c24; }
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
            Historial del Usuario ID <?= htmlspecialchars($targetId) ?>
        </div>
        <h1>Historial Completo del Usuario ID <?= htmlspecialchars($targetId) ?></h1>
        <p style="color: #666;">Cronología de todos los cambios realizados a esta cuenta</p>

        <form method="GET" action="/admin/audit/history" class="filter-form">
            <input type="hidden" name="id" value="<?= htmlspecialchars($targetId) ?>">
            <label>
                Desde
                <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>">
            </label>
            <label>
                Hasta
                <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>">
            </label>
            <button type="submit">Filtrar</button>
        </form>
    </div>

    <div class="actions">
        <a href="/admin/audit" class="btn">← Volver al Dashboard</a>
    </div>

    <?php if (!empty($history)): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total de Eventos</h3>
                <div class="value"><?= count($history) ?></div>
            </div>
            <div class="stat-card">
                <h3>Período</h3>
                <div class="value" style="font-size: 14px; margin-top: 5px;">
                    <?= htmlspecialchars($startDate) ?> - <?= htmlspecialchars($endDate) ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Última Modificación</h3>
                <div class="value" style="font-size: 14px; margin-top: 5px;">
                    <?= htmlspecialchars($history[0]['context']['timestamp'] ?? 'N/A') ?>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Línea de Tiempo</h2>
            <div class="timeline">
                <?php foreach ($history as $event): ?>
                    <?php
                    $action = $event['context']['action'] ?? 'UNKNOWN';
                    $timelineClass = match($action) {
                        'USER_CREATED' => 'created',
                        'USER_UPDATED' => 'updated',
                        'USER_DELETED' => 'deleted',
                        default => ''
                    };
                    $badgeClass = match($action) {
                        'USER_CREATED' => 'badge-success',
                        'USER_UPDATED' => 'badge-info',
                        'USER_DELETED' => 'badge-danger',
                        default => 'badge-info'
                    };
                    ?>
                    <div class="timeline-item <?= $timelineClass ?>">
                        <div class="timeline-header">
                        <span class="badge <?= $badgeClass ?>">
                            <?= htmlspecialchars($action) ?>
                        </span>
                            <span class="timeline-date">
                            <?= htmlspecialchars($event['context']['timestamp'] ?? 'N/A') ?>
                        </span>
                        </div>
                        <div class="timeline-content <?= $timelineClass ?>">
                            <div class="timeline-meta">
                                <strong>Realizado por:</strong>
                                <?= htmlspecialchars($event['context']['actor_username'] ?? 'Sistema') ?>
                                (ID: <?= htmlspecialchars($event['context']['actor_user_id'] ?? 'N/A') ?>)
                            </div>
                            <div class="timeline-meta">
                                <strong>Desde IP:</strong>
                                <?= htmlspecialchars($event['context']['ip_address'] ?? 'N/A') ?>
                            </div>

                            <?php if ($action === 'USER_CREATED'): ?>
                                <div class="changes">
                                    <div class="change-item">
                                        <span class="change-label">Nombre:</span>
                                        <span class="change-new"><?= htmlspecialchars($event['context']['target_name'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="change-item">
                                        <span class="change-label">Email:</span>
                                        <span class="change-new"><?= htmlspecialchars($event['context']['target_email'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="change-item">
                                        <span class="change-label">Rol:</span>
                                        <span class="change-new"><?= htmlspecialchars($event['context']['target_role'] ?? 'N/A') ?></span>
                                    </div>
                                </div>
                            <?php elseif ($action === 'USER_UPDATED'): ?>
                                <div class="changes">
                                    <?php if (isset($event['context']['old_name']) && $event['context']['old_name'] !== $event['context']['target_name']): ?>
                                        <div class="change-item">
                                            <span class="change-label">Nombre:</span>
                                            <span class="change-old"><?= htmlspecialchars($event['context']['old_name']) ?></span>
                                            →
                                            <span class="change-new"><?= htmlspecialchars($event['context']['target_name']) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($event['context']['old_email']) && $event['context']['old_email'] !== $event['context']['target_email']): ?>
                                        <div class="change-item">
                                            <span class="change-label">Email:</span>
                                            <span class="change-old"><?= htmlspecialchars($event['context']['old_email']) ?></span>
                                            →
                                            <span class="change-new"><?= htmlspecialchars($event['context']['target_email']) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($event['context']['old_role']) && $event['context']['old_role'] !== $event['context']['target_role']): ?>
                                        <div class="change-item">
                                            <span class="change-label">Rol:</span>
                                            <span class="change-old"><?= htmlspecialchars($event['context']['old_role']) ?></span>
                                            →
                                            <span class="change-new"><?= htmlspecialchars($event['context']['target_role']) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($event['context']['password_changed']) && $event['context']['password_changed']): ?>
                                        <div class="change-item">
                                            <span class="change-label">Contraseña:</span>
                                            <span class="change-new">Modificada</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($action === 'USER_DELETED'): ?>
                                <div class="changes">
                                    <div class="change-item">
                                        <span class="change-label">Email eliminado:</span>
                                        <span class="change-old"><?= htmlspecialchars($event['context']['target_email'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="change-item" style="background: #fff3cd; border-left: 3px solid #ffc107;">
                                        <strong>⚠️ Usuario marcado como eliminado (soft delete)</strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="section">
            <div class="empty">
                <h2>No se encontró historial</h2>
                <p>No hay eventos registrados para el usuario con ID <?= htmlspecialchars($targetId) ?> en el período seleccionado.</p>
                <p style="margin-top: 10px; font-size: 14px; color: #666;">
                    Esto podría significar que el usuario fue creado fuera del período de búsqueda o que no ha sufrido modificaciones.
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
