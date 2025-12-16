
<?php
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/functions.php';
if(!is_admin()){set_flash('error','Solo un administrador puede gestionar usuarios.');redirect('index.php');}

if(is_post()){
  $action=post_param('action');
  if($action==='create'){
    $u=trim(post_param('username')); $p=trim(post_param('password')); $r=post_param('role','editor');
    if($u===''||$p===''){set_flash('error','Usuario y contraseña son obligatorios.');}
    else{
      $hash=password_hash($p,PASSWORD_BCRYPT);
      db_query("INSERT INTO users (username,password_hash,role,is_active) VALUES (?,?,?,1)",[$u,$hash,$r]);
      set_flash('success','Usuario creado.');
    }
    redirect('index.php?page=users');
  }
  if($action==='update_role'){
    $id=(int)post_param('id'); $r=post_param('role','editor'); $act=post_param('is_active')?1:0;
    db_query("UPDATE users SET role=?,is_active=? WHERE id=?",[$r,$act,$id]);
    set_flash('success','Usuario actualizado.');
    redirect('index.php?page=users');
  }
}
$users=db_query("SELECT * FROM users ORDER BY id")->fetchAll();
include __DIR__.'/../layout/header.php';
?>
<h1 class="h3 mb-3"><i class="bi bi-people"></i> Usuarios</h1>
<div class="row">
  <div class="col-lg-6">
    <div class="card mb-3">
      <div class="card-header">Listado de usuarios</div>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
          <thead><tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Activo</th><th class="text-end">Acciones</th></tr></thead>
          <tbody>
          <?php foreach($users as $u):?>
          <tr>
            <form method="post">
              <input type="hidden" name="action" value="update_role">
              <input type="hidden" name="id" value="<?=h($u['id'])?>">
              <td><?=h($u['id'])?></td>
              <td><?=h($u['username'])?></td>
              <td>
                <select name="role" class="form-select form-select-sm">
                  <option value="admin"  <?=$u['role']==='admin'?'selected':''?>>admin</option>
                  <option value="editor" <?=$u['role']==='editor'?'selected':''?>>editor</option>
                  <option value="viewer" <?=$u['role']==='viewer'?'selected':''?>>viewer</option>
                </select>
              </td>
              <td class="text-center">
                <input type="checkbox" name="is_active" value="1" <?=$u['is_active']?'checked':''?>>
              </td>
              <td class="text-end"><button class="btn btn-sm btn-primary"><i class="bi bi-save"></i></button></td>
            </form>
          </tr>
          <?php endforeach; if(!$users):?>
          <tr><td colspan="5" class="text-center text-muted py-2">No hay usuarios.</td></tr>
          <?php endif;?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card mb-3">
      <div class="card-header">Nuevo usuario</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="create">
          <div class="col-md-4">
            <label class="form-label">Usuario</label>
            <input type="text" name="username" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Contraseña</label>
            <input type="text" name="password" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Rol</label>
            <select name="role" class="form-select">
              <option value="editor">editor</option>
              <option value="admin">admin</option>
              <option value="viewer">viewer</option>
            </select>
          </div>
          <div class="col-12 d-flex justify-content-end mt-2">
            <button class="btn btn-success"><i class="bi bi-plus-circle"></i> Crear usuario</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__.'/../layout/footer.php';?>
