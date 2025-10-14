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
    <link rel="stylesheet" href="/css/audit.css">
<body>
<div class="container">
    <div class="header">
        <div class="breadcrumb">
            <a href="/admin/users">Usuarios</a> /
            <a href="/admin/audit">Dashboard de Auditoría</a> /
            Usuario ID <?= htmlspecialchars($userId) ?>
        </div>
        <h1>Acciones del Usuario ID <?= htmlspecialchars($userId) ?></h1>
        <p class="text-muted">Historial completo de operaciones realizadas</p>

        <form method="GET" action="/admin/audit/user" class="filter-form">
            <input type="hidden" name="id" value="<?= htmlspecialchars($userId) ?>">
            <label>
                Fecha específica (opcional)
                <input type="date" name="date" value="<?= htmlspecialchars($date ?? '') ?>">
            </label>
              <button type="submit" class="btn-primary">Filtrar</button>
            <?php if (!empty($date)): ?>
                <a href="/admin/audit/user?id=<?= htmlspecialchars($userId) ?>" class="btn btn-secondary">Limpiar filtro</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="actions">
        <a href="/admin/audit" class="btn-secondary">← Volver al Dashboard</a>
    </div>

    <?php if (!empty($actions)): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total de Acciones</h3>
            <div class="value"><?= count($actions) ?></div>
        </div>
        <div class="stat-card">
            <h3>Período</h3>
          <div class="value value-small">
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
                          class="link-primary">
                            <?= htmlspecialchars($action['context']['target_email'] ?? '-') ?>
                        </a>
                    <?php else: ?>
                        <?= htmlspecialchars($action['context']['target_email'] ?? '-') ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($action['context']['ip_address'] ?? 'N/A') ?></td>
               <td class="text-ellipsis"
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