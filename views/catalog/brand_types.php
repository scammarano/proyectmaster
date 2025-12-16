<?php
/**
 * Catálogo — Tipos de Artículos por Marca
 * Página: index.php?page=catalog_brand_types
 *
 * ✅ Mantiene lo existente: asignar tipos de artículos a marcas
 * ✅ Agrega CRUD: crear / editar / eliminar tipos de artículos
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

if (!function_exists('add_flash') && function_exists('set_flash')) {
  function add_flash($t,$m){ set_flash($t,$m); }
}

if (!function_exists('table_exists')) {
  function table_exists(string $table): bool {
    try { $stmt = db_query("SHOW TABLES LIKE ?", [$table]); return (bool)$stmt->fetch(); }
    catch (Throwable $e) { return false; }
  }
}
if (!function_exists('col_exists')) {
  function col_exists(string $table, string $col): bool {
    try { return (bool)db_query("SHOW COLUMNS FROM `$table` LIKE ?", [$col])->fetch(); }
    catch (Throwable $e) { return false; }
  }
}
function slugify_local(string $s): string {
  $s = trim(mb_strtolower($s,'UTF-8'));
  $s = preg_replace('~[^\pL\pN]+~u', '-', $s);
  $s = trim($s,'-');
  if ($s==='') $s = 'type';
  return $s;
}

$HAS_TYPES = table_exists('article_types');
$HAS_BRANDS = table_exists('brands');
$HAS_PIVOT = table_exists('brand_article_types'); // esperado: (brand_id, article_type_id)
$HAS_ARTICLES = table_exists('articles');

if (!$HAS_TYPES || !$HAS_BRANDS || !$HAS_PIVOT) {
  include __DIR__ . '/../layout/header.php';
  echo '<div class="alert alert-danger">Faltan tablas requeridas: ';
  echo !$HAS_TYPES ? '<code>article_types</code> ' : '';
  echo !$HAS_BRANDS ? '<code>brands</code> ' : '';
  echo !$HAS_PIVOT ? '<code>brand_article_types</code> ' : '';
  echo '</div>';
  include __DIR__ . '/../layout/footer.php';
  exit;
}

$HAS_TYPE_SLUG = col_exists('article_types','slug');
$HAS_TYPE_CODE = col_exists('article_types','code'); // por si tu esquema usa code

if (is_post()) {
  $action = post_param('action','');

  // ============ CRUD TIPOS ============
  if ($action === 'type_create') {
    $name = trim(post_param('name',''));
    if ($name==='') { add_flash('error','Nombre requerido.'); redirect('index.php?page=catalog_brand_types'); }

    if ($HAS_TYPE_SLUG) {
      $slug = trim(post_param('slug',''));
      if ($slug==='') $slug = slugify_local($name);

      // evitar duplicados de slug si existe
      $exists = db_query("SELECT id FROM article_types WHERE slug=? LIMIT 1", [$slug])->fetch();
      if ($exists) {
        // si está tomado, agrega sufijo
        $baseSlug = $slug;
        $i = 2;
        while ($exists) {
          $slug = $baseSlug.'-'.$i;
          $exists = db_query("SELECT id FROM article_types WHERE slug=? LIMIT 1", [$slug])->fetch();
          $i++;
          if ($i>200) break;
        }
      }

      db_query("INSERT INTO article_types(name,slug) VALUES (?,?)", [$name,$slug]);
    } elseif ($HAS_TYPE_CODE) {
      $code = trim(post_param('code',''));
      if ($code==='') $code = strtoupper(substr(slugify_local($name),0,10));
      db_query("INSERT INTO article_types(name,code) VALUES (?,?)", [$name,$code]);
    } else {
      db_query("INSERT INTO article_types(name) VALUES (?)", [$name]);
    }

    add_flash('success','Tipo creado.');
    redirect('index.php?page=catalog_brand_types');
  }

  if ($action === 'type_update') {
    $id = (int)post_param('id',0);
    $name = trim(post_param('name',''));
    if ($id<=0 || $name==='') { add_flash('error','Datos inválidos.'); redirect('index.php?page=catalog_brand_types'); }

    if ($HAS_TYPE_SLUG) {
      $slug = trim(post_param('slug',''));
      if ($slug==='') $slug = slugify_local($name);

      // validar slug único para otros IDs
      $dup = db_query("SELECT id FROM article_types WHERE slug=? AND id<>? LIMIT 1", [$slug,$id])->fetch();
      if ($dup) { add_flash('error','Slug ya existe.'); redirect('index.php?page=catalog_brand_types'); }

      db_query("UPDATE article_types SET name=?, slug=? WHERE id=?", [$name,$slug,$id]);
    } elseif ($HAS_TYPE_CODE) {
      $code = trim(post_param('code',''));
      if ($code==='') $code = strtoupper(substr(slugify_local($name),0,10));
      db_query("UPDATE article_types SET name=?, code=? WHERE id=?", [$name,$code,$id]);
    } else {
      db_query("UPDATE article_types SET name=? WHERE id=?", [$name,$id]);
    }

    add_flash('success','Tipo actualizado.');
    redirect('index.php?page=catalog_brand_types');
  }

  if ($action === 'type_delete') {
    $id = (int)post_param('id',0);
    if ($id<=0) { add_flash('error','Datos inválidos.'); redirect('index.php?page=catalog_brand_types'); }

    $usedPivot = (int)db_query("SELECT COUNT(*) c FROM brand_article_types WHERE article_type_id=?", [$id])->fetch()['c'];
    $usedArts  = 0;
    if ($HAS_ARTICLES && col_exists('articles','article_type_id')) {
      $usedArts = (int)db_query("SELECT COUNT(*) c FROM articles WHERE article_type_id=?", [$id])->fetch()['c'];
    }

    if ($usedPivot>0 || $usedArts>0) {
      add_flash('error',"No se puede eliminar: asignado a marcas($usedPivot) / usado en artículos($usedArts).");
      redirect('index.php?page=catalog_brand_types');
    }

    db_query("DELETE FROM article_types WHERE id=?", [$id]);
    add_flash('success','Tipo eliminado.');
    redirect('index.php?page=catalog_brand_types');
  }

  // ============ ASIGNACIÓN MARCA ↔ TIPO ============
  if ($action === 'assign') {
    $brand_id = (int)post_param('brand_id',0);
    $type_id  = (int)post_param('article_type_id',0);
    if ($brand_id<=0 || $type_id<=0) { add_flash('error','Selecciona marca y tipo.'); redirect('index.php?page=catalog_brand_types'); }

    db_query("INSERT IGNORE INTO brand_article_types(brand_id,article_type_id) VALUES (?,?)", [$brand_id,$type_id]);
    add_flash('success','Tipo asignado a la marca.');
    redirect('index.php?page=catalog_brand_types&brand_id='.$brand_id);
  }

  if ($action === 'unassign') {
    $brand_id = (int)post_param('brand_id',0);
    $type_id  = (int)post_param('article_type_id',0);
    if ($brand_id<=0 || $type_id<=0) { add_flash('error','Datos inválidos.'); redirect('index.php?page=catalog_brand_types'); }

    db_query("DELETE FROM brand_article_types WHERE brand_id=? AND article_type_id=?", [$brand_id,$type_id]);
    add_flash('success','Tipo removido de la marca.');
    redirect('index.php?page=catalog_brand_types&brand_id='.$brand_id);
  }
}

// ======= DATA =======
$brands = db_query("SELECT id, name FROM brands ORDER BY name")->fetchAll();
$types  = db_query("SELECT id, name"
  . ($HAS_TYPE_SLUG ? ", slug" : "")
  . ($HAS_TYPE_CODE ? ", code" : "")
  . " FROM article_types ORDER BY name")->fetchAll();

$brand_id = (int)get_param('brand_id', 0);
if ($brand_id<=0 && $brands) $brand_id = (int)$brands[0]['id'];

$assigned = [];
if ($brand_id>0) {
  $assigned = db_query("
    SELECT t.id, t.name"
      . ($HAS_TYPE_SLUG ? ", t.slug" : "")
      . ($HAS_TYPE_CODE ? ", t.code" : "")
    . " FROM brand_article_types bat
    JOIN article_types t ON t.id = bat.article_type_id
    WHERE bat.brand_id=?
    ORDER BY t.name
  ", [$brand_id])->fetchAll();
}
$assignedIds = array_flip(array_map(fn($r)=> (int)$r['id'], $assigned));

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('Catálogo: Tipos de Artículo por Marca', `
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=catalog"><i class="bi bi-arrow-left"></i> Volver</a>
`);
</script>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h1 class="h4 mb-0"><i class="bi bi-tags"></i> Tipos de Artículo por Marca</h1>
</div>

<div class="row g-3">

  <!-- ====== ASIGNACIÓN A MARCAS ====== -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-link-45deg"></i> Asignación Marca → Tipos</span>
        <span class="text-muted small">Selecciona marca y asigna tipos</span>
      </div>
      <div class="card-body">

        <form method="get" class="row g-2 mb-3">
          <input type="hidden" name="page" value="catalog_brand_types">
          <div class="col-md-8">
            <label class="form-label">Marca</label>
            <select class="form-select" name="brand_id" onchange="this.form.submit()">
              <?php foreach($brands as $b): ?>
                <option value="<?=h($b['id'])?>" <?= ((int)$b['id']===$brand_id?'selected':'') ?>><?=h($b['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <form method="post" class="row g-2 mb-3">
          <input type="hidden" name="action" value="assign">
          <input type="hidden" name="brand_id" value="<?=h($brand_id)?>">
          <div class="col-md-8">
            <label class="form-label">Tipo a asignar</label>
            <select class="form-select" name="article_type_id" required>
              <option value="">(selecciona)</option>
              <?php foreach($types as $t): if(isset($assignedIds[(int)$t['id']])) continue; ?>
                <option value="<?=h($t['id'])?>"><?=h($t['name'])?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">La lista excluye los tipos ya asignados.</div>
          </div>
          <div class="col-md-4 d-grid align-self-end">
            <button class="btn btn-primary"><i class="bi bi-plus-circle"></i> Asignar</button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle">
            <thead>
              <tr>
                <th>Tipo</th>
                <th class="text-muted small">Identificador</th>
                <th class="text-end" style="width:120px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if(!$assigned): ?>
                <tr><td colspan="3" class="text-muted text-center py-3">No hay tipos asignados a esta marca.</td></tr>
              <?php else: foreach($assigned as $t): ?>
                <tr>
                  <td><?=h($t['name'])?></td>
                  <td class="text-muted small">
                    <?php
                      if ($HAS_TYPE_SLUG) echo h($t['slug'] ?? '—');
                      elseif ($HAS_TYPE_CODE) echo h($t['code'] ?? '—');
                      else echo '—';
                    ?>
                  </td>
                  <td class="text-end">
                    <form method="post" class="d-inline" onsubmit="return confirm('¿Quitar tipo de esta marca?');">
                      <input type="hidden" name="action" value="unassign">
                      <input type="hidden" name="brand_id" value="<?=h($brand_id)?>">
                      <input type="hidden" name="article_type_id" value="<?=h($t['id'])?>">
                      <button class="btn btn-sm btn-outline-warning" title="Quitar"><i class="bi bi-x-circle"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>

  <!-- ====== CRUD TIPOS ====== -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-pencil-square"></i> Tipos de Artículo (CRUD)</span>
        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#createType">
          <i class="bi bi-plus-circle"></i> Nuevo
        </button>
      </div>
      <div class="card-body">

        <div class="collapse mb-3" id="createType">
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="type_create">
            <div class="col-12">
              <label class="form-label">Nombre</label>
              <input class="form-control" name="name" required>
            </div>

            <?php if($HAS_TYPE_SLUG): ?>
              <div class="col-12">
                <label class="form-label">Slug (opcional)</label>
                <input class="form-control" name="slug" placeholder="ej: soporte, placa, camara">
                <div class="form-text">Si lo dejas vacío se genera automáticamente.</div>
              </div>
            <?php elseif($HAS_TYPE_CODE): ?>
              <div class="col-12">
                <label class="form-label">Code (opcional)</label>
                <input class="form-control" name="code" placeholder="ej: SOP, PLA, CAM">
              </div>
            <?php endif; ?>

            <div class="col-12 d-grid">
              <button class="btn btn-primary"><i class="bi bi-check2"></i> Crear</button>
            </div>
          </form>
          <hr>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle">
            <thead>
              <tr>
                <th>Tipo</th>
                <th class="text-muted small">Identificador</th>
                <th class="text-end" style="width:140px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if(!$types): ?>
                <tr><td colspan="3" class="text-muted text-center py-3">No hay tipos.</td></tr>
              <?php else: foreach($types as $t): $tid=(int)$t['id']; ?>
                <tr>
                  <td><?=h($t['name'])?></td>
                  <td class="text-muted small">
                    <?php
                      if ($HAS_TYPE_SLUG) echo h($t['slug'] ?? '—');
                      elseif ($HAS_TYPE_CODE) echo h($t['code'] ?? '—');
                      else echo '—';
                    ?>
                  </td>
                  <td class="text-end">

                    <button class="btn btn-sm btn-outline-secondary" type="button"
                            data-bs-toggle="modal" data-bs-target="#mdlType<?=h($tid)?>" title="Editar">
                      <i class="bi bi-pencil"></i>
                    </button>

                    <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar tipo? Solo si no está asignado ni usado por artículos.');">
                      <input type="hidden" name="action" value="type_delete">
                      <input type="hidden" name="id" value="<?=h($tid)?>">
                      <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                    </form>

                  </td>
                </tr>

                <div class="modal fade" id="mdlType<?=h($tid)?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <form method="post">
                        <input type="hidden" name="action" value="type_update">
                        <input type="hidden" name="id" value="<?=h($tid)?>">
                        <div class="modal-header">
                          <h5 class="modal-title">Editar tipo</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <div class="mb-2">
                            <label class="form-label">Nombre</label>
                            <input class="form-control" name="name" value="<?=h($t['name'])?>" required>
                          </div>

                          <?php if($HAS_TYPE_SLUG): ?>
                            <div class="mb-2">
                              <label class="form-label">Slug</label>
                              <input class="form-control" name="slug" value="<?=h($t['slug'] ?? '')?>">
                            </div>
                          <?php elseif($HAS_TYPE_CODE): ?>
                            <div class="mb-2">
                              <label class="form-label">Code</label>
                              <input class="form-control" name="code" value="<?=h($t['code'] ?? '')?>">
                            </div>
                          <?php endif; ?>

                          <div class="form-text">Esto no cambia las asignaciones existentes a marcas.</div>
                        </div>
                        <div class="modal-footer">
                          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                          <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <div class="alert alert-info mt-3 mb-0">
      <div class="fw-semibold">Nota</div>
      <div class="small">
        El CRUD administra el catálogo global de <code>article_types</code>. <br>
        La sección izquierda controla la asignación por marca (<code>brand_article_types</code>).
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
