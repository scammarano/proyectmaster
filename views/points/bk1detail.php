<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attachments.php';
require_once __DIR__ . '/../../includes/rules.php';
require_once __DIR__ . '/../../includes/point_rules.php'; // autonumeración (si existe)

require_login();

/**
 * Helpers defensivos (tu proyecto ha tenido varias migraciones).
 */
if (!function_exists('add_flash') && function_exists('set_flash')) {
  function add_flash($t,$m){ set_flash($t,$m); }
}
if (!function_exists('col_exists')) {
  function col_exists(string $table, string $col): bool {
    try { return (bool)db_query("SHOW COLUMNS FROM `$table` LIKE ?", [$col])->fetch(); }
    catch (Throwable $e) { return false; }
  }
}
if (!function_exists('table_exists')) {
  function table_exists(string $table): bool {
    try { return (bool)db_query("SHOW TABLES LIKE ?", [$table])->fetch(); }
    catch (Throwable $e) { return false; }
  }
}

$point_id = (int)get_param('id', 0);
$area_id  = (int)get_param('area_id', 0);

$point = null;
if ($point_id > 0) {
  $point = db_query("SELECT * FROM points WHERE id=?", [$point_id])->fetch();
  if (!$point) die('Punto no encontrado');
  if ($area_id <= 0) $area_id = (int)($point['area_id'] ?? 0);
}
if ($area_id <= 0) die('Falta area_id');

$area = db_query("SELECT * FROM areas WHERE id=?", [$area_id])->fetch();
if (!$area) die('Área no encontrada');

$project_id = (int)$area['project_id'];
$project = db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch();
if (!$project) die('Proyecto no encontrado');

$HAS_CLOSED = col_exists('projects','is_closed');
$closed = $HAS_CLOSED ? ((int)($project['is_closed'] ?? 0)===1) : false;

/**
 * Divisiones permitidas:
 * - si existe project_divisions: usar solo las asociadas al proyecto
 * - si no: usar todas
 */
$divisions = [];
if (table_exists('divisions')) {
  if (table_exists('project_divisions')) {
    $divisions = db_query("
      SELECT d.*
      FROM divisions d
      JOIN project_divisions pd ON pd.division_id=d.id
      WHERE pd.project_id=?
      ORDER BY d.name
    ", [$project_id])->fetchAll();
  }
  if (!$divisions) {
    $divisions = db_query("SELECT * FROM divisions ORDER BY name")->fetchAll();
  }
}

$HAS_DIV_PREFIX = table_exists('divisions') && col_exists('divisions','prefix');

function division_prefix(int $division_id): string {
  if (!table_exists('divisions')) return '';
  $row = db_query("SELECT ".(col_exists('divisions','prefix')?'prefix':'name')." AS v FROM divisions WHERE id=?", [$division_id])->fetch();
  if (!$row) return '';
  $v = trim((string)$row['v']);
  if (col_exists('divisions','prefix')) return strtoupper($v);
  // fallback a iniciales del nombre
  $v = preg_replace('/[^A-Za-z0-9]+/','', strtoupper($v));
  return substr($v,0,4);
}

/**
 * Reglas desde el asistente:
 * config/rules.php debe retornar un array (no JSON).
 * - points: clave por prefix (PE, CCTV, etc.)
 */
function rules_for_point_prefix(string $prefix): array {
  $rules = load_rules();
  $all = $rules['points'] ?? [];
  $prefix = strtoupper(trim($prefix));
  return $all[$prefix] ?? ($all['default'] ?? [
    'requires_support'=>false,
    'requires_plate'=>false,
    'requires_fruits'=>false,
    'auto_fill_blanks'=>false,
    'ask_fill_blanks'=>false,
    'ui'=>['show_location'=>true],
  ]);
}

function get_article(int $id): ?array {
  if ($id<=0) return null;
  $a = db_query("SELECT * FROM articles WHERE id=?", [$id])->fetch();
  return $a ?: null;
}

function sum_fruit_modules(int $point_id): int {
  if (!table_exists('point_components')) return 0;
  // detectar columnas qty/quantity
  $qtyCol = col_exists('point_components','qty') ? 'qty' : (col_exists('point_components','quantity') ? 'quantity' : null);
  if (!$qtyCol) $qtyCol = 'qty';
  // filtrar solo role=fruit si existe
  $roleWhere = col_exists('point_components','role') ? " AND pc.role='fruit' " : "";
  $sql = "SELECT COALESCE(SUM(a.modules * pc.$qtyCol),0) AS sm
          FROM point_components pc
          JOIN articles a ON a.id=pc.article_id
          WHERE pc.point_id=? $roleWhere";
  $row = db_query($sql, [$point_id])->fetch();
  return (int)($row['sm'] ?? 0);
}

function support_modules_from_point(array $point): int {
  $sid = (int)($point['support_article_id'] ?? 0);
  if ($sid<=0) return 0;
  $a = get_article($sid);
  return (int)($a['modules'] ?? 0);
}

if (!function_exists('find_blank_module_article_id')) {
function find_blank_module_article_id(?int $brand_id, ?int $series_id): int {
  // heurística: busca "ciego" en nombre o código. Se puede mejorar luego.
  $where = " (name LIKE '%ciego%' OR code LIKE '%041%' OR code LIKE '%CIEG%') ";
  $params = [];
  if (col_exists('articles','brand_id') && $brand_id) { $where .= " AND brand_id=?"; $params[]=$brand_id; }
  if (col_exists('articles','series_id') && $series_id) { $where .= " AND series_id=?"; $params[]=$series_id; }
  $row = db_query("SELECT id FROM articles WHERE $where ORDER BY id LIMIT 1", $params)->fetch();
  return (int)($row['id'] ?? 0);
}
}

function ensure_blank_fill(int $point_id, int $missing, ?int $brand_id, ?int $series_id): void {
  if ($missing<=0) return;
  if (!table_exists('point_components')) return;

  $blankId = find_blank_module_article_id($brand_id, $series_id);
  if ($blankId<=0) return;

  $qtyCol = col_exists('point_components','qty') ? 'qty' : (col_exists('point_components','quantity') ? 'quantity' : 'qty');
  $roleCol = col_exists('point_components','role') ? 'role' : null;

  for ($i=0; $i<$missing; $i++) {
    if ($roleCol) {
      db_query("INSERT INTO point_components(point_id,article_id,$qtyCol,role) VALUES (?,?,1,'fruit')", [$point_id,$blankId]);
    } else {
      db_query("INSERT INTO point_components(point_id,article_id,$qtyCol) VALUES (?,?,1)", [$point_id,$blankId]);
    }
  }
}

/**
 * Carga opciones de catálogo filtradas por división/marca/serie.
 */
function list_articles_for_select(?int $division_id, ?int $brand_id, ?int $series_id, ?int $article_type_id): array {
  $where = "1=1";
  $params = [];

  if (col_exists('articles','brand_id') && $brand_id) { $where.=" AND a.brand_id=?"; $params[]=$brand_id; }
  if (col_exists('articles','series_id') && $series_id) { $where.=" AND a.series_id=?"; $params[]=$series_id; }
  if (col_exists('articles','article_type_id') && $article_type_id) { $where.=" AND a.article_type_id=?"; $params[]=$article_type_id; }

  // filtro por división: si existe article_divisions, usa esa relación
  if ($division_id && table_exists('article_divisions')) {
    $where .= " AND EXISTS (SELECT 1 FROM article_divisions ad WHERE ad.article_id=a.id AND ad.division_id=?)";
    $params[] = $division_id;
  }

  return db_query("SELECT a.id,a.code,a.name,a.modules FROM articles a WHERE $where ORDER BY a.code,a.name", $params)->fetchAll();
}

// ----- columnas disponibles en points
$HAS_DIV_ID = col_exists('points','division_id');
$HAS_SEQ    = col_exists('points','seq');
$HAS_CODE   = col_exists('points','code') || col_exists('points','point_code') || col_exists('points','ref');
$CODE_COL   = col_exists('points','code') ? 'code' : (col_exists('points','point_code') ? 'point_code' : (col_exists('points','ref')?'ref':null));
$HAS_NAME   = col_exists('points','name');
$HAS_NOTES  = col_exists('points','notes');
$HAS_LOC    = col_exists('points','location');

$HAS_BRAND  = col_exists('points','brand_id');
$HAS_SERIES = col_exists('points','series_id');

$HAS_SUPPORT = col_exists('points','support_article_id');
$HAS_PLATE   = col_exists('points','plate_article_id');

$brand_id = (int)($point['brand_id'] ?? 0);
$series_id = (int)($point['series_id'] ?? 0);
$division_id = (int)($point['division_id'] ?? (int)get_param('division_id',0));

if (!$point && $division_id<=0 && $divisions) {
  // sugerir la primera división asociada al proyecto
  $division_id = (int)($divisions[0]['id'] ?? 0);
}

$prefix = $division_id>0 ? division_prefix($division_id) : '';
$rules = rules_for_point_prefix($prefix);

$errors = [];
$warnings = [];

if (is_post() && !$closed) {
  $action = post_param('action','save');

  if ($action==='save') {
    $division_id = (int)post_param('division_id',0);
    if ($HAS_DIV_ID && $division_id<=0) $errors[] = 'Selecciona una división.';
    $prefix = $division_id>0 ? division_prefix($division_id) : '';
    $rules = rules_for_point_prefix($prefix);

    $name = trim(post_param('name',''));
    $location = trim(post_param('location',''));
    $notes = trim(post_param('notes',''));

    $brand_id = $HAS_BRAND ? (int)post_param('brand_id',0) : 0;
    $series_id = $HAS_SERIES ? (int)post_param('series_id',0) : 0;

    $support_id = $HAS_SUPPORT ? (int)post_param('support_article_id',0) : 0;
    $plate_id   = $HAS_PLATE   ? (int)post_param('plate_article_id',0) : 0;

    // Validaciones por reglas
    if (!empty($rules['requires_support']) && $HAS_SUPPORT && $support_id<=0) $errors[]='Este punto requiere soporte.';
    if (!empty($rules['requires_plate']) && $HAS_PLATE && $plate_id<=0) $errors[]='Este punto requiere placa.';
    // fruits vienen por point_components (procesaremos luego)

    // Insert / Update point
    $isNew = ($point_id<=0);

    if ($isNew) {
      $cols=[]; $vals=[];

      if (col_exists('points','project_id')) { $cols[]='project_id'; $vals[]=$project_id; }
      if (col_exists('points','area_id'))    { $cols[]='area_id'; $vals[]=$area_id; }
      if ($HAS_DIV_ID) { $cols[]='division_id'; $vals[]=$division_id; }

      if ($HAS_BRAND)  { $cols[]='brand_id'; $vals[]=$brand_id ?: null; }
      if ($HAS_SERIES) { $cols[]='series_id'; $vals[]=$series_id ?: null; }

      if ($HAS_NAME)  { $cols[]='name'; $vals[]=$name; }
      if ($HAS_LOC)   { $cols[]='location'; $vals[]=$location; }
      if ($HAS_NOTES) { $cols[]='notes'; $vals[]=$notes; }

      if ($HAS_SUPPORT) { $cols[]='support_article_id'; $vals[]=$support_id ?: null; }
      if ($HAS_PLATE)   { $cols[]='plate_article_id'; $vals[]=$plate_id ?: null; }

      // created_by/created_at si existen
      if (col_exists('points','created_by') && function_exists('current_user_id')) { $cols[]='created_by'; $vals[]=current_user_id(); }
      if (col_exists('points','created_at')) { $cols[]='created_at'; $vals[]=date('Y-m-d H:i:s'); }

      if ($HAS_SEQ || $CODE_COL) {
        // autonumeración por area+division: seq = max(seq)+1
        $seq = 1;
        if ($HAS_SEQ && $division_id>0) {
          $row = db_query("SELECT COALESCE(MAX(seq),0) mx FROM points WHERE area_id=? AND division_id=?", [$area_id,$division_id])->fetch();
          $seq = ((int)($row['mx'] ?? 0)) + 1;
          $cols[]='seq'; $vals[]=$seq;
        }
        if ($CODE_COL && $prefix!=='') {
          // code = PREFIX + 2 dígitos (si quieres 3 dígitos luego lo cambiamos)
          $code = $prefix . str_pad((string)$seq, 2, '0', STR_PAD_LEFT);
          $cols[]=$CODE_COL; $vals[]=$code;
        }
      }

      if (!$errors) {
        $q = "INSERT INTO points(".implode(',',$cols).") VALUES (".implode(',',array_fill(0,count($cols),'?')).")";
        db_query($q, $vals);
        $point_id = (int)db_connect()->lastInsertId();
        $point = db_query("SELECT * FROM points WHERE id=?", [$point_id])->fetch();
      }
    } else {
      $cols=[]; $vals=[];
      if ($HAS_DIV_ID) { $cols[]='division_id=?'; $vals[]=$division_id; }
      if ($HAS_BRAND)  { $cols[]='brand_id=?'; $vals[]=$brand_id ?: null; }
      if ($HAS_SERIES) { $cols[]='series_id=?'; $vals[]=$series_id ?: null; }

      if ($HAS_NAME)  { $cols[]='name=?'; $vals[]=$name; }
      if ($HAS_LOC)   { $cols[]='location=?'; $vals[]=$location; }
      if ($HAS_NOTES) { $cols[]='notes=?'; $vals[]=$notes; }

      if ($HAS_SUPPORT) { $cols[]='support_article_id=?'; $vals[]=$support_id ?: null; }
      if ($HAS_PLATE)   { $cols[]='plate_article_id=?'; $vals[]=$plate_id ?: null; }

      if (col_exists('points','updated_at')) { $cols[]='updated_at=?'; $vals[]=date('Y-m-d H:i:s'); }

      $vals[]=$point_id;

      if (!$errors && $cols) {
        db_query("UPDATE points SET ".implode(',',$cols)." WHERE id=?", $vals);
        $point = db_query("SELECT * FROM points WHERE id=?", [$point_id])->fetch();
      }
    }

    // ---- procesar frutos (point_components)
    if (!$errors && table_exists('point_components')) {
      $fruit_ids = $_POST['fruit_article_id'] ?? [];
      $fruit_qty = $_POST['fruit_qty'] ?? [];

      $items = [];
      if (is_array($fruit_ids)) {
        foreach ($fruit_ids as $i=>$fid) {
          $fid=(int)$fid;
          if ($fid<=0) continue;
          $q = 1;
          if (is_array($fruit_qty) && isset($fruit_qty[$i])) $q = max(1,(int)$fruit_qty[$i]);
          $items[]=['id'=>$fid,'qty'=>$q];
        }
      }

      // limpiar y reinsertar (simple por ahora)
      $roleWhere = col_exists('point_components','role') ? " AND role='fruit' " : "";
      db_query("DELETE FROM point_components WHERE point_id=? $roleWhere", [$point_id]);

      $qtyCol = col_exists('point_components','qty') ? 'qty' : (col_exists('point_components','quantity') ? 'quantity' : 'qty');
      $hasRole = col_exists('point_components','role');

      foreach($items as $it){
        if ($hasRole) db_query("INSERT INTO point_components(point_id,article_id,$qtyCol,role) VALUES (?,?,?,'fruit')", [$point_id,$it['id'],$it['qty']]);
        else db_query("INSERT INTO point_components(point_id,article_id,$qtyCol) VALUES (?,?,?)", [$point_id,$it['id'],$it['qty']]);
      }

      // Validación de módulos vs soporte
      if (!empty($rules['requires_support']) && $HAS_SUPPORT) {
        $supportM = support_modules_from_point($point);
        $fruitM = sum_fruit_modules($point_id);

        if ($supportM>0) {
          if ($fruitM > $supportM) $errors[] = "Los frutos ocupan $fruitM módulos, pero el soporte es de $supportM.";
          if ($fruitM < $supportM) {
            $missing = $supportM - $fruitM;
            if (!empty($rules['auto_fill_blanks']) && (!empty($rules['ask_fill_blanks']) ? (bool)post_param('fill_blanks',0) : true)) {
              ensure_blank_fill($point_id, $missing, $brand_id?:null, $series_id?:null);
              $warnings[] = "Se rellenaron $missing módulo(s) con ciegos (si existían en catálogo).";
            } elseif (!empty($rules['ask_fill_blanks'])) {
              $warnings[] = "Quedan $missing módulo(s) libres. Marca “Rellenar con ciegos” y guarda otra vez si quieres completarlo.";
            }
          }
        }
      }

      if (!empty($rules['requires_fruits']) && empty($items)) {
        $errors[] = 'Este punto requiere al menos un fruto/mecanismo.';
      }
    }

    if ($errors) {
      foreach($errors as $e) add_flash('error',$e);
      foreach($warnings as $w) add_flash('warning',$w);
      redirect('index.php?page=point_detail&id='.$point_id.'&area_id='.$area_id);
    }

    foreach($warnings as $w) add_flash('warning',$w);
    add_flash('success', $isNew ? 'Punto creado.' : 'Punto actualizado.');
    redirect('index.php?page=area_detail&id='.$area_id);
  }
}

// ---------- datos para UI
$brands = table_exists('brands') ? db_query("SELECT id,name FROM brands ORDER BY name")->fetchAll() : [];
$series = [];
if (table_exists('series') && $brand_id>0) {
  $series = db_query("SELECT id,name FROM series WHERE brand_id=? ORDER BY name", [$brand_id])->fetchAll();
}

// Tipos de artículo (para soporte/fruto/placa) desde article_types
$HAS_ATYPES = table_exists('article_types');
$HAS_ATYPE_SLUG = $HAS_ATYPES ? col_exists('article_types','slug') : false;
$articleTypes = $HAS_ATYPES ? db_query("SELECT id,name".($HAS_ATYPE_SLUG?",slug":"")." FROM article_types ORDER BY name")->fetchAll() : [];
$bySlug = [];
foreach($articleTypes as $t){
  $key = $HAS_ATYPE_SLUG ? ($t['slug'] ?? '') : ($t['name'] ?? '');
  $slug = strtolower(trim((string)$key));
  if($slug==='') continue;
  $bySlug[$slug]=(int)$t['id'];
}
$supportTypeId = $bySlug['soporte'] ?? 0;
$plateTypeId   = $bySlug['placa'] ?? 0;
$coverTypeId   = $bySlug['cubretecla'] ?? 0;
// frutos/mecanismos (según tu catálogo)
$fruitTypeId = $bySlug['fruto'] ?? ($bySlug['mecanismo'] ?? 0);
$mechTypeId  = $bySlug['mecanismo'] ?? 0;

// opciones artículos filtradas por división + marca + serie + tipo
$supportOptions = ($division_id>0 ? list_articles_for_select($division_id, $brand_id?:null, $series_id?:null, $supportTypeId ?: null) : []);
$plateOptions   = ($division_id>0 ? list_articles_for_select($division_id, $brand_id?:null, $series_id?:null, $plateTypeId ?: null) : []);
$fruitOptions   = ($division_id>0 ? list_articles_for_select($division_id, $brand_id?:null, $series_id?:null, null) : []); // filtramos por división; luego JS filtra por "tipo"

$existingFruits = [];
if ($point_id>0 && table_exists('point_components')) {
  $qtyCol = col_exists('point_components','qty') ? 'qty' : (col_exists('point_components','quantity') ? 'quantity' : 'qty');
  $roleWhere = col_exists('point_components','role') ? " AND pc.role='fruit' " : "";
  $existingFruits = db_query("
    SELECT pc.article_id, pc.$qtyCol AS qty, a.code, a.name
    FROM point_components pc
    JOIN articles a ON a.id=pc.article_id
    WHERE pc.point_id=? $roleWhere
    ORDER BY pc.id
  ", [$point_id])->fetchAll();
}

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('Punto', `
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=area_detail&id=<?=h($area_id)?>"><i class="bi bi-arrow-left"></i> Volver al área</a>
`);
</script>

<?php if($closed): ?>
  <div class="alert alert-warning"><i class="bi bi-lock"></i> Proyecto cerrado: no se permiten cambios.</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <div>
    <div class="text-muted small">Proyecto: <a href="index.php?page=project_detail&id=<?=h($project_id)?>"><?=h($project['name'] ?? '')?></a> · Área: <a href="index.php?page=area_detail&id=<?=h($area_id)?>"><?=h($area['name'] ?? '')?></a></div>
    <h1 class="h4 mb-0"><?= $point_id>0 ? ('Editar punto #'.h($point_id)) : 'Nuevo punto' ?></h1>
  </div>
  <?php if($CODE_COL && $point && !empty($point[$CODE_COL])): ?>
    <span class="badge text-bg-primary fs-6"><?=h($point[$CODE_COL])?></span>
  <?php endif; ?>
</div>

<form method="post" class="card">
  <div class="card-body">
    <input type="hidden" name="action" value="save">

    <!-- 1) Datos base -->
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">División</label>
        <select class="form-select" name="division_id" id="division_id" <?= $closed?'disabled':'' ?>>
          <option value="">(selecciona)</option>
          <?php foreach($divisions as $d): ?>
            <option value="<?=h($d['id'])?>" <?= $division_id==(int)$d['id']?'selected':'' ?>>
              <?=h($d['name'])?><?= $HAS_DIV_PREFIX && !empty($d['prefix']) ? ' — '.$d['prefix'] : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Las reglas se aplican por <b>prefix</b> (ej: PE, CCTV, DATA).</div>
      </div>

      <?php if($HAS_NAME): ?>
      <div class="col-md-5">
        <label class="form-label">Nombre / Descripción</label>
        <input class="form-control" name="name" value="<?=h($point['name'] ?? '')?>" <?= $closed?'disabled':'' ?> placeholder="Ej: Interruptor entrada">
      </div>
      <?php endif; ?>

      <?php if($HAS_LOC && !empty($rules['ui']['show_location'])): ?>
      <div class="col-md-4">
        <label class="form-label">Ubicación (dentro del área)</label>
        <input class="form-control" name="location" value="<?=h($point['location'] ?? '')?>" <?= $closed?'disabled':'' ?> placeholder="Ej: Entrada, lado cama, isla...">
      </div>
      <?php endif; ?>

      <?php if($HAS_NOTES): ?>
      <div class="col-12">
        <label class="form-label">Notas</label>
        <textarea class="form-control" name="notes" rows="2" <?= $closed?'disabled':'' ?>><?=h($point['notes'] ?? '')?></textarea>
      </div>
      <?php endif; ?>
    </div>

    <hr>

    <!-- 2) Para divisiones tipo PE: marca/serie + armado (soporte/frutos/placa) -->
    <div id="electricBlock" style="<?= (!empty($rules['requires_support']) || !empty($rules['requires_fruits']) || !empty($rules['requires_plate'])) ? '' : 'display:none;' ?>">

      <div class="row g-2">
        <?php if($HAS_BRAND): ?>
        <div class="col-md-4">
          <label class="form-label">Marca</label>
          <select class="form-select" name="brand_id" id="brand_id" <?= $closed?'disabled':'' ?>>
            <option value="0">(sin marca)</option>
            <?php foreach($brands as $b): ?>
              <option value="<?=h($b['id'])?>" <?= $brand_id==(int)$b['id']?'selected':'' ?>><?=h($b['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <?php if($HAS_SERIES): ?>
        <div class="col-md-4">
          <label class="form-label">Serie</label>
          <select class="form-select" name="series_id" id="series_id" <?= $closed?'disabled':'' ?>>
            <option value="0">(sin serie)</option>
            <?php foreach($series as $s): ?>
              <option value="<?=h($s['id'])?>" <?= $series_id==(int)$s['id']?'selected':'' ?>><?=h($s['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <?php if(!empty($rules['ask_fill_blanks'])): ?>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="fill_blanks" id="fill_blanks" value="1">
            <label class="form-check-label" for="fill_blanks">Rellenar módulos libres con ciegos al guardar</label>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="row g-2 mt-2">
        <?php if($HAS_SUPPORT): ?>
        <div class="col-md-6">
          <label class="form-label">Soporte <?= !empty($rules['requires_support']) ? '<span class="text-danger">*</span>' : '' ?></label>
          <select class="form-select" name="support_article_id" <?= $closed?'disabled':'' ?>>
            <option value="0">(selecciona soporte)</option>
            <?php foreach($supportOptions as $a): ?>
              <option value="<?=h($a['id'])?>" <?= (int)($point['support_article_id'] ?? 0)==(int)$a['id']?'selected':'' ?>>
                <?=h($a['code'])?> — <?=h($a['name'])?><?= isset($a['modules']) ? ' ('.$a['modules'].'M)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <?php if($HAS_PLATE): ?>
        <div class="col-md-6">
          <label class="form-label">Placa <?= !empty($rules['requires_plate']) ? '<span class="text-danger">*</span>' : '<span class="text-muted">(opcional)</span>' ?></label>
          <select class="form-select" name="plate_article_id" <?= $closed?'disabled':'' ?>>
            <option value="0">(sin placa)</option>
            <?php foreach($plateOptions as $a): ?>
              <option value="<?=h($a['id'])?>" <?= (int)($point['plate_article_id'] ?? 0)==(int)$a['id']?'selected':'' ?>>
                <?=h($a['code'])?> — <?=h($a['name'])?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
      </div>

      <div class="mt-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-semibold">Frutos / Mecanismos <?= !empty($rules['requires_fruits']) ? '<span class="text-danger">*</span>' : '' ?></div>
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="addFruitRow()" <?= $closed?'disabled':'' ?>><i class="bi bi-plus-circle"></i> Agregar</button>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle" id="fruitsTable">
            <thead><tr><th>Artículo</th><th style="width:110px" class="text-end">Cant.</th><th style="width:60px"></th></tr></thead>
            <tbody>
              <?php if($existingFruits): foreach($existingFruits as $it): ?>
                <tr>
                  <td>
                    <select class="form-select form-select-sm" name="fruit_article_id[]">
                      <option value="0">(selecciona)</option>
                      <?php foreach($fruitOptions as $a): ?>
                        <option value="<?=h($a['id'])?>" <?= (int)$it['article_id']==(int)$a['id']?'selected':'' ?>>
                          <?=h($a['code'])?> — <?=h($a['name'])?><?= isset($a['modules']) && $a['modules']!==null ? ' ('.$a['modules'].'M)' : '' ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input class="form-control form-control-sm text-end" type="number" min="1" name="fruit_qty[]" value="<?=h($it['qty'])?>"></td>
                  <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
                </tr>
              <?php endforeach; else: ?>
                <tr class="text-muted"><td colspan="3">Sin frutos (aún).</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="form-text">
          Se filtran por <b>división</b> (tabla <code>article_divisions</code>). Si un artículo aplica a varias divisiones, simplemente asígnalo a varias en esa tabla.
        </div>
      </div>
    </div>

  </div>

  <div class="card-footer d-flex justify-content-end gap-2">
    <a class="btn btn-outline-secondary" href="index.php?page=area_detail&id=<?=h($area_id)?>">Cancelar</a>
    <button class="btn btn-primary" <?= $closed?'disabled':'' ?>><i class="bi bi-save"></i> Guardar</button>
  </div>
</form>

<script>
const FRUIT_TBODY = document.querySelector('#fruitsTable tbody');

function addFruitRow(){
  // elimina placeholder "Sin frutos"
  if (FRUIT_TBODY.querySelector('tr.text-muted')) FRUIT_TBODY.querySelector('tr.text-muted').remove();

  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select class="form-select form-select-sm" name="fruit_article_id[]">
        <option value="0">(selecciona)</option>
        <?php foreach($fruitOptions as $a): ?>
          <option value="<?=h($a['id'])?>"><?=h($a['code'])?> — <?=h($a['name'])?><?= isset($a['modules']) && $a['modules']!==null ? ' ('.$a['modules'].'M)' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input class="form-control form-control-sm text-end" type="number" min="1" name="fruit_qty[]" value="1"></td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
  `;
  FRUIT_TBODY.appendChild(tr);
}

// Si cambian división/marca/serie -> refrescar página para re-filtrar combos (simple y robusto)
document.getElementById('division_id')?.addEventListener('change', (e)=>{
  const did = e.target.value || '';
  const url = new URL(window.location.href);
  url.searchParams.set('division_id', did);
  // conservar area_id
  window.location.href = url.toString();
});

document.getElementById('brand_id')?.addEventListener('change', ()=>{
  const url = new URL(window.location.href);
  url.searchParams.set('brand_id', document.getElementById('brand_id').value || '0');
  window.location.href = url.toString();
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
