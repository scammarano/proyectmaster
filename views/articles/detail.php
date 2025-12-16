<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rules.php';
require_login();

if (!function_exists('table_exists')) {
  function table_exists(string $table): bool {
    try { $stmt=db_query("SHOW TABLES LIKE ?",[$table]); return (bool)$stmt->fetch(); }
    catch(Throwable $e){ return false; }
  }
}

$article_id = (int)get_param('id',0);
$article = null;
if ($article_id>0) {
  $article = db_query("SELECT * FROM articles WHERE id=?",[$article_id])->fetch();
  if(!$article) die('Artículo no encontrado');
}

$rules = load_rules();
$artRules = $rules['articles'] ?? [];
$typeRules = $artRules['type_field_rules'] ?? [];
$defaultRule = $artRules['default'] ?? ['show'=>['modules'],'hide'=>[]];

$brands = table_exists('brands') ? db_query("SELECT id,name FROM brands ORDER BY name")->fetchAll() : [];
$series = table_exists('series') ? db_query("SELECT id,name,brand_id FROM series ORDER BY name")->fetchAll() : [];
$typesAll = table_exists('article_types') ? db_query("SELECT id,code,name FROM article_types ORDER BY name")->fetchAll() : [];

$HAS_BAT = table_exists('brand_article_types');

$current_brand_id = (int)($article['brand_id'] ?? 0);
if (get_param('brand_id')!==null) $current_brand_id = (int)get_param('brand_id');

function get_filtered_types(int $brand_id, array $typesAll, bool $HAS_BAT): array {
  if (!$HAS_BAT || $brand_id<=0) return $typesAll;
  $rows = db_query("SELECT article_type_id FROM brand_article_types WHERE brand_id=?",[$brand_id])->fetchAll();
  $allowed = array_flip(array_map(fn($r)=>(int)$r['article_type_id'],$rows));
  return array_values(array_filter($typesAll, fn($t)=>isset($allowed[(int)$t['id']])));
}

$types = get_filtered_types($current_brand_id, $typesAll, $HAS_BAT);

function rule_for_type(string $type_code, array $typeRules, array $defaultRule): array {
  return $typeRules[$type_code] ?? $defaultRule;
}
function has_field(string $field, array $rule): bool {
  $show = $rule['show'] ?? [];
  $hide = $rule['hide'] ?? [];
  if (in_array($field,$hide,true)) return false;
  if (in_array($field,$show,true)) return true;
  // si no está en show/hide, por defecto lo ocultamos (más seguro)
  return false;
}

if (is_post()) {
  $action = post_param('action','save');

  if ($action==='save') {
    $code = trim(post_param('code',''));
    $name = trim(post_param('name',''));
    $brand_id = (int)post_param('brand_id',0);
    $series_id = (int)post_param('series_id',0);
    $type_id = (int)post_param('article_type_id',0);

    // revalidación: tipo permitido por marca si mapping existe
    if ($HAS_BAT && $brand_id>0 && $type_id>0) {
      $ok = db_query("SELECT 1 FROM brand_article_types WHERE brand_id=? AND article_type_id=? LIMIT 1",[$brand_id,$type_id])->fetch();
      if (!$ok) { set_flash('error','Ese tipo de artículo no está habilitado para la marca seleccionada.'); redirect('index.php?page=article_detail'.($article_id?('&id='.$article_id):'').'&brand_id='.$brand_id); }
    }

    // campos controlados por reglas
    $type_code = '';
    foreach($typesAll as $t){ if((int)$t['id']===$type_id){ $type_code=(string)$t['code']; break; } }
    $rule = rule_for_type($type_code, $typeRules, $defaultRule);

    $modules = null;
    if (has_field('modules',$rule)) $modules = (int)post_param('modules',0);

    $requires_cover = 0;
    if (has_field('requires_cover',$rule)) $requires_cover = post_param('requires_cover') ? 1 : 0;

    if ($article_id>0) {
      db_query("UPDATE articles SET code=?, name=?, brand_id=?, series_id=?, article_type_id=?, modules=?, requires_cover=? WHERE id=?",
        [$code,$name,$brand_id?:null,$series_id?:null,$type_id?:null,$modules,$requires_cover,$article_id]
      );
      set_flash('success','Artículo actualizado.');
      redirect('index.php?page=articles');
    } else {
      db_query("INSERT INTO articles (code,name,brand_id,series_id,article_type_id,modules,requires_cover) VALUES (?,?,?,?,?,?,?)",
        [$code,$name,$brand_id?:null,$series_id?:null,$type_id?:null,$modules,$requires_cover]
      );
      set_flash('success','Artículo creado.');
      redirect('index.php?page=articles');
    }
  }
}

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('Catálogo: Artículos', `
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=articles"><i class="bi bi-arrow-left"></i> Volver</a>
`);
</script>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h1 class="h4 mb-0"><?= $article_id? 'Editar artículo':'Nuevo artículo' ?></h1>
</div>

<form method="post" class="card">
  <div class="card-body row g-3">
    <input type="hidden" name="action" value="save">

    <div class="col-md-4">
      <label class="form-label">Marca</label>
      <select class="form-select" name="brand_id" id="brand_id">
        <option value="0">(sin marca)</option>
        <?php foreach($brands as $b): ?>
          <option value="<?=h($b['id'])?>" <?= ((int)$b['id']===$current_brand_id)?'selected':'' ?>><?=h($b['name'])?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">
        <?= $HAS_BAT ? 'El tipo se filtrará según la marca.' : 'Tip: puedes crear brand_article_types para filtrar tipos por marca.' ?>
      </div>
    </div>

    <div class="col-md-4">
      <label class="form-label">Tipo de artículo</label>
      <select class="form-select" name="article_type_id" id="article_type_id">
        <option value="0">(sin tipo)</option>
        <?php
          $current_type_id = (int)($article['article_type_id'] ?? 0);
          // si el tipo actual no está en el filtro (por cambios), lo agregamos arriba para no “perderlo”
          $inList = false;
          foreach($types as $t){ if((int)$t['id']===$current_type_id) $inList=true; }
          if($current_type_id>0 && !$inList){
            $tcur = null;
            foreach($typesAll as $t){ if((int)$t['id']===$current_type_id){ $tcur=$t; break; } }
            if($tcur){
              echo '<option value="'.h($tcur['id']).'" selected>(Actual) '.h($tcur['name']).' — '.h($tcur['code']).'</option>';
            }
          }
        ?>
        <?php foreach($types as $t): ?>
          <option value="<?=h($t['id'])?>" <?= ((int)$t['id']===(int)($article['article_type_id'] ?? 0))?'selected':'' ?>>
            <?=h($t['name'])?> — <?=h($t['code'])?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Las reglas se aplican por <code>article_types.code</code>.</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">Serie</label>
      <select class="form-select" name="series_id" id="series_id">
        <option value="0">(sin serie)</option>
        <?php foreach($series as $s): ?>
          <option value="<?=h($s['id'])?>" data-brand="<?=h($s['brand_id'])?>" <?= ((int)$s['id']===(int)($article['series_id'] ?? 0))?'selected':'' ?>>
            <?=h($s['name'])?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">La serie se puede filtrar por marca (simple).</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">Código</label>
      <input class="form-control" name="code" value="<?=h($article['code'] ?? '')?>" required>
    </div>

    <div class="col-md-8">
      <label class="form-label">Nombre</label>
      <input class="form-control" name="name" value="<?=h($article['name'] ?? '')?>" required>
    </div>

    <?php
      $type_code = '';
      $cur_type_id = (int)($article['article_type_id'] ?? 0);
      // para crear: usamos lo que venga por GET?brand_id, pero type se define luego; por ahora usamos defaultRule
      if($cur_type_id>0){
        foreach($typesAll as $t){ if((int)$t['id']===$cur_type_id){ $type_code=(string)$t['code']; break; } }
      }
      $rule = rule_for_type($type_code, $typeRules, $defaultRule);
      $showModules = has_field('modules',$rule);
      $showCover = has_field('requires_cover',$rule);
    ?>

    <?php if($showModules): ?>
    <div class="col-md-3">
      <label class="form-label">Módulos</label>
      <select class="form-select" name="modules">
        <?php
          $m = (int)($article['modules'] ?? 1);
          foreach([1,2,3,4,7] as $opt){
            $sel = $m===$opt ? 'selected':'';
            echo "<option value=\"$opt\" $sel>$opt</option>";
          }
        ?>
      </select>
    </div>
    <?php endif; ?>

    <?php if($showCover): ?>
    <div class="col-md-3 d-flex align-items-end">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="requires_cover" id="requires_cover" value="1" <?= !empty($article['requires_cover'])?'checked':'' ?>>
        <label class="form-check-label" for="requires_cover">Requiere cubretecla</label>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <div class="card-footer d-flex justify-content-end gap-2">
    <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
  </div>
</form>

<script>
// Marca primero: al cambiar marca, recargamos para refrescar tipos filtrados.
// Mantiene id si estás editando.
document.getElementById('brand_id')?.addEventListener('change', (e)=>{
  const bid = e.target.value || 0;
  const url = new URL(window.location.href);
  url.searchParams.set('page','article_detail');
  <?php if($article_id>0): ?>
  url.searchParams.set('id','<?= (int)$article_id ?>');
  <?php endif; ?>
  url.searchParams.set('brand_id', bid);
  window.location.href = url.toString();
});

// Filtrar series por marca de forma simple (client-side)
function filterSeries(){
  const bid = document.getElementById('brand_id')?.value || '0';
  const sel = document.getElementById('series_id');
  if(!sel) return;
  Array.from(sel.options).forEach(opt=>{
    if(!opt.value || opt.value==='0') { opt.hidden=false; return; }
    const b = opt.getAttribute('data-brand') || '';
    opt.hidden = (bid!=='0' && b!==bid);
  });
}
filterSeries();
document.getElementById('brand_id')?.addEventListener('change', filterSeries);

// (Siguiente iteración) cambiar tipo debería re-renderizar campos según reglas sin recargar.
// Por ahora lo dejamos simple.
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
