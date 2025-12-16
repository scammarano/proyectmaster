<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/helpers_safe.php';

require_login();
if(!is_admin()){ die('Solo admin'); }

// Detectar columnas reales
$HAS_TYPE_ID = (bool)db_query("SHOW COLUMNS FROM articles LIKE 'article_type_id'")->fetch();
$HAS_MODULES = (bool)db_query("SHOW COLUMNS FROM articles LIKE 'modules'")->fetch();
$HAS_REQ     = (bool)db_query("SHOW COLUMNS FROM articles LIKE 'requires_cover'")->fetch();
$HAS_DIVMAP  = safe_table_exists('article_divisions');

if(!$HAS_TYPE_ID){
  die('Tu tabla articles no tiene article_type_id. Este list.php es para el esquema con FK a article_types.');
}

$divisions = safe_table_exists('divisions') ? db_query("SELECT id,name,prefix FROM divisions ORDER BY name")->fetchAll() : [];
$brands    = safe_table_exists('brands') ? db_query("SELECT id,name FROM brands ORDER BY name")->fetchAll() : [];
$series    = safe_table_exists('series') ? db_query("SELECT id,name,brand_id FROM series ORDER BY name")->fetchAll() : [];
$types     = safe_table_exists('article_types') ? db_query("SELECT id,code,name FROM article_types ORDER BY name")->fetchAll() : [];

if(!$types){
  // Intento de ayuda: si no existe article_types, avisa claro.
  set_flash('error','No existe tabla article_types o está vacía. Ejecuta sql/fix_articles_article_type_id_seed.sql');
}

if (is_post()) {
  $action = post_param('action');

  if ($action==='save_article') {
    $id=(int)post_param('id',0);
    $code=trim(post_param('code',''));
    $name=trim(post_param('name',''));
    $brand_id = post_param('brand_id')!=='' ? (int)post_param('brand_id') : null;
    $series_id = post_param('series_id')!=='' ? (int)post_param('series_id') : null;

    $type_id = (int)post_param('article_type_id',0);
    $modules = (int)post_param('modules',1);
    $req     = (int)post_param('requires_cover',0);

    if($code===''||$name===''){ set_flash('error','Código y nombre son requeridos'); redirect('index.php?page=articles'); }
    if($type_id<=0){ set_flash('error','Selecciona el tipo de artículo'); redirect('index.php?page=articles'); }

    if($id>0){
      $sets = ["code=?","name=?","brand_id=?","series_id=?","article_type_id=?"];
      $vals = [$code,$name,$brand_id,$series_id,$type_id];
      if($HAS_MODULES){ $sets[]="modules=?"; $vals[]=$modules; }
      if($HAS_REQ){ $sets[]="requires_cover=?"; $vals[]=$req; }
      $vals[]=$id;

      db_query("UPDATE articles SET ".implode(',',$sets)." WHERE id=?", $vals);
      if(function_exists('user_log')) user_log('article_update','article',$id,$code);
      set_flash('success','Artículo actualizado.');
    } else {
      $cols = ["code","name","brand_id","series_id","article_type_id"];
      $vals = [$code,$name,$brand_id,$series_id,$type_id];
      if($HAS_MODULES){ $cols[]="modules"; $vals[]=$modules; }
      if($HAS_REQ){ $cols[]="requires_cover"; $vals[]=$req; }

      db_query("INSERT INTO articles(".implode(',',$cols).") VALUES (".implode(',',array_fill(0,count($cols),'?')).")", $vals);
      $id=(int)db_connect()->lastInsertId();

      if(function_exists('user_log')) user_log('article_create','article',$id,$code);
      set_flash('success','Artículo creado.');
    }

    if($HAS_DIVMAP){
      $div_ids = $_POST['division_ids'] ?? [];
      $div_ids = array_values(array_filter(array_map('intval', is_array($div_ids)?$div_ids:[])));
      db_query("DELETE FROM article_divisions WHERE article_id=?",[$id]);
      foreach($div_ids as $did){
        db_query("INSERT INTO article_divisions(article_id,division_id) VALUES (?,?)",[$id,$did]);
      }
    }

    redirect('index.php?page=articles');
  }

  if ($action==='delete_article') {
    $id=(int)post_param('id',0);
    if($id<=0) redirect('index.php?page=articles');
    db_query("DELETE FROM articles WHERE id=?",[$id]);
    if(function_exists('user_log')) user_log('article_delete','article',$id,'');
    set_flash('success','Artículo eliminado.');
    redirect('index.php?page=articles');
  }
}

$q = trim(get_param('q',''));
$where="1=1"; $params=[];
if($q!==''){
  $where="(a.code LIKE ? OR a.name LIKE ?)"; $params=["%$q%","%$q%"];
}

$selectModules = $HAS_MODULES ? "a.modules" : "0 AS modules";
$selectReq     = $HAS_REQ ? "a.requires_cover" : "0 AS requires_cover";

$rows = db_query("
  SELECT a.*, $selectModules, $selectReq,
         b.name brand_name, s.name series_name,
         t.name type_name, t.code type_code
  FROM articles a
  LEFT JOIN brands b ON b.id=a.brand_id
  LEFT JOIN series s ON s.id=a.series_id
  LEFT JOIN article_types t ON t.id=a.article_type_id
  WHERE $where
  ORDER BY a.id DESC
  LIMIT 500
", $params)->fetchAll();

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('Catálogo: Artículos', `
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=divisions"><i class="bi bi-layers"></i> Divisiones</a>
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=catalog_rules"><i class="bi bi-gear"></i> Reglas</a>
`);
</script>

<?php if(!$types): ?>
  <div class="alert alert-warning">
    Falta catálogo de tipos: ejecuta <code>sql/fix_articles_article_type_id_seed.sql</code>.
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Crear / editar artículo</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="save_article">
          <input type="hidden" name="id" id="a_id" value="">
          <div class="col-6">
            <label class="form-label">Código</label>
            <input class="form-control" name="code" id="a_code" required>
          </div>
          <div class="col-6">
            <label class="form-label">Módulos</label>
            <input class="form-control" type="number" name="modules" id="a_modules" value="1" min="0" <?= $HAS_MODULES?'':'disabled' ?>>
          </div>
          <div class="col-12">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="name" id="a_name" required>
          </div>
          <div class="col-6">
            <label class="form-label">Marca</label>
            <select class="form-select" name="brand_id" id="a_brand">
              <option value="">(sin marca)</option>
              <?php foreach($brands as $b): ?><option value="<?=h($b['id'])?>"><?=h($b['name'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Serie</label>
            <select class="form-select" name="series_id" id="a_series">
              <option value="">(sin serie)</option>
              <?php foreach($series as $s): ?><option value="<?=h($s['id'])?>"><?=h($s['name'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Tipo</label>
            <select class="form-select" name="article_type_id" id="a_type" required>
              <option value="">(selecciona)</option>
              <?php foreach($types as $t): ?>
                <option value="<?=h($t['id'])?>"><?=h($t['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Requiere cubretecla</label>
            <select class="form-select" name="requires_cover" id="a_req" <?= $HAS_REQ?'':'disabled' ?>>
              <option value="0">No</option>
              <option value="1">Sí</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Divisiones donde aplica</label>
            <?php if(!$HAS_DIVMAP): ?>
              <div class="text-danger small">Falta tabla <code>article_divisions</code>.</div>
            <?php else: ?>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach($divisions as $d): ?>
                  <label class="border rounded px-2 py-1 small">
                    <input type="checkbox" class="a_div" name="division_ids[]" value="<?=h($d['id'])?>">
                    <?=h($d['name'])?> <?= $d['prefix']?('('.h($d['prefix']).')'):'' ?>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="col-12 d-grid">
            <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span>Artículos (últimos 500)</span>
        <form class="d-flex gap-2" method="get">
          <input type="hidden" name="page" value="articles">
          <input class="form-control form-control-sm" name="q" value="<?=h($q)?>" placeholder="Buscar código o nombre">
          <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        </form>
      </div>
      <div class="card-body">
        <?php if(!$rows): ?>
          <div class="text-muted">Sin artículos.</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle">
            <thead><tr>
              <th>ID</th><th>Código</th><th>Nombre</th><th>Tipo</th><th class="text-end">M</th><th>Marca/Serie</th><th class="text-end" style="width:160px">Acciones</th>
            </tr></thead>
            <tbody>
              <?php foreach($rows as $r): ?>
              <?php
                $div_ids=[];
                if($HAS_DIVMAP){
                  $tmp=db_query("SELECT division_id FROM article_divisions WHERE article_id=?",[(int)$r['id']])->fetchAll();
                  $div_ids=array_map(fn($x)=>(int)$x['division_id'],$tmp);
                }
              ?>
              <tr>
                <td><?=h($r['id'])?></td>
                <td><code><?=h($r['code'])?></code></td>
                <td><?=h($r['name'])?></td>
                <td><?=h($r['type_name'] ?? '—')?></td>
                <td class="text-end"><?=h($r['modules'] ?? '')?></td>
                <td class="text-muted small"><?=h($r['brand_name'] ?? '—')?> / <?=h($r['series_name'] ?? '—')?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" type="button"
                    onclick='fillArticle(<?= (int)$r["id"] ?>, <?= json_encode($r["code"]) ?>, <?= json_encode($r["name"]) ?>, <?= (int)($r["article_type_id"]??0) ?>, <?= (int)($r["modules"]??1) ?>, <?= json_encode((string)($r["brand_id"]??"")) ?>, <?= json_encode((string)($r["series_id"]??"")) ?>, <?= (int)($r["requires_cover"]??0) ?>, <?= json_encode($div_ids) ?>)'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar artículo?');">
                    <input type="hidden" name="action" value="delete_article">
                    <input type="hidden" name="id" value="<?=h($r['id'])?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
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
function fillArticle(id,code,name,type_id,modules,brand_id,series_id,req,div_ids){
  document.getElementById('a_id').value=id;
  document.getElementById('a_code').value=code||'';
  document.getElementById('a_name').value=name||'';
  document.getElementById('a_type').value=String(type_id||'');
  document.getElementById('a_modules').value=modules||1;
  document.getElementById('a_brand').value=brand_id||'';
  document.getElementById('a_series').value=series_id||'';
  document.getElementById('a_req').value = String(req||0);

  document.querySelectorAll('.a_div').forEach(ch=>ch.checked=false);
  (div_ids||[]).forEach(d=>{
    const el = document.querySelector('.a_div[value="'+d+'"]');
    if(el) el.checked=true;
  });
  window.scrollTo({top:0,behavior:'smooth'});
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
