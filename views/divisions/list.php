<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/helpers_safe.php';

require_login();
if(!is_admin()){ die('Solo admin'); }

$HAS_RULES = safe_table_exists('divisions') && (bool)db_query("SHOW COLUMNS FROM divisions LIKE 'prefix'")->fetch();

if (is_post()) {
  $action = post_param('action');

  if ($action==='save_division') {
    $id = (int)post_param('id',0);
    $name = trim(post_param('name',''));
    $prefix = strtoupper(trim(post_param('prefix','')));
    $rule_key = trim(post_param('rule_key',''));

    if($name===''){ set_flash('error','Nombre requerido'); redirect('index.php?page=divisions'); }

    if($id>0){
      if($HAS_RULES){
        db_query("UPDATE divisions SET name=?, prefix=?, rule_key=? WHERE id=?", [$name,$prefix?:null,$rule_key?:null,$id]);
      } else {
        db_query("UPDATE divisions SET name=? WHERE id=?", [$name,$id]);
      }
      if(function_exists('user_log')) user_log('division_update','division',$id,$name);
      set_flash('success','División actualizada.');
    } else {
      if($HAS_RULES){
        db_query("INSERT INTO divisions(name,prefix,rule_key) VALUES (?,?,?)", [$name,$prefix?:null,$rule_key?:null]);
      } else {
        db_query("INSERT INTO divisions(name) VALUES (?)", [$name]);
      }
      $new_id = (int)db_connect()->lastInsertId();
      if(function_exists('user_log')) user_log('division_create','division',$new_id,$name);
      set_flash('success','División creada.');
    }
    redirect('index.php?page=divisions');
  }

  if ($action==='delete_division') {
    $id=(int)post_param('id',0);
    if($id<=0) redirect('index.php?page=divisions');

    if(safe_table_exists('project_divisions')){
      $c=(int)db_query("SELECT COUNT(*) c FROM project_divisions WHERE division_id=?",[$id])->fetch()['c'];
      if($c>0){ set_flash('error','No se puede eliminar: usada en proyectos.'); redirect('index.php?page=divisions'); }
    }
    db_query("DELETE FROM divisions WHERE id=?",[$id]);
    if(function_exists('user_log')) user_log('division_delete','division',$id,'');
    set_flash('success','División eliminada.');
    redirect('index.php?page=divisions');
  }

  if ($action==='save_division_brands') {
    if(!safe_table_exists('division_brands')){
      set_flash('error','Falta tabla division_brands. Ejecuta alters.');
      redirect('index.php?page=divisions');
    }
    $division_id=(int)post_param('division_id',0);
    $brand_ids = $_POST['brand_ids'] ?? [];
    $brand_ids = array_values(array_filter(array_map('intval', is_array($brand_ids)?$brand_ids:[])));

    db_query("DELETE FROM division_brands WHERE division_id=?",[$division_id]);
    foreach($brand_ids as $bid){
      db_query("INSERT INTO division_brands(division_id,brand_id) VALUES (?,?)",[$division_id,$bid]);
    }
    if(function_exists('user_log')) user_log('division_brands_update','division',$division_id,'brands map');
    set_flash('success','Marcas asociadas a la división.');
    redirect('index.php?page=divisions#map'.$division_id);
  }
}

$divisions = safe_table_exists('divisions') ? db_query("SELECT * FROM divisions ORDER BY name")->fetchAll() : [];
$brands = safe_table_exists('brands') ? db_query("SELECT id,name FROM brands ORDER BY name")->fetchAll() : [];

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('Catálogo: Divisiones', `
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=projects"><i class="bi bi-house"></i> Proyectos</a>
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=articles"><i class="bi bi-box"></i> Artículos</a>
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=catalog_rules"><i class="bi bi-gear"></i> Reglas</a>
`);
</script>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Crear / editar división</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="save_division">
          <input type="hidden" name="id" id="div_id" value="">
          <div class="col-12">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="name" id="div_name" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Prefix</label>
            <input class="form-control" name="prefix" id="div_prefix" placeholder="PE">
          </div>
          <div class="col-md-8">
            <label class="form-label">rule_key</label>
            <input class="form-control" name="rule_key" id="div_rule" placeholder="electric_vimar / generic">
          </div>
          <div class="col-12 d-grid">
            <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
          </div>
          <div class="form-text">Prefix + rule_key se usan para numeración y reglas por división.</div>
        </form>
      </div>
    </div>

    <div class="alert alert-info mt-3 small">
      Si no ves prefix/rule_key, ejecuta el SQL: <code>alters_admin_divisions_articles_rules_v1.sql</code>.
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Divisiones</span>
        <span class="text-muted small">Editar, asociar marcas</span>
      </div>
      <div class="card-body">
        <?php if(!$divisions): ?>
          <div class="text-muted">No hay divisiones.</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle">
            <thead>
              <tr>
                <th>ID</th><th>Nombre</th><th>Prefix</th><th>rule_key</th><th class="text-end" style="width:240px">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($divisions as $d): ?>
              <tr id="map<?=h($d['id'])?>">
                <td><?=h($d['id'])?></td>
                <td class="fw-semibold"><?=h($d['name'])?></td>
                <td><?=h($d['prefix'] ?? '')?></td>
                <td><code><?=h($d['rule_key'] ?? '')?></code></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" type="button"
                    onclick="fillDiv(<?= (int)$d['id'] ?>, <?= json_encode($d['name']) ?>, <?= json_encode($d['prefix'] ?? '') ?>, <?= json_encode($d['rule_key'] ?? '') ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>

                  <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#brands<?=h($d['id'])?>">
                    <i class="bi bi-diagram-2"></i> Marcas
                  </button>

                  <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar división?');">
                    <input type="hidden" name="action" value="delete_division">
                    <input type="hidden" name="id" value="<?=h($d['id'])?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
              <tr class="collapse" id="brands<?=h($d['id'])?>">
                <td colspan="5">
                  <?php if(!safe_table_exists('division_brands')): ?>
                    <div class="text-danger">Falta tabla <code>division_brands</code>. Ejecuta alters.</div>
                  <?php else:
                    $mapped = db_query("SELECT brand_id FROM division_brands WHERE division_id=?",[(int)$d['id']])->fetchAll();
                    $mapped_ids = array_map(fn($r)=>(int)$r['brand_id'],$mapped);
                  ?>
                    <form method="post">
                      <input type="hidden" name="action" value="save_division_brands">
                      <input type="hidden" name="division_id" value="<?=h($d['id'])?>">
                      <div class="row g-2">
                        <div class="col-12">
                          <div class="small text-muted mb-1">Marcas permitidas en esta división:</div>
                          <div class="d-flex flex-wrap gap-2">
                            <?php foreach($brands as $b): ?>
                              <label class="border rounded px-2 py-1 small">
                                <input type="checkbox" name="brand_ids[]" value="<?=h($b['id'])?>" <?= in_array((int)$b['id'],$mapped_ids,true)?'checked':'' ?>>
                                <?=h($b['name'])?>
                              </label>
                            <?php endforeach; ?>
                          </div>
                        </div>
                        <div class="col-12 d-grid">
                          <button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Guardar marcas</button>
                        </div>
                      </div>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function fillDiv(id,name,prefix,rule){
  document.getElementById('div_id').value=id;
  document.getElementById('div_name').value=name||'';
  document.getElementById('div_prefix').value=prefix||'';
  document.getElementById('div_rule').value=rule||'';
  window.scrollTo({top:0,behavior:'smooth'});
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
