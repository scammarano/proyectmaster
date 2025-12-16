<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rules.php';
require_once __DIR__ . '/../../includes/rules_writer.php';

require_login();
if (!is_admin()) die('Solo admin');

if (!function_exists('table_exists')) {
  function table_exists(string $table): bool {
    try { $stmt = db_query("SHOW TABLES LIKE ?", [$table]); return (bool)$stmt->fetch(); }
    catch (Throwable $e) { return false; }
  }
}
function col_exists(string $table, string $col): bool {
  try { return (bool)db_query("SHOW COLUMNS FROM `$table` LIKE ?", [$col])->fetch(); }
  catch (Throwable $e) { return false; }
}

$HAS_TYPES = table_exists('article_types');
$HAS_DIVS  = table_exists('divisions');

$types = $HAS_TYPES ? db_query("SELECT id,code,name FROM article_types ORDER BY name")->fetchAll() : [];

// divisions: en tu BD puede NO existir divisions.code (solo id,name,prefix,etc)
$DIV_HAS_CODE   = $HAS_DIVS ? col_exists('divisions','code') : false;
$DIV_HAS_PREFIX = $HAS_DIVS ? col_exists('divisions','prefix') : false;

if ($HAS_DIVS) {
  $cols = ["id","name"];
  if ($DIV_HAS_PREFIX) $cols[]="prefix";
  if ($DIV_HAS_CODE)   $cols[]="code";
  $divs = db_query("SELECT ".implode(',',$cols)." FROM divisions ORDER BY name")->fetchAll();
} else {
  $divs = [];
}

$rules = load_rules();
$article_rules = $rules['articles']['type_field_rules'] ?? [];
$articles_default = $rules['articles']['default'] ?? ['show'=>['modules'], 'hide'=>[]];
$points_rules = $rules['points'] ?? [];

// Campos que podemos controlar hoy (ampliamos luego)
$ARTICLE_FIELDS = [
  'modules' => 'Módulos',
  'requires_cover' => 'Requiere cubretecla',
];

$POINTS_KEYS = [
  'requires_support' => 'Soporte obligatorio',
  'requires_plate' => 'Placa obligatoria',
  'requires_fruits' => 'Frutos/mecanismos obligatorios',
  'auto_fill_blanks' => 'Auto completar módulos ciegos',
  'ask_fill_blanks' => 'Preguntar antes de rellenar ciegos',
];

function arr_has($arr,$k){ return is_array($arr) && array_key_exists($k,$arr); }
function to_bool($v){ return ($v==='1' || $v===1 || $v===true || $v==='on'); }

function division_key(array $d): string {
  // key estable para reglas: preferimos 'code' si existe, si no 'prefix', si no 'DIV{id}'
  if (isset($d['code']) && trim((string)$d['code'])!=='') return (string)$d['code'];
  if (isset($d['prefix']) && trim((string)$d['prefix'])!=='') return (string)$d['prefix'];
  return 'DIV'.(int)$d['id'];
}

if (is_post()) {
  $action = post_param('action','');

  if ($action==='save_articles') {
    $new = $rules;
    $new['articles'] = $new['articles'] ?? [];
    $new['articles']['type_field_rules'] = [];
    $new['articles']['default'] = $articles_default;

    foreach ($types as $t) {
      $code = (string)$t['code'];
      $show = $_POST['art_show'][$code] ?? [];
      $hide = $_POST['art_hide'][$code] ?? [];
      $show = array_values(array_unique(array_filter(array_map('strval', is_array($show)?$show:[]))));
      $hide = array_values(array_unique(array_filter(array_map('strval', is_array($hide)?$hide:[]))));
      $new['articles']['type_field_rules'][$code] = ['show'=>$show,'hide'=>$hide];
    }

    $dshow = $_POST['art_def_show'] ?? [];
    $dhide = $_POST['art_def_hide'] ?? [];
    $dshow = array_values(array_unique(array_filter(array_map('strval', is_array($dshow)?$dshow:[]))));
    $dhide = array_values(array_unique(array_filter(array_map('strval', is_array($dhide)?$dhide:[]))));
    $new['articles']['default'] = ['show'=>$dshow,'hide'=>$dhide];

    try{
      write_rules_file($new);
      set_flash('success','Reglas de Artículos guardadas en /config/rules.php');
    }catch(Exception $e){
      set_flash('error','No se pudo guardar: '.$e->getMessage().' (revisa permisos de /config)');
    }
    redirect('index.php?page=catalog_rules_wizard#tab-articles');
  }

  if ($action==='save_points') {
    $new = $rules;
    $new['points'] = $new['points'] ?? [];

    foreach ($divs as $d) {
      $key = division_key($d);
      $new['points'][$key] = $new['points'][$key] ?? [];

      foreach (array_keys($POINTS_KEYS) as $rk) {
        $new['points'][$key][$rk] = to_bool($_POST['pt'][$key][$rk] ?? '0');
      }

      $new['points'][$key]['ui'] = $new['points'][$key]['ui'] ?? [];
      $new['points'][$key]['ui']['show_location'] = to_bool($_POST['pt'][$key]['ui']['show_location'] ?? '1');
    }

    try{
      write_rules_file($new);
      set_flash('success','Reglas de Puntos guardadas en /config/rules.php');
    }catch(Exception $e){
      set_flash('error','No se pudo guardar: '.$e->getMessage().' (revisa permisos de /config)');
    }
    redirect('index.php?page=catalog_rules_wizard#tab-points');
  }
}

include __DIR__ . '/../layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-2">
  <h1 class="h4 mb-0"><i class="bi bi-magic"></i> Asistente de reglas</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" href="index.php?page=catalog_rules"><i class="bi bi-eye"></i> Ver reglas</a>
  </div>
</div>

<div class="alert alert-info">
  Este asistente edita <code>/config/rules.php</code> sin tocar la BD.
  Si al guardar te da error de permisos, en cPanel asigna permisos de escritura a la carpeta <code>/config</code>.
</div>

<ul class="nav nav-tabs">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-articles" id="tab-art-btn">Artículos</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-points" id="tab-pt-btn">Puntos</button></li>
</ul>

<div class="tab-content border border-top-0 p-3">
  <div class="tab-pane fade show active" id="tab-articles">
    <h2 class="h6 mb-3">Mostrar / ocultar campos por tipo de artículo (article_types.code)</h2>

    <?php if(!$HAS_TYPES): ?>
      <div class="text-muted">No existe la tabla <code>article_types</code>.</div>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="action" value="save_articles">

        <div class="card mb-3">
          <div class="card-header">Default (si el tipo no está definido)</div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-md-6">
                <div class="fw-semibold small mb-1">Mostrar</div>
                <?php foreach($ARTICLE_FIELDS as $k=>$label): ?>
                  <?php $ck = in_array($k, $articles_default['show'] ?? [], true); ?>
                  <label class="me-3"><input type="checkbox" name="art_def_show[]" value="<?=h($k)?>" <?= $ck?'checked':'' ?>> <?=h($label)?></label>
                <?php endforeach; ?>
              </div>
              <div class="col-md-6">
                <div class="fw-semibold small mb-1">Ocultar</div>
                <?php foreach($ARTICLE_FIELDS as $k=>$label): ?>
                  <?php $ck = in_array($k, $articles_default['hide'] ?? [], true); ?>
                  <label class="me-3"><input type="checkbox" name="art_def_hide[]" value="<?=h($k)?>" <?= $ck?'checked':'' ?>> <?=h($label)?></label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle">
            <thead>
              <tr>
                <th>Tipo</th>
                <th class="text-muted small">code</th>
                <th>Mostrar</th>
                <th>Ocultar</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($types as $t): $code=(string)$t['code']; $r=$article_rules[$code] ?? ['show'=>[],'hide'=>[]]; ?>
              <tr>
                <td><?=h($t['name'])?></td>
                <td class="text-muted small"><code><?=h($code)?></code></td>
                <td>
                  <?php foreach($ARTICLE_FIELDS as $k=>$label): ?>
                    <?php $ck = in_array($k, $r['show'] ?? [], true); ?>
                    <label class="me-3 small"><input type="checkbox" name="art_show[<?=h($code)?>][]" value="<?=h($k)?>" <?= $ck?'checked':'' ?>> <?=h($label)?></label>
                  <?php endforeach; ?>
                </td>
                <td>
                  <?php foreach($ARTICLE_FIELDS as $k=>$label): ?>
                    <?php $ck = in_array($k, $r['hide'] ?? [], true); ?>
                    <label class="me-3 small"><input type="checkbox" name="art_hide[<?=h($code)?>][]" value="<?=h($k)?>" <?= $ck?'checked':'' ?>> <?=h($label)?></label>
                  <?php endforeach; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar reglas de Artículos</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="tab-pane fade" id="tab-points">
    <h2 class="h6 mb-3">Reglas por División (usa key: code/prefix/id)</h2>

    <?php if(!$HAS_DIVS): ?>
      <div class="text-muted">No existe la tabla <code>divisions</code>.</div>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="action" value="save_points">

        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle">
            <thead>
              <tr>
                <th>División</th>
                <th class="text-muted small">key</th>
                <th class="text-muted small">prefix</th>
                <?php foreach($POINTS_KEYS as $k=>$label): ?><th class="text-center small"><?=h($label)?></th><?php endforeach; ?>
                <th class="text-center small">Ubicación</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($divs as $d):
                $key = division_key($d);
                $r=$points_rules[$key] ?? [];
              ?>
              <tr>
                <td><?=h($d['name'])?></td>
                <td class="text-muted small"><code><?=h($key)?></code></td>
                <td class="text-muted small"><?=h($d['prefix'] ?? '—')?></td>
                <?php foreach(array_keys($POINTS_KEYS) as $rk): $ck = !empty($r[$rk]); ?>
                  <td class="text-center"><input type="checkbox" name="pt[<?=h($key)?>][<?=h($rk)?>]" value="1" <?= $ck?'checked':'' ?>></td>
                <?php endforeach; ?>
                <?php $ckLoc = !arr_has($r,'ui') || !arr_has($r['ui'],'show_location') ? true : !empty($r['ui']['show_location']); ?>
                <td class="text-center"><input type="checkbox" name="pt[<?=h($key)?>][ui][show_location]" value="1" <?= $ckLoc?'checked':'' ?>></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar reglas de Puntos</button>
      </form>

      <div class="alert alert-secondary mt-3 mb-0 small">
        Nota: en la próxima iteración conectamos estas reglas con el formulario real de creación/edición de puntos.
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
if (location.hash==='#tab-points') { const btn=document.getElementById('tab-pt-btn'); if(btn) btn.click(); }
if (location.hash==='#tab-articles') { const btn=document.getElementById('tab-art-btn'); if(btn) btn.click(); }
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
