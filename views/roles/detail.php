<?php
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_permission('roles','permissions');

$id = (int)($_GET['id'] ?? 0);
$role = db_query("SELECT * FROM roles WHERE id=?", [$id])->fetch();
if (!$role) { echo "<div class='container py-3'><div class='alert alert-danger'>Rol no encontrado.</div></div>"; require __DIR__.'/../layout/footer.php'; exit; }

$err=''; $ok='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $allow = $_POST['allow'] ?? [];
  try {
    db_connect()->beginTransaction();

    // Ensure all perms exist in role_permissions
    db_query("INSERT IGNORE INTO role_permissions(role_id, permission_id, allowed)
              SELECT ?, p.id, 0 FROM permissions p", [$id]);

    // Reset all to 0
    db_query("UPDATE role_permissions SET allowed=0 WHERE role_id=?", [$id]);

    if (is_array($allow)) {
      foreach ($allow as $pid => $v) {
        db_query("UPDATE role_permissions SET allowed=1 WHERE role_id=? AND permission_id=?", [$id,(int)$pid]);
      }
    }

    db_connect()->commit();
    $ok='Permisos guardados.';
  } catch (Throwable $e) {
    db_connect()->rollBack();
    $err='Error: '.$e->getMessage();
  }
}

$perms = db_query("SELECT * FROM permissions ORDER BY module, action")->fetchAll();
$rp = db_query("SELECT permission_id, allowed FROM role_permissions WHERE role_id=?", [$id])->fetchAll();
$allowedMap = [];
foreach($rp as $row){ $allowedMap[(int)$row['permission_id']] = (int)$row['allowed']; }

$modules = [];
$actions = [];
foreach($perms as $p){
  $modules[$p['module']] = true;
  $actions[$p['action']] = true;
}
$modules = array_keys($modules);
sort($modules);
$actions = array_keys($actions);
sort($actions);

// Build grid: module -> action -> permission row
$grid = [];
foreach($perms as $p){
  $grid[$p['module']][$p['action']] = $p;
}
?>
<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center">
    <h3>Permisos: <?=h($role['name'])?> <small class="text-muted">(<?=h($role['slug'])?>)</small></h3>
    <a class="btn btn-outline-secondary" href="index.php?page=roles">Volver</a>
  </div>

  <?php if($err): ?><div class="alert alert-danger mt-2"><?=h($err)?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-success mt-2"><?=h($ok)?></div><?php endif; ?>

  <div class="my-2 d-flex gap-2">
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAll(true)">Marcar todo</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">Quitar todo</button>
  </div>

  <form method="post">
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th style="min-width:180px">Módulo</th>
            <?php foreach($actions as $a): ?>
              <th class="text-center"><?=h($a)?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach($modules as $m): ?>
          <tr>
            <th>
              <?=h($m)?>
              <div class="mt-1 d-flex gap-1">
                <button class="btn btn-xs btn-outline-primary" type="button" onclick="toggleModule('<?=h($m)?>', true)">Todo</button>
                <button class="btn btn-xs btn-outline-secondary" type="button" onclick="toggleModule('<?=h($m)?>', false)">Nada</button>
              </div>
            </th>
            <?php foreach($actions as $a): ?>
              <td class="text-center">
                <?php if(isset($grid[$m][$a])): $p=$grid[$m][$a]; $pid=(int)$p['id']; $ck = (!empty($allowedMap[$pid]) && (int)$allowedMap[$pid]===1); ?>
                  <input class="perm-check perm-<?=h($m)?>" type="checkbox" name="allow[<?=$pid?>]" value="1" <?=$ck?'checked':''?>>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <button class="btn btn-primary">Guardar</button>
  </form>
</div>

<script>
function toggleAll(on){
  document.querySelectorAll('.perm-check').forEach(cb => cb.checked = !!on);
}
function toggleModule(mod, on){
  document.querySelectorAll('.perm-' + CSS.escape(mod)).forEach(cb => cb.checked = !!on);
}
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
