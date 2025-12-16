<?php
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_permission('roles','view');

$err=''; $ok='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  require_permission('roles','create');
  $name = trim($_POST['name'] ?? '');
  $slug = trim($_POST['slug'] ?? '');
  if ($name==='' || $slug==='') $err='Nombre y slug son obligatorios.';
  else {
    try {
      db_query("INSERT INTO roles(name,slug,is_active,is_system) VALUES(?,?,1,0)", [$name,$slug]);
      $ok='Rol creado.';
    } catch (Throwable $e) { $err='Error creando rol: '.$e->getMessage(); }
  }
}

if (isset($_GET['toggle_id'])) {
  require_permission('roles','edit');
  $id=(int)$_GET['toggle_id'];
  db_query("UPDATE roles SET is_active = IF(is_active=1,0,1) WHERE id=? AND is_system=0", [$id]);
  redirect('index.php?page=roles');
}

$roles = db_query("SELECT * FROM roles ORDER BY is_system DESC, name ASC")->fetchAll();
?>
<div class="container py-3">
  <div class="d-flex align-items-center justify-content-between">
    <h3>Roles</h3>
    <?php if (can_perm('roles','create')): ?>
      <form method="post" class="d-flex gap-2">
        <input class="form-control form-control-sm" name="name" placeholder="Nombre" required>
        <input class="form-control form-control-sm" name="slug" placeholder="slug" required>
        <button class="btn btn-sm btn-primary">Crear</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if($err): ?><div class="alert alert-danger mt-2"><?=h($err)?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-success mt-2"><?=h($ok)?></div><?php endif; ?>

  <table class="table table-sm table-striped mt-3">
    <thead><tr><th>ID</th><th>Nombre</th><th>Slug</th><th>Activo</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach($roles as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h($r['name']) ?></td>
          <td><code><?= h($r['slug']) ?></code></td>
          <td><?= ((int)$r['is_active']===1)?'Sí':'No' ?></td>
          <td class="text-nowrap">
            <?php if (can_perm('roles','permissions')): ?>
              <a class="btn btn-sm btn-outline-primary" href="index.php?page=role_detail&id=<?=(int)$r['id']?>">Permisos</a>
            <?php endif; ?>
            <?php if ((int)$r['is_system']===0 && can_perm('roles','edit')): ?>
              <a class="btn btn-sm btn-outline-secondary" href="index.php?page=roles&toggle_id=<?=(int)$r['id']?>">Activar/Desactivar</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
