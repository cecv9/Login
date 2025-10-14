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
    <link rel="stylesheet" href="/css/audit.css">
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
        <p class="text-muted">Cronología de todos los cambios realizados a esta cuenta</p>

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
                 <button type="submit" class="btn-primary">Filtrar</button>    
        </form>
    </div>

    <div class="actions">
         <a href="/admin/audit" class="btn btn-secondary">← Volver al Dashboard</a>
    </div>

    <?php if (!empty($history)): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total de Eventos</h3>
                <div class="value"><?= count($history) ?></div>
            </div>
            <div class="stat-card">
                <h3>Período</h3>
                <div class="value value-small">
                    <?= htmlspecialchars($startDate) ?> - <?= htmlspecialchars($endDate) ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Última Modificación</h3>
                < <div class="value value-small">
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
                                   <div class="change-item highlight">
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
                  <p class="info-note">
                    Esto podría significar que el usuario fue creado fuera del período de búsqueda o que no ha sufrido modificaciones.
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
