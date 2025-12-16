<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();
if (!is_admin()) die('Solo admin');

if (!function_exists('table_exists')) {
  function table_exists(string $table): bool {
    try { $stmt=db_query("SHOW TABLES LIKE ?",[$table]); return (bool)$stmt->fetch(); }
    catch(Throwable $e){ return false; }
  }
}

$HAS_BRANDS = table_exists('brands');
$HAS_TYPES  = table_exists('article_types');
$HAS_MAP    = table_exists('brand_article_types');

if (!$HAS_BRANDS || !$HAS_TYPES) {
  include __DIR__ . '/../layout/header.php';
  echo '<div class="alert alert-danger">Faltan tablas requeridas: '.(!$HAS_BRANDS?'brands ':'').(!$HAS_TYPES?'article_types ':'').'</div>';
  include __DIR__ . '/../layout/footer.php';
  exit;
}
if (!$HAS_MAP) {
  // Intentamos crearla (safe) — si falla por permisos, mostramos instrucción.
  try {
    db_query("CREATE TABLE IF NOT EXISTS brand_article_types (
      brand_id INT NOT NULL,
      article_type_id INT NOT NULL,
      PRIMARY KEY (brand_id, article_type_id),
      CONSTRAINT fk_bat_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
      CONSTRAINT fk_bat_type  FOREIGN KEY (article_type_id) REFERENCES article_types(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $HAS_MAP = true;
  } catch (Throwable $e) {
    $HAS_MAP = false;
    $CREATE_ERR = $e->getMessage();
  }
}

$brands = db_query("SELECT id,name FROM brands ORDER BY name")->fetchAll();
$types  = db_query("SELECT id,code,name FROM article_types ORDER BY name")->fetchAll();

$brand_id = (int)get_param('brand_id', 0);
if ($brand_id<=0 && !empty($brands)) $brand_id = (int)$brands[0]['id'];

function get_allowed(int $brand_id): array {
  if ($brand_id<=0) return [];
  $rows = db_query("SELECT article_type_id FROM brand_article_types WHERE brand_id=?",[$brand_id])->fetchAll();
  return array_map(fn($r)=>(int)$r['article_type_id'], $rows);
}

if (is_post()) {
  if (!$HAS_MAP) {
    set_flash('error','No existe brand_article_types y no se pudo crear automáticamente.');
    redirect('index.php?page=catalog_brand_types&brand_id='.$brand_id);
  }
  $brand_id = (int)post_param('brand_id', 0);
  $ids = $_POST['type_ids'] ?? [];
  $ids = array_values(array_filter(array_map('intval', is_array($ids)?$ids:[])));

  if ($brand_id<=0) {
    set_flash('error','Selecciona una marca.');
    redirect('index.php?page=catalog_brand_types');
  }

  // Guardar: reemplazo completo
  db_connect()->beginTransaction();
  try {
    db_query("DELETE FROM brand_article_types WHERE brand_id=?",[$brand_id]);
    if ($ids) {
      $sql = "INSERT INTO brand_article_types (brand_id, article_type_id) VALUES (?,?)";
      foreach ($ids as $tid) db_query($sql, [$brand_id, $tid]);
    }
    db_connect()->commit();
    set_flash('success','Tipos permitidos guardados.');
  } catch (Throwable $e) {
    db_connect()->rollBack();
    set_flash('error','Error guardando: '.$e->getMessage());
  }
  redirect('index.php?page=catalog_brand_types&brand_id='.$brand_id);
}

$allowed = $HAS_MAP ? get_allowed($brand_id) : [];
$allowedSet = array_flip($allowed);

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('Catálogo: Tipos por marca', `
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=brands"><i class="bi bi-arrow-left"></i> Volver a Marcas</a>
`);
</script>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h1 class="h4 mb-0"><i class="bi bi-diagram-3"></i> Tipos de artículo por marca</h1>
</div>

<?php if(!$HAS_MAP): ?>
  <div class="alert alert-warning">
    No existe la tabla <code>brand_article_types</code> y no se pudo crear automáticamente.
    <div class="small text-muted mt-1">Detalle: <?=h($CREATE_ERR ?? 'sin detalle')?></div>
    <hr>
    Ejecuta este SQL en phpMyAdmin:
    <pre class="mb-0"><code>CREATE TABLE brand_article_types (
  brand_id INT NOT NULL,
  article_type_id INT NOT NULL,
  PRIMARY KEY (brand_id, article_type_id),
  CONSTRAINT fk_bat_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
  CONSTRAINT fk_bat_type  FOREIGN KEY (article_type_id) REFERENCES article_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</code></pre>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Marca</div>
      <div class="card-body">
        <form method="get">
          <input type="hidden" name="page" value="catalog_brand_types">
          <select class="form-select" name="brand_id" onchange="this.form.submit()">
            <?php foreach($brands as $b): ?>
              <option value="<?=h($b['id'])?>" <?= ((int)$b['id']===$brand_id)?'selected':'' ?>><?=h($b['name'])?></option>
            <?php endforeach; ?>
          </select>
        </form>

        <div class="alert alert-info mt-3 mb-0 small">
          Esto controla el combo <b>Tipo de artículo</b> en el formulario de Artículos (Marca → Tipos permitidos).
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Tipos permitidos</span>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setAll(true)"><i class="bi bi-check2-square"></i> Todos</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setAll(false)"><i class="bi bi-square"></i> Ninguno</button>
        </div>
      </div>
      <div class="card-body">
        <?php if(!$types): ?>
          <div class="text-muted">No hay tipos en <code>article_types</code>.</div>
        <?php else: ?>
          <form method="post" onsubmit="return confirm('¿Guardar tipos permitidos para esta marca?');">
            <input type="hidden" name="brand_id" value="<?=h($brand_id)?>">
            <div class="table-responsive">
              <table class="table table-sm table-striped align-middle">
                <thead>
                  <tr>
                    <th style="width:42px;"></th>
                    <th>Tipo</th>
                    <th class="text-muted small">code</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($types as $t): $tid=(int)$t['id']; $ck=isset($allowedSet[$tid]); ?>
                    <tr>
                      <td><input class="form-check-input chk" type="checkbox" name="type_ids[]" value="<?=h($tid)?>" <?= $ck?'checked':'' ?>></td>
                      <td><?=h($t['name'])?></td>
                      <td class="text-muted small"><code><?=h($t['code'])?></code></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <button class="btn btn-primary" <?= $HAS_MAP?'':'disabled' ?>><i class="bi bi-save"></i> Guardar</button>
            <?php if($HAS_MAP): ?>
              <a class="btn btn-outline-secondary" href="index.php?page=catalog_brand_types&brand_id=<?=h($brand_id)?>"><i class="bi bi-arrow-clockwise"></i> Recargar</a>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function setAll(v){ document.querySelectorAll('.chk').forEach(ch=>ch.checked=v); }
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
