<?php
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_permission('logs','view');

$qUser = trim($_GET['user'] ?? '');
$qFrom = trim($_GET['from'] ?? '');
$qTo   = trim($_GET['to'] ?? '');

$where = [];
$args  = [];

if ($qUser !== '') { $where[] = "u.username LIKE ?"; $args[] = "%$qUser%"; }
if ($qFrom !== '') { $where[] = "l.created_at >= ?"; $args[] = $qFrom . " 00:00:00"; }
if ($qTo   !== '') { $where[] = "l.created_at <= ?"; $args[] = $qTo   . " 23:59:59"; }

$sql = "SELECT l.*, u.username
        FROM user_logs l
        LEFT JOIN users u ON u.id = l.user_id";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY l.created_at DESC LIMIT 500";

$rows = db_query($sql, $args)->fetchAll();
?>
<div class="container py-3">
  <h3>Logs</h3>

  <form class="row g-2 mb-3">
    <div class="col-md-4">
      <input class="form-control" name="user" value="<?=h($qUser)?>" placeholder="Filtrar por username...">
    </div>
    <div class="col-md-3">
      <input class="form-control" name="from" type="date" value="<?=h($qFrom)?>">
    </div>
    <div class="col-md-3">
      <input class="form-control" name="to" type="date" value="<?=h($qTo)?>">
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-primary w-100">Filtrar</button>
    </div>
  </form>

  <table class="table table-sm table-striped">
    <thead>
      <tr>
        <th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>ID</th><th>IP</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?=h($r['created_at'] ?? '')?></td>
          <td><?=h($r['username'] ?? '-')?></td>
          <td><code><?=h($r['action'] ?? '')?></code></td>
          <td><?=h($r['entity'] ?? '-')?></td>
          <td><?=h($r['entity_id'] ?? '-')?></td>
          <td><?=h($r['ip'] ?? '-')?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
