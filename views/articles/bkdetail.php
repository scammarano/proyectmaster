<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();
if (!is_admin()) die('Solo admin');

if (!function_exists('table_exists')) {
  function table_exists(string $table): bool {
    try { $stmt = db_query("SHOW TABLES LIKE ?", [$table]); return (bool)$stmt->fetch(); }
    catch (Throwable $e) { return false; }
  }
}

$HAS_BRANDS  = table_exists('brands');
$HAS_SERIES  = table_exists('series');
$HAS_TYPES   = table_exists('article_types');
$HAS_DIVS    = table_exists('divisions');
$HAS_MAP     = table_exists('article_divisions');

$HAS_MODULES = (bool)db_query("SHOW COLUMNS FROM articles LIKE 'modules'")->fetch();
$HAS_REQ     = (bool)db_query("SHOW COLUMNS FROM articles LIKE 'requires_cover'")->fetch();
$HAS_ATID    = (bool)db_query("SHOW COLUMNS FROM articles LIKE 'article_type_id'")->fetch();

$id = (int)get_param('id', 0);

$brands = $HAS_BRANDS ? db_query("SELECT id,name FROM brands ORDER BY name")->fetchAll() : [];
$series = $HAS_SERIES ? db_query("SELECT id,name,brand_id FROM series ORDER BY name")->fetchAll() : [];
$types  = ($HAS_TYPES && $HAS_ATID) ? db_query("SELECT id,code,name FROM article_types ORDER BY name")->fetchAll() : [];
$divs   = ($HAS_DIVS && $HAS_MAP) ? db_query("SELECT id,name,prefix FROM divisions ORDER BY name")->fetchAll() : [];

$article = [
  'id' => 0,
  'code' => '',
  'name' => '',
  'brand_id' => null,
  'series_id' => null,
  'article_type_id' => null,
  'modules' => 1,
  'requires_cover' => 0,
];

$selected_div_ids = [];

if ($id > 0) {
  $article = db_query("SELECT * FROM articles WHERE id=?", [$id])->fetch();
  if (!$article) die('Artículo no encontrado');

  if ($HAS_MODULES && !isset($article['modules'])) $article['modules'] = 1;
  if ($HAS_REQ && !isset($article['requires_cover'])) $article['requires_cover'] = 0;

  if ($HAS_MAP) {
    $tmp = db_query("SELECT division_id FROM article_divisions WHERE article_id=?", [$id])->fetchAll();
    $selected_div_ids = array_map(fn($x)=>(int)$x['division_id'], $tmp);
  }
}

if (is_post()) {
  $action = post_param('action');

  if ($action === 'delete' && $id > 0) {
    if ($HAS_MAP) db_query("DELETE FROM article_divisions WHERE article_id=?", [$id]);
    db_query("DELETE FROM articles WHERE id=?", [$id]);
    set_flash('success', 'Artículo eliminado.');
    redirect('index.php?page=articles');
  }

  if ($action === 'save') {
    $code = trim(post_param('code'));
    $name = trim(post_param('name'));
    $brand_id = ($HAS_BRANDS && post_param('brand_id')!=='') ? (int)post_param('brand_id') : null;
    $series_id = ($HAS_SERIES && post_param('series_id')!=='') ? (int)post_param('series_id') : null;
    $type_id = ($HAS_TYPES && $HAS_ATID && post_param('article_type_id')!=='') ? (int)post_param('article_type_id') : null;

    $modules = $HAS_MODULES ? max(0, (int)post_param('modules', 1)) : null;
    $req = $HAS_REQ ? (int)post_param('requires_cover', 0) : null;

    $div_ids = [];
    if ($HAS_MAP) {
      $div_ids = $_POST['division_ids'] ?? [];
      $div_ids = array_values(array_filter(array_map('intval', is_array($div_ids)?$div_ids:[])));
    }

    if ($code === '' || $name === '') {
      set_flash('error', 'Código y nombre son obligatorios.');
      redirect('index.php?page=article_detail'.($id>0 ? '&id='.$id : ''));
    }
    if (($HAS_TYPES && $HAS_ATID) && !$type_id) {
      set_flash('error', 'Selecciona el tipo de artículo.');
      redirect('index.php?page=article_detail'.($id>0 ? '&id='.$id : ''));
    }

    if ($id > 0) {
      $sets = ["code=?","name=?"];
      $vals = [$code,$name];

      if ($HAS_BRANDS) { $sets[]="brand_id=?"; $vals[]=$brand_id; }
      if ($HAS_SERIES) { $sets[]="series_id=?"; $vals[]=$series_id; }
      if ($HAS_TYPES && $HAS_ATID) { $sets[]="article_type_id=?"; $vals[]=$type_id; }
      if ($HAS_MODULES) { $sets[]="modules=?"; $vals[]=$modules; }
      if ($HAS_REQ) { $sets[]="requires_cover=?"; $vals[]=$req; }

      $vals[] = $id;
      db_query("UPDATE articles SET ".implode(',', $sets)." WHERE id=?", $vals);

      if ($HAS_MAP) {
        db_query("DELETE FROM article_divisions WHERE article_id=?", [$id]);
        foreach ($div_ids as $did) {
          db_query("INSERT INTO article_divisions(article_id,division_id) VALUES (?,?)", [$id,$did]);
        }
      }

      set_flash('success','Artículo actualizado.');
      redirect('index.php?page=articles');
    } else {
      $cols = ["code","name"];
      $vals = [$code,$name];

      if ($HAS_BRANDS) { $cols[]="brand_id"; $vals[]=$brand_id; }
      if ($HAS_SERIES) { $cols[]="series_id"; $vals[]=$series_id; }
      if ($HAS_TYPES && $HAS_ATID) { $cols[]="article_type_id"; $vals[]=$type_id; }
      if ($HAS_MODULES) { $cols[]="modules"; $vals[]=$modules; }
      if ($HAS_REQ) { $cols[]="requires_cover"; $vals[]=$req; }

      db_query("INSERT INTO articles(".implode(',', $cols).") VALUES (".implode(',', array_fill(0,count($cols),'?')).")", $vals);
      $newId = (int)db_connect()->lastInsertId();

      if ($HAS_MAP) {
        foreach ($div_ids as $did) {
          db_query("INSERT INTO article_divisions(article_id,division_id) VALUES (?,?)", [$newId,$did]);
        }
      }

      set_flash('success','Artículo creado.');
      redirect('index.php?page=articles');
    }
  }
}

include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h1 class="h4 mb-0"><?= $id>0 ? 'Editar artículo' : 'Nuevo artículo' ?></h1>
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=articles"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" action="index.php?page=article_detail<?= $id>0 ? '&id='.h($id) : '' ?>" class="row g-3" id="articleSaveForm">
      <input type="hidden" name="action" value="save">

      <div class="col-md-3">
        <label class="form-label">Código</label>
        <input class="form-control" name="code" value="<?=h($article['code'] ?? '')?>" required>
      </div>

      <div class="col-md-9">
        <label class="form-label">Nombre</label>
        <input class="form-control" name="name" value="<?=h($article['name'] ?? '')?>" required>
      </div>

      <?php if($HAS_TYPES && $HAS_ATID): ?>
      <div class="col-md-4">
        <label class="form-label">Tipo de artículo</label>
        <select class="form-select" name="article_type_id" required id="article_type_id">
          <option value="">(selecciona)</option>
          <?php foreach($types as $t): ?>
            <option value="<?=h($t['id'])?>" data-code="<?=h($t['code'])?>"
              <?= ((int)($article['article_type_id'] ?? 0)===(int)$t['id'])?'selected':'' ?>>
              <?=h($t['name'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <?php if($HAS_MODULES): ?>
      <div class="col-md-2" id="wrap_modules">
        <label class="form-label">Módulos</label>
        <input class="form-control" type="number" name="modules" min="0" value="<?=h($article['modules'] ?? 1)?>">
      </div>
      <?php endif; ?>

      <?php if($HAS_REQ): ?>
      <div class="col-md-3" id="wrap_cover">
        <label class="form-label">Requiere cubretecla</label>
        <select class="form-select" name="requires_cover">
          <option value="0" <?= ((int)($article['requires_cover'] ?? 0)===0)?'selected':'' ?>>No</option>
          <option value="1" <?= ((int)($article['requires_cover'] ?? 0)===1)?'selected':'' ?>>Sí</option>
        </select>
      </div>
      <?php endif; ?>

      <?php if($HAS_BRANDS): ?>
      <div class="col-md-4">
        <label class="form-label">Marca</label>
        <select class="form-select" name="brand_id" id="brand_id">
          <option value="">(sin marca)</option>
          <?php foreach($brands as $b): ?>
            <option value="<?=h($b['id'])?>" <?= ((int)($article['brand_id'] ?? 0)===(int)$b['id'])?'selected':'' ?>>
              <?=h($b['name'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <?php if($HAS_SERIES): ?>
      <div class="col-md-4">
        <label class="form-label">Serie</label>
        <select class="form-select" name="series_id" id="series_id">
          <option value="">(sin serie)</option>
          <?php foreach($series as $s): ?>
            <option value="<?=h($s['id'])?>" data-brand="<?=h($s['brand_id'])?>"
              <?= ((int)($article['series_id'] ?? 0)===(int)$s['id'])?'selected':'' ?>>
              <?=h($s['name'])?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Tip: filtra por marca automáticamente.</div>
      </div>
      <?php endif; ?>

      <div class="col-12">
        <label class="form-label">Divisiones donde aplica</label>
        <?php if(!$HAS_MAP || !$HAS_DIVS): ?>
          <div class="text-muted">Falta tabla <code>divisions</code> o <code>article_divisions</code>.</div>
        <?php else: ?>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach($divs as $d): ?>
              <?php $checked = in_array((int)$d['id'], $selected_div_ids, true); ?>
              <label class="border rounded px-2 py-1 small">
                <input type="checkbox" name="division_ids[]" value="<?=h($d['id'])?>" <?= $checked?'checked':'' ?>>
                <?=h($d['name'])?> <?= $d['prefix'] ? '('.h($d['prefix']).')' : '' ?>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="form-text">Un artículo puede pertenecer a múltiples divisiones.</div>
        <?php endif; ?>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
        <a class="btn btn-outline-secondary" href="index.php?page=articles">Cancelar</a>
      </div>
    </form>

    <?php if($id>0): ?>
      <hr>
      <form method="post" action="index.php?page=article_detail&id=<?=h($id)?>" onsubmit="return confirm('¿Eliminar este artículo? (no se puede deshacer)');">
        <input type="hidden" name="action" value="delete">
        <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Eliminar</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  const brand = document.getElementById('brand_id');
  const series = document.getElementById('series_id');
  function filterSeries(){
    if(!brand || !series) return;
    const bid = brand.value;
    [...series.options].forEach((opt,i)=>{
      if(i===0) return;
      const ob = opt.getAttribute('data-brand');
      opt.hidden = (bid && ob !== bid);
    });
    const sel = series.options[series.selectedIndex];
    if(sel && sel.hidden) series.value = '';
  }
  if(brand) brand.addEventListener('change', filterSeries);
  filterSeries();

  const typeSel = document.getElementById('article_type_id');
  const wrapC = document.getElementById('wrap_cover');
  function applyTypeUI(){
    if(!typeSel || !wrapC) return;
    const opt = typeSel.options[typeSel.selectedIndex];
    const code = (opt ? (opt.getAttribute('data-code')||'') : '').toLowerCase();
    const showCover = (code.includes('fruto') || code.includes('mecan') || code.includes('fruit'));
    wrapC.style.display = showCover ? '' : 'none';
  }
  if(typeSel) typeSel.addEventListener('change', applyTypeUI);
  applyTypeUI();
})();
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
