<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attachments.php';
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

$project_id = (int)get_param('id', 0);
$project = db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch();
if (!$project) die('Proyecto no encontrado');

$HAS_CLOSED = col_exists('projects','is_closed');
$closed = $HAS_CLOSED ? ((int)($project['is_closed'] ?? 0)===1) : false;

$HAS_PARENT = col_exists('areas','parent_area_id');
$HAS_AREA_CREATED_AT = col_exists('areas','created_at');

$clientCol = null;
foreach (['client','client_name','customer','customer_name'] as $c) {
  if (col_exists('projects',$c)) { $clientCol = $c; break; }
}

$HAS_DIVS = table_exists('divisions');
$HAS_PDIV = table_exists('project_divisions');
$HAS_DIV_PREFIX = $HAS_DIVS ? col_exists('divisions','prefix') : false;

// ✅ Ocultar creación de nuevas divisiones
$ALLOW_CREATE_DIVISIONS = false;

function points_count_map_by_area(int $project_id): array {
  $hasProjectId = false;
  try { $hasProjectId = (bool)db_query("SHOW COLUMNS FROM `points` LIKE 'project_id'")->fetch(); }
  catch (Throwable $e) { $hasProjectId = false; }

  if ($hasProjectId) {
    $rows = db_query("
      SELECT area_id, COUNT(*) c
      FROM points
      WHERE project_id=?
      GROUP BY area_id
    ", [$project_id])->fetchAll();
  } else {
    $rows = db_query("
      SELECT p.area_id, COUNT(*) c
      FROM points p
      JOIN areas a ON a.id = p.area_id
      WHERE a.project_id=?
      GROUP BY p.area_id
    ", [$project_id])->fetchAll();
  }

  $map = [];
  foreach ($rows as $r) $map[(int)$r['area_id']] = (int)$r['c'];
  return $map;
}

function areas_tree(int $project_id): array {
  $rows = db_query("SELECT * FROM areas WHERE project_id=? ORDER BY name", [$project_id])->fetchAll();
  $by = [];
  foreach ($rows as $r) {
    $pid = $r['parent_area_id'] ?? null;
    $k = $pid===null ? 0 : (int)$pid;
    if (!isset($by[$k])) $by[$k]=[];
    $by[$k][] = $r;
  }
  return $by;
}

function subtree_points_total(int $area_id, array $by, array $directMap): int {
  $total = (int)($directMap[$area_id] ?? 0);
  foreach (($by[$area_id] ?? []) as $child) {
    $total += subtree_points_total((int)$child['id'], $by, $directMap);
  }
  return $total;
}

function area_has_children(int $id): bool {
  return (int)db_query("SELECT COUNT(*) c FROM areas WHERE parent_area_id=?", [$id])->fetch()['c'] > 0;
}
function area_points_count(int $id): int {
  return (int)db_query("SELECT COUNT(*) c FROM points WHERE area_id=?", [$id])->fetch()['c'] > 0 ? 1 : 0;
}

/**
 * Duplicación recursiva: área + subáreas + puntos
 */
function duplicate_area_recursive(int $old_area_id, int $project_id, ?int $new_parent_id): int {
  $old = db_query("SELECT * FROM areas WHERE id=?", [$old_area_id])->fetch();
  if (!$old) return 0;

  $HAS_AREA_CREATED_AT = (bool)db_query("SHOW COLUMNS FROM areas LIKE 'created_at'")->fetch();
  if ($HAS_AREA_CREATED_AT) {
    db_query("INSERT INTO areas(project_id,parent_area_id,name,created_at) VALUES (?,?,?,NOW())",
      [$project_id, $new_parent_id, ($old['name'] ?? 'Área').' (Copia)']
    );
  } else {
    db_query("INSERT INTO areas(project_id,parent_area_id,name) VALUES (?,?,?)",
      [$project_id, $new_parent_id, ($old['name'] ?? 'Área').' (Copia)']
    );
  }
  $new_id = (int)db_connect()->lastInsertId();

  // puntos
  if (table_exists('points')) {
    $pts = db_query("SELECT * FROM points WHERE area_id=?", [$old_area_id])->fetchAll();
    if ($pts) {
      $cols = db_query("SHOW COLUMNS FROM points")->fetchAll();
      $colNames = array_map(fn($r)=>$r['Field'], $cols);
      $skip = ['id','created_at','updated_at'];
      foreach ($pts as $pt) {
        $insCols=[]; $insVals=[];
        foreach ($colNames as $c) {
          if (in_array($c,$skip,true)) continue;
          if ($c==='project_id') { $insCols[]=$c; $insVals[]=$project_id; continue; }
          if ($c==='area_id') { $insCols[]=$c; $insVals[]=$new_id; continue; }
          $insCols[]=$c; $insVals[]=$pt[$c] ?? null;
        }
        if ($insCols) {
          db_query("INSERT INTO points(".implode(',',$insCols).") VALUES (".implode(',',array_fill(0,count($insCols),'?')).")", $insVals);
        }
      }
    }
  }

  // hijos
  if ((bool)db_query("SHOW COLUMNS FROM areas LIKE 'parent_area_id'")->fetch()) {
    $kids = db_query("SELECT id FROM areas WHERE parent_area_id=? ORDER BY id", [$old_area_id])->fetchAll();
    foreach ($kids as $k) duplicate_area_recursive((int)$k['id'], $project_id, $new_id);
  }

  return $new_id;
}

function project_divisions_ids(int $project_id): array {
  if (!table_exists('project_divisions')) return [];
  $rows = db_query("SELECT division_id FROM project_divisions WHERE project_id=?", [$project_id])->fetchAll();
  return array_map(fn($r)=>(int)$r['division_id'], $rows);
}

function render_area_row($area, $by, $directMap, $level, $closed) {
  $id=(int)$area['id'];
  $kids=$by[$id] ?? [];
  $hasKids=count($kids)>0;

  $pts = subtree_points_total($id, $by, $directMap);

  // indent más marcado
  $pad = 28*$level;
  $cid="kids_$id";

  echo '<div class="area-row border rounded p-2 mb-2 d-flex justify-content-between align-items-center" '.
       'style="padding-left:'.(12+$pad).'px; border-left-width:'.(4+$level*2).'px;" '.
       'draggable="'.($closed?'false':'true').'" data-area-id="'.$id.'">';
  echo '<div class="d-flex align-items-center gap-2">';
  if ($hasKids) echo '<button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#'.$cid.'"><i class="bi bi-chevron-down"></i></button>';
  else echo '<span style="width:34px;display:inline-block"></span>';

  echo '<span class="dropzone me-1" title="Soltar aquí para hacerlo hijo" data-drop-area="'.$id.'"></span>';

  echo '<a class="text-decoration-none fw-semibold" href="index.php?page=area_detail&id='.$id.'">'.h($area['name']).'</a>';
  echo '<span class="badge text-bg-secondary">'.$pts.' pts</span>';
  echo '</div>';

  echo '<div class="d-flex gap-1">';
  echo '<a class="btn btn-sm btn-outline-primary" title="Abrir" href="index.php?page=area_detail&id='.$id.'"><i class="bi bi-box-arrow-in-right"></i></a>';
  echo '<a class="btn btn-sm btn-outline-secondary" title="Adjuntos del área" href="index.php?page=area_detail&id='.$id.'#tab-attachments"><i class="bi bi-paperclip"></i></a>';
  echo '<a class="btn btn-sm btn-outline-primary" href="index.php?page=reports_project&id=<?=h($project_id)?>">     <i class="bi bi-graph-up"></i> Reportes   </a>';

  if (!$closed) {
    echo '<form method="post" class="d-inline" onsubmit="return confirm(\'¿Duplicar esta área con TODO lo que cuelga? (subáreas + puntos)\');">';
    echo '<input type="hidden" name="action" value="duplicate_area">';
    echo '<input type="hidden" name="area_id" value="'.$id.'">';
    echo '<button class="btn btn-sm btn-outline-warning" title="Duplicar"><i class="bi bi-copy"></i></button>';
    echo '</form>';

    echo '<form method="post" class="d-inline" onsubmit="return confirm(\'¿Eliminar área? Solo si no tiene puntos ni subáreas.\');">';
    echo '<input type="hidden" name="action" value="delete_area">';
    echo '<input type="hidden" name="area_id" value="'.$id.'">';
    echo '<button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>';
    echo '</form>';
  }

  echo '</div>';
  echo '</div>';

  if ($hasKids) {
    echo '<div class="collapse show ms-2" id="'.$cid.'">';
    foreach ($kids as $k) render_area_row($k,$by,$directMap,$level+1,$closed);
    echo '</div>';
  }
}

if (is_post()) {
  if ($closed) { add_flash('error','Proyecto cerrado: no se permiten cambios.'); redirect('index.php?page=project_detail&id='.$project_id); }
  $action = post_param('action','');

  // ====== GENERAL ======
  if ($action==='save_project') {
    $name = trim(post_param('name',''));
    if ($name==='') { add_flash('error','Nombre requerido'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-general'); }

    $fields = ['name'=>$name];
    if ($clientCol) $fields[$clientCol] = trim(post_param('client',''));

    if ($HAS_CLOSED) $fields['is_closed'] = post_param('is_closed') ? 1 : 0;

    $sets=[]; $vals=[];
    foreach($fields as $k=>$v){ $sets[]="`$k`=?"; $vals[]=$v; }
    $vals[]=$project_id;
    db_query("UPDATE projects SET ".implode(',',$sets)." WHERE id=?", $vals);

    add_flash('success','Proyecto actualizado.');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-general');
  }

  if ($action==='div_add' && $HAS_DIVS) {
    if (!$HAS_PDIV) { add_flash('error','Falta tabla project_divisions.'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-general'); }
    $did = (int)post_param('division_id',0);
    if ($did<=0) { add_flash('error','Selecciona una división'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-general'); }
    db_query("INSERT IGNORE INTO project_divisions(project_id,division_id) VALUES (?,?)", [$project_id,$did]);
    add_flash('success','División agregada al proyecto.');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-general');
  }

  if ($action==='div_remove' && $HAS_PDIV) {
    $did = (int)post_param('division_id',0);
    db_query("DELETE FROM project_divisions WHERE project_id=? AND division_id=?", [$project_id,$did]);
    add_flash('success','División removida del proyecto.');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-general');
  }

  if ($action==='div_update' && $HAS_DIVS) {
    $did=(int)post_param('division_id',0);
    $dname=trim(post_param('d_name',''));
    $dprefix=trim(post_param('d_prefix',''));
    if($did<=0||$dname===''){ add_flash('error','Datos inválidos'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-general'); }
    if ($HAS_DIV_PREFIX) db_query("UPDATE divisions SET name=?, prefix=? WHERE id=?", [$dname,$dprefix,$did]);
    else db_query("UPDATE divisions SET name=? WHERE id=?", [$dname,$did]);
    add_flash('success','División actualizada.');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-general');
  }

  if ($action==='div_delete' && $HAS_DIVS) {
    $did=(int)post_param('division_id',0);
    if($did<=0){ add_flash('error','Datos inválidos'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-general'); }

    $usedProj = $HAS_PDIV ? (int)db_query("SELECT COUNT(*) c FROM project_divisions WHERE division_id=?",[$did])->fetch()['c'] : 0;
    $usedPts  = col_exists('points','division_id') ? (int)db_query("SELECT COUNT(*) c FROM points WHERE division_id=?",[$did])->fetch()['c'] : 0;
    $usedArt  = table_exists('article_divisions') ? (int)db_query("SELECT COUNT(*) c FROM article_divisions WHERE division_id=?",[$did])->fetch()['c'] : 0;

    if ($usedProj>0 || $usedPts>0 || $usedArt>0) {
      add_flash('error',"No se puede eliminar: usada en proyectos($usedProj), puntos($usedPts), artículos($usedArt).");
      redirect('index.php?page=project_detail&id='.$project_id.'#tab-general');
    }

    db_query("DELETE FROM divisions WHERE id=?",[$did]);
    add_flash('success','División eliminada.');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-general');
  }

  // ====== AREAS ======
  if ($action==='add_area') {
    $name = trim(post_param('name',''));
    $parent = post_param('parent_area_id') !== '' ? (int)post_param('parent_area_id') : null;
    if ($name==='') { add_flash('error','Nombre requerido'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas'); }

    if ($HAS_AREA_CREATED_AT) {
      db_query("INSERT INTO areas(project_id,parent_area_id,name,created_at) VALUES (?,?,?,NOW())", [$project_id, $parent, $name]);
    } else {
      db_query("INSERT INTO areas(project_id,parent_area_id,name) VALUES (?,?,?)", [$project_id, $parent, $name]);
    }
    add_flash('success','Área creada.');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas');
  }

  if ($action==='move_area') {
    $area_id=(int)post_param('area_id',0);
    $new_parent = post_param('new_parent_id')==='' ? null : (int)post_param('new_parent_id');
    if ($area_id<=0) { add_flash('error','Área inválida'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas'); }

    // Evitar loops: no permitir hacer padre de sí mismo
    if ($new_parent!==null && $new_parent===$area_id) { add_flash('error','Movimiento inválido'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas'); }

    // Restringir a mismo proyecto
    $a = db_query("SELECT id,project_id FROM areas WHERE id=?",[$area_id])->fetch();
    if (!$a || (int)$a['project_id']!==$project_id) { add_flash('error','Área no pertenece a este proyecto'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas'); }

    if ($new_parent!==null) {
      $p = db_query("SELECT id,project_id FROM areas WHERE id=?",[$new_parent])->fetch();
      if (!$p || (int)$p['project_id']!==$project_id) { add_flash('error','Padre inválido'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas'); }
      // no permitir mover bajo un descendiente
      $cur=$new_parent;
      while($cur){
        $row=db_query("SELECT parent_area_id FROM areas WHERE id=?",[$cur])->fetch();
        if(!$row) break;
        $cur = $row['parent_area_id']!==null ? (int)$row['parent_area_id'] : 0;
        if($cur===$area_id){ add_flash('error','No puedes mover un área bajo su descendiente'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas'); }
      }
    }

    db_query("UPDATE areas SET parent_area_id=? WHERE id=?", [$new_parent, $area_id]);
    add_flash('success','Área movida.');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas');
  }

  if ($action==='duplicate_area') {
    $area_id=(int)post_param('area_id',0);
    if ($area_id<=0){ add_flash('error','Área inválida'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas'); }
    $old = db_query("SELECT * FROM areas WHERE id=?",[$area_id])->fetch();
    if(!$old || (int)$old['project_id']!==$project_id){ add_flash('error','Área no válida'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas'); }
    $parent = $old['parent_area_id']!==null ? (int)$old['parent_area_id'] : null;
    duplicate_area_recursive($area_id,$project_id,$parent);
    add_flash('success','Área duplicada (incluye subáreas y puntos).');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas');
  }

  if ($action==='delete_area') {
    $area_id=(int)post_param('area_id',0);
    if ($area_id<=0){ add_flash('error','Área inválida'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas'); }
    $old = db_query("SELECT * FROM areas WHERE id=?",[$area_id])->fetch();
    if(!$old || (int)$old['project_id']!==$project_id){ add_flash('error','Área no válida'); redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas'); }

    $hasKids = area_has_children($area_id);
    $hasPts = (int)db_query("SELECT COUNT(*) c FROM points WHERE area_id=?",[$area_id])->fetch()['c']>0;
    if ($hasKids || $hasPts){
      add_flash('error','No se puede eliminar: tiene '.($hasKids?'subáreas ':'').($hasPts?'puntos':'')); 
      redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas');
    }
    db_query("DELETE FROM areas WHERE id=?",[$area_id]);
    add_flash('success','Área eliminada.');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas');
  }

  // ====== ATTACHMENTS (proyecto) ======
  if ($action==='upload_attachment') {
    try {
      $ft = post_param('file_type_id') !== '' ? (int)post_param('file_type_id') : null;
      att_upload('project',$project_id,$_FILES['file'] ?? [],$ft);
      add_flash('success','Adjunto cargado.');
    } catch (Exception $e) {
      add_flash('error','Error adjunto: '.$e->getMessage());
    }
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-attachments');
  }
  if ($action==='delete_attachment') {
    att_delete((int)post_param('att_id'));
    add_flash('success','Adjunto eliminado.');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-attachments');
  }
}

// reload data
$project = db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch();
$closed = $HAS_CLOSED ? ((int)($project['is_closed'] ?? 0)===1) : false;

$allAreas = db_query("SELECT id,name".($HAS_PARENT?",parent_area_id":"")." FROM areas WHERE project_id=? ORDER BY name", [$project_id])->fetchAll();
$by = $HAS_PARENT ? areas_tree($project_id) : [0=>$allAreas];
$roots = $HAS_PARENT ? ($by[0] ?? []) : $allAreas;
$directMap = points_count_map_by_area($project_id);

$attachments = att_list('project',$project_id);
$fileTypes = table_exists('file_types') ? db_query("SELECT id,name FROM file_types ORDER BY name")->fetchAll() : [];

$divisionsAll = $HAS_DIVS ? db_query("SELECT id,name".($HAS_DIV_PREFIX?",prefix":"")." FROM divisions ORDER BY name")->fetchAll() : [];
$projDivIds = $HAS_PDIV ? project_divisions_ids($project_id) : [];
$projDivSet = array_flip($projDivIds);

include __DIR__ . '/../layout/header.php';
?>
<style>
/* indentación más visible */
.area-row { background: #fff; border-left-style: solid; border-left-color: rgba(13,110,253,.25); }
.area-row.dragging { opacity: .55; }
.dropzone{ width:10px; height:22px; border-radius:6px; background: rgba(13,110,253,.12); display:inline-block; }
.dropzone.over{ background: rgba(13,110,253,.35); }
</style>

<script>
setCtx('Proyecto: <?=h(addslashes($project['name']))?>', `
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=projects"><i class="bi bi-arrow-left"></i> Volver</a>
  <a class="btn btn-sm btn-outline-secondary" href="#tab-attachments" onclick="document.getElementById('tab-att-btn').click()"><i class="bi bi-paperclip"></i> Adjuntos</a>
  <a class="btn btn-sm btn-outline-success" href="index.php?page=project_report&id=<?=h($project_id)?>"><i class="bi bi-file-earmark-text"></i> Explosión</a>
`);
</script>

<?php if($closed): ?>
  <div class="alert alert-warning"><i class="bi bi-lock"></i> Proyecto cerrado: no se permiten cambios.</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h1 class="h4 mb-0"><?=h($project['name'])?></h1>
  <?php if($HAS_CLOSED): ?>
    <?= $closed ? '<span class="badge text-bg-secondary"><i class="bi bi-lock"></i> Cerrado</span>' :
                 '<span class="badge text-bg-success"><i class="bi bi-unlock"></i> Abierto</span>' ?>
  <?php endif; ?>
</div>

<ul class="nav nav-tabs">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-general" id="tab-gen-btn">General</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-areas" id="tab-areas-btn">Áreas</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-attachments" id="tab-att-btn">Adjuntos</button></li>
</ul>

<div class="tab-content border border-top-0 p-3">
  <div class="tab-pane fade show active" id="tab-general">
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header"><i class="bi bi-gear"></i> Información básica</div>
          <div class="card-body">
            <form method="post" class="row g-2">
              <input type="hidden" name="action" value="save_project">
              <div class="col-12">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="name" value="<?=h($project['name'] ?? '')?>" <?= $closed?'disabled':'' ?> required>
              </div>

              <div class="col-12">
                <label class="form-label">Cliente</label>
                <?php if($clientCol): ?>
                  <input class="form-control" name="client" value="<?=h($project[$clientCol] ?? '')?>" <?= $closed?'disabled':'' ?>>
                <?php else: ?>
                  <input class="form-control" value="(tu tabla projects no tiene columna cliente)" disabled>
                  <div class="form-text">Si quieres, luego agregamos una columna <code>client</code> a <code>projects</code>.</div>
                <?php endif; ?>
              </div>

              <?php if($HAS_CLOSED): ?>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_closed" id="is_closed" value="1" <?= $closed?'checked':'' ?> <?= $closed?'disabled':'' ?>>
                  <label class="form-check-label" for="is_closed">Cerrar proyecto (bloquea edición)</label>
                </div>
              </div>
              <?php endif; ?>

              <div class="col-12 d-grid">
                <button class="btn btn-primary" <?= $closed?'disabled':'' ?>><i class="bi bi-save"></i> Guardar</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-header"><i class="bi bi-diagram-3"></i> Divisiones del proyecto</div>
          <div class="card-body">
            <?php if(!$HAS_DIVS): ?>
              <div class="text-muted">No existe la tabla <code>divisions</code>.</div>
            <?php elseif(!$HAS_PDIV): ?>
              <div class="alert alert-warning mb-0">
                Falta la tabla <code>project_divisions</code>. (Necesaria para asociar divisiones al proyecto)
              </div>
            <?php else: ?>

              <form method="post" class="row g-2 mb-3">
                <input type="hidden" name="action" value="div_add">
                <div class="col-8">
                  <select class="form-select" name="division_id" <?= $closed?'disabled':'' ?>>
                    <option value="">(selecciona una división)</option>
                    <?php foreach($divisionsAll as $d): ?>
                      <option value="<?=h($d['id'])?>"><?=h($d['name'])?><?= $HAS_DIV_PREFIX && !empty($d['prefix']) ? ' — '.$d['prefix'] : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-4 d-grid">
                  <button class="btn btn-outline-primary" <?= $closed?'disabled':'' ?>><i class="bi bi-plus-circle"></i> Agregar</button>
                </div>
              </form>

              <div class="table-responsive mb-0">
                <table class="table table-sm table-striped align-middle">
                  <thead><tr><th>División</th><th class="text-muted small">Prefix</th><th class="text-end" style="width:190px;">Acciones</th></tr></thead>
                  <tbody>
                    <?php
                      $hasAny=false;
                      foreach($divisionsAll as $d):
                        $did=(int)$d['id'];
                        if(!isset($projDivSet[$did])) continue;
                        $hasAny=true;
                    ?>
                      <tr>
                        <td><?=h($d['name'])?></td>
                        <td class="text-muted small"><?=h($HAS_DIV_PREFIX ? ($d['prefix'] ?? '') : '—')?></td>
                        <td class="text-end">
                          <?php if(!$closed): ?>
                          <button class="btn btn-sm btn-outline-secondary" type="button"
                                  data-bs-toggle="modal" data-bs-target="#mdlDiv<?=h($did)?>"
                                  title="Editar"><i class="bi bi-pencil"></i></button>

                          <form method="post" class="d-inline" onsubmit="return confirm('¿Quitar división del proyecto?');">
                            <input type="hidden" name="action" value="div_remove">
                            <input type="hidden" name="division_id" value="<?=h($did)?>">
                            <button class="btn btn-sm btn-outline-warning" title="Quitar"><i class="bi bi-x-circle"></i></button>
                          </form>

                          <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar división del sistema? Solo si no se usa en ningún lado.');">
                            <input type="hidden" name="action" value="div_delete">
                            <input type="hidden" name="division_id" value="<?=h($did)?>">
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                          </form>
                          <?php else: ?>
                            <span class="text-muted small">—</span>
                          <?php endif; ?>
                        </td>
                      </tr>

                      <div class="modal fade" id="mdlDiv<?=h($did)?>" tabindex="-1">
                        <div class="modal-dialog">
                          <div class="modal-content">
                            <form method="post">
                              <input type="hidden" name="action" value="div_update">
                              <input type="hidden" name="division_id" value="<?=h($did)?>">
                              <div class="modal-header">
                                <h5 class="modal-title">Editar división</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                              </div>
                              <div class="modal-body">
                                <div class="mb-2">
                                  <label class="form-label">Nombre</label>
                                  <input class="form-control" name="d_name" value="<?=h($d['name'])?>" required>
                                </div>
                                <?php if($HAS_DIV_PREFIX): ?>
                                <div class="mb-2">
                                  <label class="form-label">Prefix</label>
                                  <input class="form-control" name="d_prefix" value="<?=h($d['prefix'] ?? '')?>">
                                  <div class="form-text">Ej: PE, CAM, DATA</div>
                                </div>
                                <?php endif; ?>
                              </div>
                              <div class="modal-footer">
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>

                    <?php endforeach; ?>
                    <?php if(!$hasAny): ?>
                      <tr><td colspan="3" class="text-muted text-center py-3">No hay divisiones asociadas.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <?php if($ALLOW_CREATE_DIVISIONS): ?>
                <!-- bloque oculto -->
              <?php endif; ?>

            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="tab-pane fade" id="tab-areas">
    <div class="row g-3">
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header">Crear área / subárea</div>
          <div class="card-body">
            <?php if($closed): ?>
              <div class="text-muted">Proyecto cerrado: no se pueden crear áreas.</div>
            <?php else: ?>
              <form method="post" class="row g-2">
                <input type="hidden" name="action" value="add_area">
                <div class="col-12">
                  <label class="form-label">Nombre</label>
                  <input class="form-control" name="name" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Área padre (opcional)</label>
                  <select class="form-select" name="parent_area_id">
                    <option value="">(sin padre)</option>
                    <?php foreach($allAreas as $a): ?>
                      <option value="<?=h($a['id'])?>"><?=h($a['name'])?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">Tip: también puedes reordenar con drag & drop en la lista de la derecha.</div>
                </div>
                <div class="col-12 d-grid">
                  <button class="btn btn-primary"><i class="bi bi-plus-circle"></i> Crear</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <?php if(!$closed): ?>
        <div class="alert alert-info mt-3 mb-0">
          <i class="bi bi-arrows-move"></i> Drag &amp; drop: arrastra un área y suéltala sobre otra para hacerla hija. 
          Suelta en el fondo para mover al nivel raíz.
        </div>
        <?php endif; ?>

      </div>

      <div class="col-lg-7">
        <div class="card">
          <div class="card-header d-flex justify-content-between">
            <span>Áreas del proyecto</span>
            <span class="text-muted small">Colapsar/expandir • D&amp;D</span>
          </div>
          <div class="card-body" id="areas-root" data-project-id="<?=h($project_id)?>">
            <?php if(!$roots): ?>
              <div class="text-muted">No hay áreas.</div>
            <?php else: ?>
              <?php foreach($roots as $r) render_area_row($r,$by,$directMap,0,$closed); ?>
            <?php endif; ?>

            <?php if(!$closed && $roots): ?>
              <div class="border rounded p-3 text-muted text-center mt-2" id="drop-root" style="background: rgba(0,0,0,.02);">
                Suelta aquí para mover al nivel raíz
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-attachments">
    <h2 class="h5"><i class="bi bi-paperclip"></i> Adjuntos del proyecto</h2>

    <?php if(!$closed): ?>
    <form method="post" enctype="multipart/form-data" class="card mb-3">
      <div class="card-body row g-2 align-items-end">
        <input type="hidden" name="action" value="upload_attachment">
        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          <select class="form-select" name="file_type_id">
            <option value="">(sin tipo)</option>
            <?php foreach($fileTypes as $ft): ?><option value="<?=h($ft['id'])?>"><?=h($ft['name'])?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-7">
          <label class="form-label">Archivo (pdf/jpg/png/dwg)</label>
          <input class="form-control" type="file" name="file" required>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-primary"><i class="bi bi-upload"></i> Subir</button>
        </div>
      </div>
    </form>
    <?php else: ?>
      <div class="alert alert-warning"><i class="bi bi-lock"></i> Proyecto cerrado: no se permiten adjuntos.</div>
    <?php endif; ?>

    <?php if(!$attachments): ?>
      <div class="text-muted">Sin adjuntos.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
          <thead><tr><th>Archivo</th><th>Tipo</th><th class="text-muted">Fecha</th><th class="text-end" style="width:110px;">Acción</th></tr></thead>
          <tbody>
            <?php foreach($attachments as $a): ?>
              <tr>
                <td><a href="<?=h($a['stored_path'])?>" target="_blank"><?=h($a['original_name'])?></a></td>
                <td><?=h($a['file_type_name'] ?? '—')?></td>
                <td class="text-muted small"><?=h($a['created_at'])?></td>
                <td class="text-end">
                  <?php if(!$closed): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar adjunto?');">
                    <input type="hidden" name="action" value="delete_attachment">
                    <input type="hidden" name="att_id" value="<?=h($a['id'])?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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

<script>
// Mantener tab activo por hash incluso después de post/redirect
(function(){
  function activateFromHash(){
    const h = location.hash || '';
    if (h==='#tab-attachments') { const btn=document.getElementById('tab-att-btn'); if(btn) btn.click(); }
    else if (h==='#tab-areas') { const btn=document.getElementById('tab-areas-btn'); if(btn) btn.click(); }
    else { const btn=document.getElementById('tab-gen-btn'); if(btn) btn.click(); }
  }
  activateFromHash();

  // Actualizar hash cuando cambias de tab
  document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn=>{
    btn.addEventListener('shown.bs.tab', (e)=>{
      const target = btn.getAttribute('data-bs-target');
      if(target) history.replaceState(null,'', '#'+target.replace('#',''));
    });
  });
})();
</script>

<script>
// Drag & Drop (usa POST normal para que respete tus flashes y quede en #tab-areas)
(function(){
  const root = document.getElementById('areas-root');
  if(!root) return;
  const closed = <?= $closed ? 'true':'false' ?>;
  if(closed) return;

  let draggingId = null;

  function postMove(areaId, newParentId){
    const f = document.createElement('form');
    f.method='post';
    f.style.display='none';
    const add = (n,v)=>{ const i=document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; f.appendChild(i); };
    add('action','move_area');
    add('area_id', String(areaId));
    add('new_parent_id', newParentId===null ? '' : String(newParentId));
    document.body.appendChild(f);
    // asegurar que vuelves a Áreas
    history.replaceState(null,'', '#tab-areas');
    f.submit();
  }

  document.querySelectorAll('.area-row[draggable="true"]').forEach(row=>{
    row.addEventListener('dragstart', (e)=>{
      draggingId = row.getAttribute('data-area-id');
      row.classList.add('dragging');
      e.dataTransfer.effectAllowed='move';
      e.dataTransfer.setData('text/plain', draggingId);
    });
    row.addEventListener('dragend', ()=>{
      row.classList.remove('dragging');
      document.querySelectorAll('.dropzone.over').forEach(z=>z.classList.remove('over'));
    });
  });

  // drop sobre un área (hacerla hija)
  document.querySelectorAll('.area-row').forEach(row=>{
    row.addEventListener('dragover', (e)=>{
      e.preventDefault();
      const dz = row.querySelector('.dropzone');
      if(dz) dz.classList.add('over');
    });
    row.addEventListener('dragleave', ()=>{
      const dz = row.querySelector('.dropzone');
      if(dz) dz.classList.remove('over');
    });
    row.addEventListener('drop', (e)=>{
      e.preventDefault();
      const targetId = row.getAttribute('data-area-id');
      const src = e.dataTransfer.getData('text/plain');
      if(!src || !targetId || src===targetId) return;
      postMove(src, targetId);
    });
  });

  // drop a root (nivel 0)
  const dropRoot = document.getElementById('drop-root');
  if(dropRoot){
    dropRoot.addEventListener('dragover', (e)=>{ e.preventDefault(); dropRoot.classList.add('border-primary'); });
    dropRoot.addEventListener('dragleave', ()=> dropRoot.classList.remove('border-primary'));
    dropRoot.addEventListener('drop', (e)=>{
      e.preventDefault();
      dropRoot.classList.remove('border-primary');
      const src = e.dataTransfer.getData('text/plain');
      if(!src) return;
      postMove(src, null);
    });
  }
})();
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
