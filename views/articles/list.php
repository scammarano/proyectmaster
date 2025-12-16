<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rules.php';
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

function normalize_code($s){ $s=trim((string)$s); return $s; }

function find_id_by_name(array $rows, string $name): ?int {
  $name = trim($name);
  if ($name==='') return null;
  foreach ($rows as $r) {
    if (mb_strtolower(trim($r['name'])) === mb_strtolower($name)) return (int)$r['id'];
  }
  return null;
}

if (is_post()) {
  $action = post_param('action','');

  // Duplicar desde lista
  if ($action==='dup' && ($id=(int)post_param('id',0))>0) {
    $a = db_query("SELECT * FROM articles WHERE id=?", [$id])->fetch();
    if ($a) {
      $code = ($a['code'] ?? '').'-COPY';
      // evitar duplicados
      $i=1;
      while (db_query("SELECT id FROM articles WHERE code=?", [$code])->fetch()) { $i++; $code = ($a['code'] ?? '')."-COPY$i"; }

      $cols = db_query("SHOW COLUMNS FROM articles")->fetchAll();
      $colNames = array_map(fn($x)=>$x['Field'],$cols);
      $skip = ['id','created_at','updated_at'];
      $insCols=[];$insVals=[];
      foreach($colNames as $c){
        if(in_array($c,$skip,true)) continue;
        $insCols[]=$c;
        if($c==='code') $insVals[]=$code;
        else if($c==='name') $insVals[]=(string)($a['name'] ?? '').' (Copia)';
        else $insVals[]=$a[$c] ?? null;
      }
      db_query("INSERT INTO articles(".implode(',',$insCols).") VALUES (".implode(',',array_fill(0,count($insCols),'?')).")", $insVals);
      $newId = (int)db_connect()->lastInsertId();

      if ($HAS_MAP) {
        $divs = db_query("SELECT division_id FROM article_divisions WHERE article_id=?", [$id])->fetchAll();
        foreach($divs as $d){
          db_query("INSERT INTO article_divisions(article_id,division_id) VALUES (?,?)", [$newId,(int)$d['division_id']]);
        }
      }
      set_flash('success','Artículo duplicado.');
      redirect('index.php?page=article_detail&id='.$newId);
    }
    set_flash('error','No se pudo duplicar.');
    redirect('index.php?page=articles');
  }

  // Eliminar desde lista
  if ($action==='del' && ($id=(int)post_param('id',0))>0) {
    if ($HAS_MAP) db_query("DELETE FROM article_divisions WHERE article_id=?", [$id]);
    db_query("DELETE FROM articles WHERE id=?", [$id]);
    set_flash('success','Artículo eliminado.');
    redirect('index.php?page=articles');
  }

  // Import CSV
  if ($action==='import_csv') {
    if (!isset($_FILES['csv']) || ($_FILES['csv']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) {
      set_flash('error','Sube un .csv válido.');
      redirect('index.php?page=articles');
    }

    $brands = $HAS_BRANDS ? db_query("SELECT id,name FROM brands")->fetchAll() : [];
    $series = $HAS_SERIES ? db_query("SELECT id,name,brand_id FROM series")->fetchAll() : [];
    $types  = ($HAS_TYPES && $HAS_ATID) ? db_query("SELECT id,code,name FROM article_types")->fetchAll() : [];
    $divs   = ($HAS_DIVS && $HAS_MAP) ? db_query("SELECT id,name,prefix FROM divisions")->fetchAll() : [];

    $path = $_FILES['csv']['tmp_name'];
    $fh = fopen($path,'r');
    if (!$fh) { set_flash('error','No se pudo leer el CSV.'); redirect('index.php?page=articles'); }

    // BOM UTF-8
    $first = fgets($fh);
    if ($first === false) { fclose($fh); set_flash('error','CSV vacío.'); redirect('index.php?page=articles'); }
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
    $headers = str_getcsv($first);
    $map = [];
    foreach ($headers as $i=>$h) {
      $k = mb_strtolower(trim($h));
      $map[$k] = $i;
    }

    $required = ['codigo','nombre'];
    foreach($required as $rk){
      if (!isset($map[$rk])) { fclose($fh); set_flash('error',"CSV debe tener columna '$rk'"); redirect('index.php?page=articles'); }
    }

    $countNew=0;$countUpd=0;$countErr=0;
    db_connect()->beginTransaction();
    try{
      while(($row=fgetcsv($fh))!==false){
        if(count(array_filter($row, fn($x)=>trim((string)$x)!==''))===0) continue;

        $code = normalize_code($row[$map['codigo']] ?? '');
        $name = trim((string)($row[$map['nombre']] ?? ''));

        if($code==='' || $name===''){ $countErr++; continue; }

        $brandName = $row[$map['marca']] ?? '';
        $seriesName = $row[$map['serie']] ?? '';
        $typeCodeOrName = $row[$map['tipo']] ?? '';
        $modules = $HAS_MODULES ? (int)($row[$map['modulos']] ?? 1) : null;
        $req = $HAS_REQ ? (int)($row[$map['requiere_cubretecla']] ?? 0) : null;

        $brand_id = $HAS_BRANDS ? find_id_by_name($brands, (string)$brandName) : null;
        $series_id = null;
        if ($HAS_SERIES) {
          // intenta por nombre; si hay brand_id, filtra por marca
          $sn = trim((string)$seriesName);
          if ($sn!=='') {
            foreach($series as $s){
              if(mb_strtolower(trim($s['name']))===mb_strtolower($sn)){
                if($brand_id && (int)$s['brand_id']!== (int)$brand_id) continue;
                $series_id=(int)$s['id']; break;
              }
            }
          }
        }

        $type_id = null;
        if ($HAS_TYPES && $HAS_ATID) {
          $tc = mb_strtolower(trim((string)$typeCodeOrName));
          if ($tc!=='') {
            foreach($types as $t){
              if(mb_strtolower(trim($t['code']))===$tc || mb_strtolower(trim($t['name']))===$tc){
                $type_id=(int)$t['id']; break;
              }
            }
          }
        }

        $exists = db_query("SELECT id FROM articles WHERE code=?", [$code])->fetch();
        if ($exists) {
          $id = (int)$exists['id'];
          $sets=["name=?"]; $vals=[$name];
          if ($HAS_BRANDS) { $sets[]="brand_id=?"; $vals[]=$brand_id; }
          if ($HAS_SERIES) { $sets[]="series_id=?"; $vals[]=$series_id; }
          if ($HAS_TYPES && $HAS_ATID) { $sets[]="article_type_id=?"; $vals[]=$type_id; }
          if ($HAS_MODULES) { $sets[]="modules=?"; $vals[]=$modules; }
          if ($HAS_REQ) { $sets[]="requires_cover=?"; $vals[]=$req; }
          $vals[]=$id;
          db_query("UPDATE articles SET ".implode(',',$sets)." WHERE id=?", $vals);
          $countUpd++;
        } else {
          $cols=["code","name"]; $vals=[$code,$name];
          if ($HAS_BRANDS) { $cols[]="brand_id"; $vals[]=$brand_id; }
          if ($HAS_SERIES) { $cols[]="series_id"; $vals[]=$series_id; }
          if ($HAS_TYPES && $HAS_ATID) { $cols[]="article_type_id"; $vals[]=$type_id; }
          if ($HAS_MODULES) { $cols[]="modules"; $vals[]=$modules; }
          if ($HAS_REQ) { $cols[]="requires_cover"; $vals[]=$req; }
          db_query("INSERT INTO articles(".implode(',',$cols).") VALUES (".implode(',',array_fill(0,count($cols),'?')).")", $vals);
          $id = (int)db_connect()->lastInsertId();
          $countNew++;
        }

        // divisiones: columna "divisiones" con nombres separados por coma
        if ($HAS_MAP && isset($map['divisiones'])) {
          $divCol = (string)($row[$map['divisiones']] ?? '');
          $names = array_values(array_filter(array_map('trim', explode(',', $divCol))));
          if ($names) {
            db_query("DELETE FROM article_divisions WHERE article_id=?", [$id]);
            foreach($names as $dn){
              $did = find_id_by_name($divs, $dn);
              if($did) db_query("INSERT INTO article_divisions(article_id,division_id) VALUES (?,?)", [$id,$did]);
            }
          }
        }
      }
      db_connect()->commit();
    } catch(Throwable $e){
      db_connect()->rollBack();
      fclose($fh);
      set_flash('error','Error importando: '.$e->getMessage());
      redirect('index.php?page=articles');
    }
    fclose($fh);

    set_flash('success',"Importado CSV. Nuevos: $countNew | Actualizados: $countUpd | Filas omitidas: $countErr");
    redirect('index.php?page=articles');
  }
}

$q = trim(get_param('q',''));
$where = "1=1";
$params = [];
if ($q !== '') {
  $where = "(a.code LIKE ? OR a.name LIKE ?)";
  $params = ["%$q%","%$q%"];
}

$selectModules = $HAS_MODULES ? "a.modules" : "NULL AS modules";
$selectReq     = $HAS_REQ ? "a.requires_cover" : "NULL AS requires_cover";

$sql = "
  SELECT a.*,
         $selectModules,
         $selectReq
";
$sql .= $HAS_BRANDS ? ", b.name AS brand_name" : ", NULL AS brand_name";
$sql .= $HAS_SERIES ? ", s.name AS series_name" : ", NULL AS series_name";
$sql .= ($HAS_TYPES && $HAS_ATID) ? ", t.name AS type_name, t.code AS type_code" : ", NULL AS type_name, NULL AS type_code";
$sql .= " FROM articles a";
if ($HAS_BRANDS) $sql .= " LEFT JOIN brands b ON b.id=a.brand_id";
if ($HAS_SERIES) $sql .= " LEFT JOIN series s ON s.id=a.series_id";
if ($HAS_TYPES && $HAS_ATID) $sql .= " LEFT JOIN article_types t ON t.id=a.article_type_id";
$sql .= " WHERE $where ORDER BY a.id DESC LIMIT 500";

$rows = db_query($sql, $params)->fetchAll();

include __DIR__ . '/../layout/header.php';
?>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span><i class="bi bi-box-seam"></i> Artículos</span>

      <a class="btn btn-sm btn-primary" href="index.php?page=article_detail">
        <i class="bi bi-plus-circle"></i> Nuevo artículo
      </a>

      <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#impBox">
        <i class="bi bi-upload"></i> Importar CSV
      </button>

      <a class="btn btn-sm btn-outline-secondary" href="index.php?page=catalog_rules">
        <i class="bi bi-sliders"></i> Reglas
      </a>
    </div>

    <form class="d-flex gap-2" method="get">
      <input type="hidden" name="page" value="articles">
      <input class="form-control form-control-sm" name="q" value="<?=h($q)?>" placeholder="Buscar por código o nombre">
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
  </div>

  <div class="card-body">
    <div class="collapse mb-3" id="impBox">
      <div class="alert alert-info small mb-2">
        CSV esperado (cabeceras mínimas): <code>codigo</code>, <code>nombre</code>.
        Opcionales: <code>marca</code>, <code>serie</code>, <code>tipo</code>, <code>modulos</code>, <code>requiere_cubretecla</code>, <code>divisiones</code> (separadas por coma).
      </div>
      <form method="post" enctype="multipart/form-data" class="row g-2">
        <input type="hidden" name="action" value="import_csv">
        <div class="col-md-8">
          <input class="form-control form-control-sm" type="file" name="csv" accept=".csv,text/csv" required>
        </div>
        <div class="col-md-4 d-grid">
          <button class="btn btn-sm btn-primary"><i class="bi bi-upload"></i> Importar</button>
        </div>
      </form>
      <hr>
    </div>

    <?php if(!$rows): ?>
      <div class="text-muted">Sin artículos.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
          <thead>
          <tr>
            <th>ID</th>
            <th>Código</th>
            <th>Nombre</th>
            <th>Tipo</th>
            <th>Marca/Serie</th>
            <th class="text-end">M</th>
            <th class="text-center">Cubre</th>
            <th>Divisiones</th>
            <th class="text-end" style="width:160px">Acciones</th>
          </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <?php
              $divNames = [];
              if ($HAS_MAP && $HAS_DIVS) {
                $divNames = db_query("
                  SELECT d.name
                  FROM article_divisions ad
                  JOIN divisions d ON d.id=ad.division_id
                  WHERE ad.article_id=?
                  ORDER BY d.name
                ", [(int)$r['id']])->fetchAll();
                $divNames = array_map(fn($x)=>$x['name'], $divNames);
              }
            ?>
            <tr>
              <td><?=h($r['id'])?></td>
              <td><code><?=h($r['code'])?></code></td>
              <td><?=h($r['name'])?></td>
              <td><?=h($r['type_name'] ?? '—')?></td>
              <td class="text-muted small"><?=h($r['brand_name'] ?? '—')?> / <?=h($r['series_name'] ?? '—')?></td>
              <td class="text-end"><?=h($r['modules'] ?? '')?></td>
              <td class="text-center">
                <?php if($HAS_REQ): ?>
                  <?= ((int)($r['requires_cover'] ?? 0)===1) ? '<span title="Requiere cubretecla">✅</span>' : '—' ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td class="small"><?=h($divNames ? implode(', ', $divNames) : '—')?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="index.php?page=article_detail&id=<?=h($r['id'])?>" title="Editar">
                  <i class="bi bi-pencil"></i>
                </a>

                <form method="post" class="d-inline" onsubmit="return confirm('¿Duplicar este artículo?');">
                  <input type="hidden" name="action" value="dup">
                  <input type="hidden" name="id" value="<?=h($r['id'])?>">
                  <button class="btn btn-sm btn-outline-warning" title="Duplicar"><i class="bi bi-copy"></i></button>
                </form>

                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este artículo?');">
                  <input type="hidden" name="action" value="del">
                  <input type="hidden" name="id" value="<?=h($r['id'])?>">
                  <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
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

<?php include __DIR__ . '/../layout/footer.php'; ?>
