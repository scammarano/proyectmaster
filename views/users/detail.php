<?php
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../../includes/rbac.php';

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

if ($isEdit) require_permission('users','edit');
else require_permission('users','create');

$err=''; $ok='';

$user = ['username'=>'','role'=>'viewer','is_active'=>1];
if ($isEdit) {
  $row = db_query("SELECT * FROM users WHERE id=?", [$id])->fetch();
  if (!$row) { echo "<div class='container py-3'><div class='alert alert-danger'>Usuario no encontrado.</div></div>"; require __DIR__.'/../layout/footer.php'; exit; }
  $user = $row;
}

$roles = rbac_ready() ? db_query("SELECT * FROM roles WHERE is_active=1 ORDER BY is_system DESC, name ASC")->fetchAll() : [];
$assigned = [];
if ($isEdit && rbac_ready()) {
  $assigned = db_query("SELECT role_id FROM user_roles WHERE user_id=?", [$id])->fetchAll(PDO::FETCH_COLUMN);
  $assigned = array_map('intval', $assigned);
}

if (isset($_GET['toggle_active']) && $isEdit) {
  require_permission('users','disable');
  db_query("UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id=?", [$id]);
  redirect('index.php?page=user_detail&id='.$id);
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $username = trim($_POST['username'] ?? '');
  $legacyRole = trim($_POST['legacy_role'] ?? 'viewer');
  $password = $_POST['password'] ?? '';
  $roleIds = $_POST['roles'] ?? [];

  if ($username==='') $err='Username obligatorio.';
  else {
    try{
      db_connect()->beginTransaction();
      if ($isEdit) {
        db_query("UPDATE users SET username=?, role=? WHERE id=?", [$username,$legacyRole,$id]);
        if ($password!=='') {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          db_query("UPDATE users SET password_hash=? WHERE id=?", [$hash,$id]);
        }
      } else {
        if ($password==='') throw new Exception('Password obligatorio al crear.');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db_query("INSERT INTO users (username,password_hash,role,is_active) VALUES (?,?,?,1)", [$username,$hash,$legacyRole]);
        $id = (int)db_connect()->lastInsertId();
        $isEdit = true;
      }

      if (rbac_ready() && can_perm('users','roles')) {
        db_query("DELETE FROM user_roles WHERE user_id=?", [$id]);
        if (is_array($roleIds)) {
          foreach($roleIds as $rid){
            db_query("INSERT IGNORE INTO user_roles(user_id, role_id) VALUES(?,?)", [$id,(int)$rid]);
          }
        }
      }

      db_connect()->commit();
      $ok='Usuario guardado.';
    } catch (Throwable $e) {
      db_connect()->rollBack();
      $err='Error: '.$e->getMessage();
    }
  }
}

if ($isEdit && rbac_ready()) {
  $assigned = db_query("SELECT role_id FROM user_roles WHERE user_id=?", [$id])->fetchAll(PDO::FETCH_COLUMN);
  $assigned = array_map('intval', $assigned);
}
?>
<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center">
    <h3><?= $isEdit ? 'Editar Usuario' : 'Crear Usuario' ?></h3>
    <a class="btn btn-outline-secondary" href="index.php?page=users">Volver</a>
  </div>

  <?php if($err): ?><div class="alert alert-danger mt-2"><?=h($err)?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-success mt-2"><?=h($ok)?></div><?php endif; ?>

  <?php if($isEdit && can_perm('users','disable')): ?>
    <div class="my-2">
      <a class="btn btn-outline-warning" href="index.php?page=user_detail&id=<?=$id?>&toggle_active=1">
        <?= ((int)$user['is_active']===1) ? 'Desactivar' : 'Activar' ?>
      </a>
    </div>
  <?php endif; ?>

  <form method="post" class="card p-3">
    <div class="row g-2">
      <div class="col-md-4">
        <label class="form-label">Username</label>
        <input class="form-control" name="username" value="<?=h($user['username'] ?? '')?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label"><?= $isEdit ? 'Password (opcional)' : 'Password' ?></label>
        <input class="form-control" name="password" type="password" <?= $isEdit ? '' : 'required' ?>>
      </div>
      <div class="col-md-4">
        <label class="form-label">Role (legacy)</label>
        <select class="form-select" name="legacy_role">
          <?php foreach(['admin','editor','viewer'] as $r): ?>
            <option value="<?=$r?>" <?=($user['role']??'viewer')===$r?'selected':''?>><?=$r?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if (rbac_ready() && can_perm('users','roles')): ?>
      <hr>
      <h5>Roles (RBAC)</h5>
      <div class="row">
        <?php foreach($roles as $r): $rid=(int)$r['id']; ?>
          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="roles[]" value="<?=$rid?>" <?=in_array($rid,$assigned,true)?'checked':''?>>
              <span class="form-check-label"><?=h($r['name'])?> <small class="text-muted">(<?=h($r['slug'])?>)</small></span>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="form-text">Si no asignas roles RBAC, aplica el role legacy.</div>
    <?php endif; ?>

    <div class="mt-3">
      <button class="btn btn-primary">Guardar</button>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
