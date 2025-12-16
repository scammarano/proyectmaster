<?php
/**
 * views/points/detail.php  (FORM "MAESTRO" sin reglas todavía)
 *
 * Reglas aplicadas aquí (solo filtrado/cascada + autonumeración):
 * - División: SOLO divisiones del proyecto (project_divisions)
 * - Marca: SOLO marcas que tengan artículos en esa división (article_divisions -> articles.brand_id)
 * - Serie: SOLO series de la marca seleccionada (series.brand_id)
 * - Componentes: artículos SOLO de la serie (articles.series_id) (opcional: también por división si existe article_divisions)
 * - Code/seq: code = prefix + LPAD(seq,2,'0'), seq autoincrementa por (project_id + division_id)
 * - Incluye orientación del punto (orientation)
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attachments.php';
require_login();

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
    try { $st=db_query("SHOW TABLES LIKE ?", [$table]); return (bool)$st->fetch(); }
    catch (Throwable $e) { return false; }
  }
}

$point_id = (int)get_param('id', 0);
$area_id  = (int)get_param('area_id', 0);

$area = null;
$project = null;
$project_id = 0;

if ($area_id > 0) {
  $area = db_query("SELECT * FROM areas WHERE id=?", [$area_id])->fetch();
  if (!$area) die('Área no encontrada');
  $project_id = (int)($area['project_id'] ?? 0);
  $project = db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch();
  if (!$project) die('Proyecto no encontrado');
} else {
  // Si se edita un punto existente, inferimos area/proyecto desde el punto
  if ($point_id > 0) {
    $tmp = db_query("SELECT * FROM points WHERE id=?", [$point_id])->fetch();
    if (!$tmp) die('Punto no encontrado');
    $area_id = (int)($tmp['area_id'] ?? 0);
    $project_id = (int)($tmp['project_id'] ?? 0);
    if ($area_id>0) $area = db_query("SELECT * FROM areas WHERE id=?", [$area_id])->fetch();
    if ($project_id>0) $project = db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch();
  }
}

if ($project_id<=0) die('Falta project_id (pasa area_id o edita un punto existente)');

$HAS_CLOSED = col_exists('projects','is_closed');
$closed = false;
if ($HAS_CLOSED && $project) $closed = ((int)($project['is_closed'] ?? 0)===1);

$HAS_ORIENTATION = col_exists('points','orientation');
$HAS_LOCATION    = col_exists('points','location');
$HAS_NOTES       = col_exists('points','notes');
$HAS_NAME        = col_exists('points','name');
$HAS_CODE        = col_exists('points','code');
$HAS_SEQ         = col_exists('points','seq');
$HAS_DIVISION_ID = col_exists('points','division_id');

$HAS_DIVS = table_exists('divisions');
$HAS_PDIV = table_exists('project_divisions');
$HAS_DIV_PREFIX = $HAS_DIVS ? col_exists('divisions','prefix') : false;

if (!$HAS_DIVS || !$HAS_PDIV) {
  die('Faltan tablas divisions/project_divisions para crear puntos con cascada.');
}

$HAS_SERIES = table_exists('series') && col_exists('series','brand_id');
$HAS_ARTICLES = table_exists('articles') && col_exists('articles','brand_id') && col_exists('articles','series_id');
$HAS_ARTDIV = table_exists('article_divisions') && col_exists('article_divisions','article_id') && col_exists('article_divisions','division_id');

$HAS_POINT_COMPONENTS = table_exists('point_components') && col_exists('point_components','point_id') && col_exists('point_components','article_id');

if (!$HAS_SERIES || !$HAS_ARTICLES || !$HAS_POINT_COMPONENTS) {
  die('Faltan tablas/columnas requeridas: series(brand_id), articles(brand_id,series_id) y/o point_components(point_id,article_id).');
}

function next_seq_for_project_division(int $project_id, int $division_id): int {
  if (!col_exists('points','seq') || !col_exists('points','division_id')) return 1;
  $r = db_query("SELECT COALESCE(MAX(seq),0) AS m FROM points WHERE project_id=? AND division_id=?", [$project_id,$division_id])->fetch();
  $m = (int)($r['m'] ?? 0);
  return $m + 1;
}
function build_code(string $prefix, int $seq): string {
  $seqStr = str_pad((string)$seq, 2, '0', STR_PAD_LEFT);
  return $prefix . $seqStr;
}

// ===================
// Cargar punto (si edita)
// ===================
$point = null;
if ($point_id>0) {
  $point = db_query("SELECT * FROM points WHERE id=?", [$point_id])->fetch();
  if (!$point) die('Punto no encontrado');
  if ($area_id<=0) $area_id = (int)($point['area_id'] ?? 0);
}

$selected_division_id = (int)($_REQUEST['division_id'] ?? ($point['division_id'] ?? 0));
$selected_brand_id    = (int)($_REQUEST['brand_id'] ?? 0);
$selected_series_id   = (int)($_REQUEST['series_id'] ?? 0);

if ($point && $selected_division_id<=0 && $HAS_DIVISION_ID) $selected_division_id = (int)($point['division_id'] ?? 0);

// Inferir marca/serie desde el primer componente
if ($point && $selected_series_id<=0) {
  $first = db_query("SELECT a.brand_id,a.series_id FROM point_components pc JOIN articles a ON a.id=pc.article_id WHERE pc.point_id=? LIMIT 1", [$point_id])->fetch();
  if ($first) {
    $selected_brand_id = (int)($first['brand_id'] ?? 0);
    $selected_series_id = (int)($first['series_id'] ?? 0);
  }
}

// ===================
// Data para dropdowns (cascada)
// ===================

// Divisiones del proyecto
$divisions = db_query("
  SELECT d.id, d.name, ".($HAS_DIV_PREFIX ? "d.prefix" : "'' AS prefix")."
  FROM project_divisions pd
  JOIN divisions d ON d.id = pd.division_id
  WHERE pd.project_id=?
  ORDER BY d.name
", [$project_id])->fetchAll();

// Marcas (filtradas por división)
$brands = [];
if ($selected_division_id>0) {
  if ($HAS_ARTDIV) {
    $brands = db_query("
      SELECT DISTINCT b.id, b.name
      FROM articles a
      JOIN article_divisions ad ON ad.article_id=a.id
      JOIN brands b ON b.id=a.brand_id
      WHERE ad.division_id=?
      ORDER BY b.name
    ", [$selected_division_id])->fetchAll();
  } else {
    $brands = db_query("SELECT id,name FROM brands ORDER BY name")->fetchAll();
  }
}

// Series por marca
$series = [];
if ($selected_brand_id>0) {
  $series = db_query("SELECT s.id, s.name FROM series s WHERE s.brand_id=? ORDER BY s.name", [$selected_brand_id])->fetchAll();
}

// Artículos por serie (y opcional división)
$articles = [];
if ($selected_series_id>0) {
  if ($HAS_ARTDIV && $selected_division_id>0) {
    $articles = db_query("
      SELECT a.*
      FROM articles a
      JOIN article_divisions ad ON ad.article_id=a.id
      WHERE a.series_id=? AND ad.division_id=?
      ORDER BY a.name
    ", [$selected_series_id, $selected_division_id])->fetchAll();
  } else {
    $articles = db_query("SELECT * FROM articles WHERE series_id=? ORDER BY name", [$selected_series_id])->fetchAll();
  }
}

// Componentes existentes
$components = [];
if ($point_id>0) {
  $components = db_query("
    SELECT pc.*, a.name AS article_name, a.modules AS article_modules
    FROM point_components pc
    JOIN articles a ON a.id=pc.article_id
    WHERE pc.point_id=?
    ORDER BY pc.id
  ", [$point_id])->fetchAll();
}

// Columnas opcionales de point_components
$PC_HAS_QTY   = col_exists('point_components','qty') || col_exists('point_components','quantity');
$PC_QTY_COL   = col_exists('point_components','qty') ? 'qty' : (col_exists('point_components','quantity') ? 'quantity' : null);
$PC_HAS_NOTE  = col_exists('point_components','notes') || col_exists('point_components','note') || col_exists('point_components','description');
$PC_NOTE_COL  = col_exists('point_components','notes') ? 'notes' : (col_exists('point_components','note') ? 'note' : (col_exists('point_components','description') ? 'description' : null));

// ===================
// Guardar
// ===================
if (is_post()) {
  if ($closed) { add_flash('error','Proyecto cerrado: no se permiten cambios.'); redirect('index.php?page=project_detail&id='.$project_id); }

  $action = post_param('action','');

  if ($action==='save_point') {
    $division_id = (int)post_param('division_id',0);
    $brand_id    = (int)post_param('brand_id',0);
    $series_id   = (int)post_param('series_id',0);

    $name        = trim((string)post_param('name',''));
    $notes       = trim((string)post_param('notes',''));
    $location    = trim((string)post_param('location',''));
    $orientation = trim((string)post_param('orientation',''));

    if ($division_id<=0) { add_flash('error','Selecciona una división.'); redirect('index.php?page=point_detail&area_id='.$area_id); }
    if ($brand_id<=0)    { add_flash('error','Selecciona una marca.'); redirect('index.php?page=point_detail&area_id='.$area_id.'&division_id='.$division_id); }
    if ($series_id<=0)   { add_flash('error','Selecciona una serie.'); redirect('index.php?page=point_detail&area_id='.$area_id.'&division_id='.$division_id.'&brand_id='.$brand_id); }

    $d = db_query("SELECT ".($HAS_DIV_PREFIX?'prefix':'"" AS prefix')." FROM divisions WHERE id=?", [$division_id])->fetch();
    $prefix = trim((string)($d['prefix'] ?? ''));
    if ($prefix==='') { add_flash('error','La división no tiene PREFIX (divisions.prefix).'); redirect('index.php?page=point_detail&area_id='.$area_id.'&division_id='.$division_id.'&brand_id='.$brand_id.'&series_id='.$series_id); }

    $comp_article_ids = $_POST['comp_article_id'] ?? [];
    $comp_qtys        = $_POST['comp_qty'] ?? [];
    $comp_notes       = $_POST['comp_note'] ?? [];

    $rows = [];
    if (is_array($comp_article_ids)) {
      for ($i=0; $i<count($comp_article_ids); $i++) {
        $aid = (int)$comp_article_ids[$i];
        if ($aid<=0) continue;
        $qty = 1;
        if (is_array($comp_qtys) && isset($comp_qtys[$i])) {
          $q = (int)$comp_qtys[$i];
          $qty = $q>0 ? $q : 1;
        }
        $nt = '';
        if (is_array($comp_notes) && isset($comp_notes[$i])) $nt = trim((string)$comp_notes[$i]);
        $rows[] = ['article_id'=>$aid,'qty'=>$qty,'note'=>$nt];
      }
    }
    if (!$rows) {
      add_flash('error','Agrega al menos 1 componente (artículo).');
      redirect('index.php?page=point_detail&area_id='.$area_id.'&division_id='.$division_id.'&brand_id='.$brand_id.'&series_id='.$series_id);
    }

    if ($point_id<=0) {
      $seq = next_seq_for_project_division($project_id,$division_id);
      $code = build_code($prefix,$seq);

      $fields = [];
      $vals = [];

      $fields[]='project_id'; $vals[]=$project_id;
      $fields[]='area_id';    $vals[]=$area_id;
      if ($HAS_DIVISION_ID) { $fields[]='division_id'; $vals[]=$division_id; }
      if ($HAS_SEQ) { $fields[]='seq'; $vals[]=$seq; }
      if ($HAS_CODE) { $fields[]='code'; $vals[]=$code; }

      if ($HAS_NAME) { $fields[]='name'; $vals[]=$name; }
      if ($HAS_NOTES) { $fields[]='notes'; $vals[]=$notes; }
      if ($HAS_LOCATION) { $fields[]='location'; $vals[]=$location; }
      if ($HAS_ORIENTATION) { $fields[]='orientation'; $vals[]=$orientation; }

      $sql = "INSERT INTO points(".implode(',',$fields).") VALUES (".implode(',',array_fill(0,count($fields),'?')).")";
      db_query($sql, $vals);
      $point_id = (int)db_connect()->lastInsertId();
      add_flash('success', 'Punto creado: '.$code);
    } else {
      $sets=[]; $vals=[];
      if ($HAS_DIVISION_ID) { $sets[]="division_id=?"; $vals[]=$division_id; }
      if ($HAS_NAME) { $sets[]="name=?"; $vals[]=$name; }
      if ($HAS_NOTES) { $sets[]="notes=?"; $vals[]=$notes; }
      if ($HAS_LOCATION) { $sets[]="location=?"; $vals[]=$location; }
      if ($HAS_ORIENTATION) { $sets[]="orientation=?"; $vals[]=$orientation; }
      if ($sets) {
        $vals[]=$point_id;
        db_query("UPDATE points SET ".implode(',',$sets)." WHERE id=?", $vals);
      }
      add_flash('success', 'Punto actualizado.');
    }

    db_query("DELETE FROM point_components WHERE point_id=?", [$point_id]);

    foreach ($rows as $r) {
      $cols=['point_id','article_id'];
      $vals=[$point_id, (int)$r['article_id']];

      if ($PC_HAS_QTY && $PC_QTY_COL) { $cols[]=$PC_QTY_COL; $vals[]=(int)$r['qty']; }
      if ($PC_HAS_NOTE && $PC_NOTE_COL) { $cols[]=$PC_NOTE_COL; $vals[]=(string)$r['note']; }

      $sql="INSERT INTO point_components(".implode(',',$cols).") VALUES (".implode(',',array_fill(0,count($cols),'?')).")";
      db_query($sql,$vals);
    }

    redirect('index.php?page=area_detail&id='.$area_id);
  }
}

// ===================
// UI
// ===================
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

<h1 class="h4 mb-3">
  <i class="bi bi-bounding-box"></i>
  <?= $point_id>0 ? 'Editar punto' : 'Nuevo punto' ?>
  <span class="text-muted small">— Proyecto: <?=h($project['name'] ?? '')?> / Área: <?=h($area['name'] ?? '')?></span>
</h1>

<form method="post" class="card">
  <div class="card-body">
    <input type="hidden" name="action" value="save_point">
    <input type="hidden" name="area_id" value="<?=h($area_id)?>">
    <?php if($point_id>0): ?><input type="hidden" name="id" value="<?=h($point_id)?>"><?php endif; ?>

    <div class="row g-3">

      <div class="col-md-3">
        <label class="form-label">División (del proyecto)</label>
        <select class="form-select" name="division_id" id="division_id" <?= $closed?'disabled':'' ?>>
          <option value="">(selecciona)</option>
          <?php foreach($divisions as $d): ?>
            <option value="<?=h($d['id'])?>" <?= ((int)$d['id']===$selected_division_id)?'selected':'' ?>>
              <?=h($d['name'])?><?= $HAS_DIV_PREFIX && !empty($d['prefix']) ? ' — '.$d['prefix'] : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">No viene preseleccionada para evitar confusión.</div>
      </div>

      <div class="col-md-3">
        <label class="form-label">Marca (filtrada por división)</label>
        <select class="form-select" name="brand_id" id="brand_id" <?= ($closed || $selected_division_id<=0)?'disabled':'' ?>>
          <option value="">(selecciona)</option>
          <?php foreach($brands as $b): ?>
            <option value="<?=h($b['id'])?>" <?= ((int)$b['id']===$selected_brand_id)?'selected':'' ?>><?=h($b['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Serie (filtrada por marca)</label>
        <select class="form-select" name="series_id" id="series_id" <?= ($closed || $selected_brand_id<=0)?'disabled':'' ?>>
          <option value="">(selecciona)</option>
          <?php foreach($series as $s): ?>
            <option value="<?=h($s['id'])?>" <?= ((int)$s['id']===$selected_series_id)?'selected':'' ?>><?=h($s['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Orientación</label>
        <?php if($HAS_ORIENTATION): ?>
          <select class="form-select" name="orientation" <?= $closed?'disabled':'' ?>>
            <?php
              $ori = (string)($point['orientation'] ?? '');
              $opts = [''=>'(sin definir)','N'=>'N','S'=>'S','E'=>'E','W'=>'W','NE'=>'NE','NW'=>'NW','SE'=>'SE','SW'=>'SW','H'=>'Horizontal','V'=>'Vertical'];
              foreach($opts as $k=>$v){
                $sel = ($k!=='' && $k===$ori) || ($k==='' && $ori==='') ? 'selected' : '';
                echo '<option value="'.h($k).'" '.$sel.'>'.h($v).'</option>';
              }
            ?>
          </select>
        <?php else: ?>
          <input class="form-control" value="(points.orientation no existe en BD)" disabled>
          <div class="form-text">Agrega la columna <code>points.orientation</code> si la quieres guardar.</div>
        <?php endif; ?>
      </div>

      <div class="col-md-6">
        <label class="form-label">Nombre del punto</label>
        <?php if($HAS_NAME): ?>
          <input class="form-control" name="name" value="<?=h($point['name'] ?? '')?>" <?= $closed?'disabled':'' ?> placeholder="Ej: Toma doble mesón / Cámara entrada / Rack 1">
        <?php else: ?>
          <input class="form-control" value="(points.name no existe)" disabled>
        <?php endif; ?>
      </div>

      <div class="col-md-6">
        <label class="form-label">Ubicación</label>
        <?php if($HAS_LOCATION): ?>
          <input class="form-control" name="location" value="<?=h($point['location'] ?? '')?>" <?= $closed?'disabled':'' ?> placeholder="Ej: pared norte, sobre mesón, techo, etc">
        <?php else: ?>
          <input class="form-control" value="(points.location no existe)" disabled>
        <?php endif; ?>
      </div>

      <div class="col-12">
        <label class="form-label">Notas</label>
        <?php if($HAS_NOTES): ?>
          <textarea class="form-control" name="notes" rows="2" <?= $closed?'disabled':'' ?>><?=h($point['notes'] ?? '')?></textarea>
        <?php else: ?>
          <textarea class="form-control" rows="2" disabled>(points.notes no existe)</textarea>
        <?php endif; ?>
      </div>

      <div class="col-12"><hr class="my-2"></div>

      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
          <h2 class="h6 mb-0"><i class="bi bi-box-seam"></i> Componentes del punto</h2>
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()" <?= ($closed || $selected_series_id<=0)?'disabled':'' ?>>
            <i class="bi bi-plus-circle"></i> Agregar componente
          </button>
        </div>
        <div class="text-muted small mt-1">En este modo maestro, los componentes son artículos (filtrados por serie).</div>

        <div class="table-responsive mt-2">
          <table class="table table-sm align-middle" id="cmpTable">
            <thead>
              <tr>
                <th style="width:48%">Artículo (serie)</th>
                <th style="width:12%">Cant.</th>
                <th style="width:28%">Nota</th>
                <th class="text-end" style="width:12%">Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php if($components): ?>
                <?php foreach($components as $c): ?>
                  <tr>
                    <td>
                      <select class="form-select form-select-sm" name="comp_article_id[]" <?= ($closed || $selected_series_id<=0)?'disabled':'' ?>>
                        <option value="">(selecciona)</option>
                        <?php foreach($articles as $a): ?>
                          <option value="<?=h($a['id'])?>" <?= ((int)$a['id']===(int)$c['article_id'])?'selected':'' ?>>
                            <?=h($a['name'])?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td>
                      <input class="form-control form-control-sm" type="number" min="1" name="comp_qty[]" value="<?=h(($PC_QTY_COL && isset($c[$PC_QTY_COL])) ? $c[$PC_QTY_COL] : 1)?>" <?= $closed?'disabled':'' ?>>
                    </td>
                    <td>
                      <input class="form-control form-control-sm" name="comp_note[]" value="<?=h(($PC_NOTE_COL && isset($c[$PC_NOTE_COL])) ? $c[$PC_NOTE_COL] : '')?>" <?= $closed?'disabled':'' ?>>
                    </td>
                    <td class="text-end">
                      <button type="button" class="btn btn-sm btn-outline-danger" onclick="delRow(this)" <?= $closed?'disabled':'' ?>><i class="bi bi-trash"></i></button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr class="text-muted"><td colspan="4">No hay componentes aún.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if($selected_series_id<=0): ?>
          <div class="alert alert-info mt-2 mb-0">
            Selecciona <b>División → Marca → Serie</b> para poder agregar componentes.
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <div class="card-footer d-flex justify-content-between align-items-center">
    <div class="text-muted small">
      <?php if($point_id>0 && $HAS_CODE): ?>
        Código actual: <b><?=h($point['code'] ?? '')?></b>
      <?php else: ?>
        El código se genera al guardar (prefix + secuencia).
      <?php endif; ?>
    </div>
    <button class="btn btn-primary" <?= $closed?'disabled':'' ?> onclick="return confirm('¿Guardar punto?');">
      <i class="bi bi-save"></i> Guardar
    </button>
  </div>
</form>

<script>
function qs(obj){
  const p = new URLSearchParams(window.location.search);
  Object.keys(obj).forEach(k=>{
    if(obj[k]===null || obj[k]==='' || typeof obj[k]==='undefined') p.delete(k);
    else p.set(k,obj[k]);
  });
  return 'index.php?'+p.toString();
}

document.getElementById('division_id')?.addEventListener('change', (e)=>{
  window.location = qs({page:'point_detail', area_id:'<?=h($area_id)?>', division_id:e.target.value, brand_id:null, series_id:null, id:'<?=h($point_id)?>' || null});
});
document.getElementById('brand_id')?.addEventListener('change', (e)=>{
  window.location = qs({page:'point_detail', area_id:'<?=h($area_id)?>', division_id:'<?=h($selected_division_id)?>', brand_id:e.target.value, series_id:null, id:'<?=h($point_id)?>' || null});
});
document.getElementById('series_id')?.addEventListener('change', (e)=>{
  window.location = qs({page:'point_detail', area_id:'<?=h($area_id)?>', division_id:'<?=h($selected_division_id)?>', brand_id:'<?=h($selected_brand_id)?>', series_id:e.target.value, id:'<?=h($point_id)?>' || null});
});

function delRow(btn){
  const tr = btn.closest('tr');
  if(tr) tr.remove();
  const tbody = document.querySelector('#cmpTable tbody');
  if(tbody && tbody.children.length===0){
    const tr2=document.createElement('tr');
    tr2.className='text-muted';
    tr2.innerHTML='<td colspan="4">No hay componentes aún.</td>';
    tbody.appendChild(tr2);
  }
}
function addRow(){
  const tbody = document.querySelector('#cmpTable tbody');
  if(!tbody) return;

  if(tbody.children.length===1 && tbody.children[0].classList.contains('text-muted')){
    tbody.innerHTML='';
  }

  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select class="form-select form-select-sm" name="comp_article_id[]">
        <option value="">(selecciona)</option>
        <?php foreach($articles as $a): ?>
          <option value="<?=h($a['id'])?>"><?=h($a['name'])?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input class="form-control form-control-sm" type="number" min="1" name="comp_qty[]" value="1"></td>
    <td><input class="form-control form-control-sm" name="comp_note[]" value=""></td>
    <td class="text-end">
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="delRow(this)"><i class="bi bi-trash"></i></button>
    </td>
  `;
  tbody.appendChild(tr);
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
