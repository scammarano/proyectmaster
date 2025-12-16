<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attachments.php';

require_login();

$area_id = (int) get_param('id', 0);
$area = db_query("SELECT * FROM areas WHERE id=?", [$area_id])->fetch();
if (!$area) die('Área no encontrada');

$project_id = (int)($area['project_id'] ?? 0);
$project = $project_id ? db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch() : null;

$HAS_CLOSED = (bool)db_query("SHOW COLUMNS FROM projects LIKE 'is_closed'")->fetch();
$closed = ($project && $HAS_CLOSED) ? ((int)($project['is_closed'] ?? 0)===1) : false;

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

$HAS_PARENT = (bool)db_query("SHOW COLUMNS FROM areas LIKE 'parent_area_id'")->fetch();

function area_has_children(int $id): bool {
  return (int)db_query("SELECT COUNT(*) c FROM areas WHERE parent_area_id=?", [$id])->fetch()['c'] > 0;
}
function area_points_count(int $id): int {
  return (int)db_query("SELECT COUNT(*) c FROM points WHERE area_id=?", [$id])->fetch()['c'];
}

function duplicate_area_recursive(int $old_area_id, int $project_id, ?int $new_parent_id): int {
  $old = db_query("SELECT * FROM areas WHERE id=?", [$old_area_id])->fetch();
  if (!$old) return 0;

  $HAS_AREA_CREATED_AT = col_exists('areas','created_at');
  if ($HAS_AREA_CREATED_AT) {
    db_query("INSERT INTO areas(project_id,parent_area_id,name,created_at) VALUES (?,?,?,NOW())", [$project_id, $new_parent_id, ($old['name'] ?? 'Área').' (Copia)']);
  } else {
    db_query("INSERT INTO areas(project_id,parent_area_id,name) VALUES (?,?,?)", [$project_id, $new_parent_id, ($old['name'] ?? 'Área').' (Copia)']);
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
        db_query("INSERT INTO points(".implode(',',$insCols).") VALUES (".implode(',',array_fill(0,count($insCols),'?')).")", $insVals);
      }
    }
  }

  // hijos
  if (col_exists('areas','parent_area_id')) {
    $kids = db_query("SELECT id FROM areas WHERE parent_area_id=? ORDER BY id", [$old_area_id])->fetchAll();
    foreach ($kids as $k) duplicate_area_recursive((int)$k['id'], $project_id, $new_id);
  }

  return $new_id;
}

function duplicate_point_single(int $point_id, int $to_area_id): void {
  $pt = db_query("SELECT * FROM points WHERE id=?", [$point_id])->fetch();
  if (!$pt) return;

  $cols = db_query("SHOW COLUMNS FROM points")->fetchAll();
  $colNames = array_map(fn($r)=>$r['Field'], $cols);
  $skip=['id','created_at','updated_at'];

  $insCols=[];$insVals=[];
  foreach($colNames as $c){
    if(in_array($c,$skip,true)) continue;
    if($c==='area_id'){ $insCols[]=$c; $insVals[]=$to_area_id; continue; }
    $insCols[]=$c; $insVals[]=$pt[$c] ?? null;
  }
  db_query("INSERT INTO points(".implode(',',$insCols).") VALUES (".implode(',',array_fill(0,count($insCols),'?')).")", $insVals);
}

if (is_post() && !$closed) {
  $action = post_param('action');

  if ($action==='rename_area') {
    $name=trim(post_param('name'));
    if($name===''){ add_flash('error','Nombre requerido'); redirect('index.php?page=area_detail&id='.$area_id); }
    db_query("UPDATE areas SET name=? WHERE id=?", [$name,$area_id]);
    add_flash('success','Área renombrada.');
    redirect('index.php?page=area_detail&id='.$area_id);
  }

  if ($action==='add_subarea') {
    $name=trim(post_param('name'));
    if($name===''){ add_flash('error','Nombre requerido'); redirect('index.php?page=area_detail&id='.$area_id); }
    $HAS_AREA_CREATED_AT = col_exists('areas','created_at');
    if ($HAS_AREA_CREATED_AT) {
      db_query("INSERT INTO areas(project_id,parent_area_id,name,created_at) VALUES (?,?,?,NOW())", [$project_id,$area_id,$name]);
    } else {
      db_query("INSERT INTO areas(project_id,parent_area_id,name) VALUES (?,?,?)", [$project_id,$area_id,$name]);
    }
    add_flash('success','Subárea creada.');
    redirect('index.php?page=area_detail&id='.$area_id);
  }

  if ($action==='duplicate_area') {
    $newParent = post_param('new_parent_id')!=='' ? (int)post_param('new_parent_id') : null;
    duplicate_area_recursive($area_id,$project_id,$newParent);
    add_flash('success','Área duplicada (incluye subáreas y puntos).');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas');
  }

  if ($action==='delete_area') {
    $hasPts = area_points_count($area_id)>0;
    $hasKids = area_has_children($area_id);
    if($hasPts || $hasKids){
      add_flash('error','No se puede eliminar: tiene '.($hasKids?'subáreas ':'').($hasPts?'puntos':''));
      redirect('index.php?page=area_detail&id='.$area_id);
    }
    db_query("DELETE FROM areas WHERE id=?", [$area_id]);
    add_flash('success','Área eliminada.');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-areas');
  }

  // puntos: eliminar / duplicar individual
  if ($action==='point_delete') {
    $pid = (int)post_param('point_id',0);
    if($pid>0){
      db_query("DELETE FROM points WHERE id=? AND area_id=?", [$pid,$area_id]);
      add_flash('success','Punto eliminado.');
    }
    redirect('index.php?page=area_detail&id='.$area_id);
  }

  if ($action==='point_duplicate') {
    $pid = (int)post_param('point_id',0);
    $to = post_param('to_area_id')!=='' ? (int)post_param('to_area_id') : $area_id;
    if($pid>0 && $to>0){
      duplicate_point_single($pid,$to);
      add_flash('success','Punto duplicado.');
    }
    redirect('index.php?page=area_detail&id='.$area_id);
  }

  // bulk points
  if ($action==='bulk_points') {
    $ids = $_POST['point_ids'] ?? [];
    $ids = array_values(array_filter(array_map('intval', is_array($ids)?$ids:[])));
    if(!$ids){ add_flash('error','No seleccionaste puntos.'); redirect('index.php?page=area_detail&id='.$area_id); }
    $op = post_param('op');

    if ($op==='delete') {
      $in=implode(',',array_fill(0,count($ids),'?'));
      db_query("DELETE FROM points WHERE area_id=? AND id IN ($in)", array_merge([$area_id],$ids));
      add_flash('success','Puntos eliminados.');
      redirect('index.php?page=area_detail&id='.$area_id);
    }

    if ($op==='move') {
      $to=(int)post_param('to_area_id');
      if($to<=0){ add_flash('error','Selecciona área destino.'); redirect('index.php?page=area_detail&id='.$area_id); }
      $in=implode(',',array_fill(0,count($ids),'?'));
      db_query("UPDATE points SET area_id=? WHERE area_id=? AND id IN ($in)", array_merge([$to,$area_id],$ids));
      add_flash('success','Puntos movidos.');
      redirect('index.php?page=area_detail&id='.$area_id);
    }

    if ($op==='duplicate') {
      $to=(int)post_param('to_area_id');
      if($to<=0) $to=$area_id;

      $pts = db_query("SELECT * FROM points WHERE area_id=? AND id IN (".implode(',',array_fill(0,count($ids),'?')).")",
                      array_merge([$area_id],$ids))->fetchAll();

      $cols = db_query("SHOW COLUMNS FROM points")->fetchAll();
      $colNames = array_map(fn($r)=>$r['Field'], $cols);
      $skip=['id','created_at','updated_at'];

      foreach($pts as $pt){
        $insCols=[];$insVals=[];
        foreach($colNames as $c){
          if(in_array($c,$skip,true)) continue;
          if($c==='area_id'){ $insCols[]=$c; $insVals[]=$to; continue; }
          $insCols[]=$c; $insVals[]=$pt[$c] ?? null;
        }
        db_query("INSERT INTO points(".implode(',',$insCols).") VALUES (".implode(',',array_fill(0,count($insCols),'?')).")", $insVals);
      }
      add_flash('success','Puntos duplicados.');
      redirect('index.php?page=area_detail&id='.$area_id);
    }

    add_flash('error','Operación no válida.');
    redirect('index.php?page=area_detail&id='.$area_id);
  }

  // adjuntos
  if ($action==='upload_attachment') {
    try{
      $ft = post_param('file_type_id')!=='' ? (int)post_param('file_type_id') : null;
      att_upload('area',$area_id,$_FILES['file'] ?? [],$ft);
      add_flash('success','Adjunto cargado.');
    }catch(Exception $e){
      add_flash('error','Error adjunto: '.$e->getMessage());
    }
    redirect('index.php?page=area_detail&id='.$area_id.'#tab-attachments');
  }

  if ($action==='update_attachment') {
    $att_id = (int)post_param('att_id',0);
    $new_name = trim(post_param('original_name',''));
    $ft = post_param('file_type_id')!=='' ? (int)post_param('file_type_id') : null;

    if ($att_id>0 && table_exists('attachments')) {
      $sets=[]; $vals=[];
      if ($new_name!=='' && col_exists('attachments','original_name')) { $sets[]="original_name=?"; $vals[]=$new_name; }
      if (col_exists('attachments','file_type_id')) { $sets[]="file_type_id=?"; $vals[]=$ft; }
      if ($sets) {
        $vals[]=$att_id;
        db_query("UPDATE attachments SET ".implode(',',$sets)." WHERE id=?", $vals);
        add_flash('success','Adjunto actualizado.');
      }
    }
    redirect('index.php?page=area_detail&id='.$area_id.'#tab-attachments');
  }

  if ($action==='delete_attachment') {
    att_delete((int)post_param('att_id'));
    add_flash('success','Adjunto eliminado.');
    redirect('index.php?page=area_detail&id='.$area_id.'#tab-attachments');
  }
}

$subareas = $HAS_PARENT ? db_query("SELECT * FROM areas WHERE parent_area_id=? ORDER BY name", [$area_id])->fetchAll() : [];
$points = table_exists('points') ? db_query("SELECT * FROM points WHERE area_id=? ORDER BY id DESC", [$area_id])->fetchAll() : [];

$allAreas = db_query("SELECT id,name FROM areas WHERE project_id=? ORDER BY name", [$project_id])->fetchAll();
$attachments = att_list('area',$area_id);
$fileTypes = table_exists('file_types') ? db_query("SELECT id,name FROM file_types ORDER BY name")->fetchAll() : [];

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('Área: <?=h(addslashes($area['name']))?>', `<a class="btn btn-sm btn-outline-secondary" href="index.php?page=project_detail&id=<?=h($project_id)?>#tab-areas"><i class="bi bi-arrow-left"></i> Volver al proyecto</a>`);
</script>

<?php if($closed): ?>
  <div class="alert alert-warning"><i class="bi bi-lock"></i> Proyecto cerrado: no se permiten cambios.</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <div class="d-flex align-items-center gap-2">
    <h1 class="h4 mb-0"><?=h($area['name'])?></h1>

    <?php if(!$closed): ?>
      <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#mdlRenameArea">
        <i class="bi bi-pencil"></i>
      </button>
    <?php endif; ?>
  </div>

  <span class="text-muted small">Proyecto:
    <?php if($project): ?>
      <a href="index.php?page=project_detail&id=<?=h($project_id)?>#tab-areas"><?=h($project['name'] ?? '')?></a>
    <?php else: ?>
      —
    <?php endif; ?>
  </span>
</div>

<?php if(!$closed): ?>
<div class="modal fade" id="mdlRenameArea" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="rename_area">
        <div class="modal-header">
          <h5 class="modal-title">Renombrar área</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Nombre</label>
          <input class="form-control" name="name" value="<?=h($area['name'])?>" required>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<ul class="nav nav-tabs">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-main">Subáreas y Puntos</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-attachments" id="tab-att-btn">Adjuntos</button></li>
</ul>

<div class="tab-content border border-top-0 p-3">
  <div class="tab-pane fade show active" id="tab-main">

    <div class="row g-3">
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header d-flex justify-content-between">
            <span>Subáreas</span>
            <?php if(!$closed): ?>
              <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addSub"><i class="bi bi-plus-circle"></i></button>
            <?php endif; ?>
          </div>
          <div class="card-body">

            <?php if(!$closed): ?>
            <div class="collapse mb-3" id="addSub">
              <form method="post" class="row g-2">
                <input type="hidden" name="action" value="add_subarea">
                <div class="col-8"><input class="form-control" name="name" placeholder="Nombre subárea" required></div>
                <div class="col-4 d-grid"><button class="btn btn-primary">Crear</button></div>
              </form>
            </div>
            <?php endif; ?>

            <?php if(!$subareas): ?>
              <div class="text-muted">No hay subáreas.</div>
            <?php else: ?>
              <ul class="list-group">
                <?php foreach($subareas as $sa): $c=(int)db_query("SELECT COUNT(*) c FROM points WHERE area_id=?",[(int)$sa['id']])->fetch()['c']; ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <a href="index.php?page=area_detail&id=<?=h($sa['id'])?>"><?=h($sa['name'])?></a>
                    <span class="badge text-bg-secondary"><?=h($c)?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

          </div>

          <div class="card-footer">
            <?php if(!$closed): ?>
              <form method="post" class="row g-2 mb-2" onsubmit="return confirm('¿Duplicar esta área con TODO lo que cuelga? (subáreas + puntos)');">
                <input type="hidden" name="action" value="duplicate_area">
                <div class="col-8">
                  <select class="form-select" name="new_parent_id">
                    <option value="">(duplicar al mismo nivel)</option>
                    <?php foreach($allAreas as $a): if((int)$a['id']===$area_id) continue; ?>
                      <option value="<?=h($a['id'])?>">Pegar copia bajo: <?=h($a['name'])?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-4 d-grid"><button class="btn btn-outline-warning"><i class="bi bi-copy"></i> Duplicar</button></div>
              </form>

              <form method="post" class="d-grid" onsubmit="return confirm('¿Eliminar área? Solo si no tiene puntos ni subáreas.');">
                <input type="hidden" name="action" value="delete_area">
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Eliminar área</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>Puntos (<?=count($points)?>)</span>
            <span class="text-muted small">Selección múltiple: borrar / mover / duplicar</span>
          </div>

          <div class="card-body">
            <?php if(!$points): ?>
              <div class="text-muted">No hay puntos en esta área.</div>
            <?php else: ?>
              <form method="post">
                <input type="hidden" name="action" value="bulk_points">

                <div class="d-flex gap-2 mb-2">
                  <select class="form-select form-select-sm" name="op" style="max-width:180px" <?= $closed?'disabled':'' ?>>
                    <option value="delete">Eliminar</option>
                    <option value="move">Mover</option>
                    <option value="duplicate">Duplicar</option>
                  </select>

                  <select class="form-select form-select-sm" name="to_area_id" style="max-width:260px" <?= $closed?'disabled':'' ?>>
                    <option value="">(destino si aplica)</option>
                    <?php foreach($allAreas as $a): ?><option value="<?=h($a['id'])?>"><?=h($a['name'])?></option><?php endforeach; ?>
                  </select>

                  <button class="btn btn-sm btn-outline-primary" <?= $closed?'disabled':'' ?> onclick="return confirm('¿Ejecutar acción sobre puntos seleccionados?');">Ejecutar</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(true)">Todo</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">Nada</button>
                </div>

                <div class="table-responsive">
                  <table class="table table-sm table-striped align-middle">
                    <thead>
                      <tr>
                        <th style="width:36px;"></th>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th class="text-end">Módulos</th>
                        <th class="text-end" style="width:160px;">Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach($points as $pt): ?>
                      <tr>
                        <td><input class="form-check-input ptchk" type="checkbox" name="point_ids[]" value="<?=h($pt['id'])?>"></td>
                        <td><?=h($pt['id'])?></td>
                        <td><?=h($pt['name'] ?? ('Punto '.$pt['id']))?></td>
                        <td class="text-end"><?=h($pt['modules'] ?? '')?></td>
                        <td class="text-end">
                          <a class="btn btn-sm btn-outline-primary" title="Editar" href="index.php?page=point_detail&id=<?=h($pt['id'])?>">
                            <i class="bi bi-pencil"></i>
                          </a>

                          <?php if(!$closed): ?>
                          <form method="post" class="d-inline" onsubmit="return confirm('¿Duplicar este punto?');">
                            <input type="hidden" name="action" value="point_duplicate">
                            <input type="hidden" name="point_id" value="<?=h($pt['id'])?>">
                            <input type="hidden" name="to_area_id" value="<?=h($area_id)?>">
                            <button class="btn btn-sm btn-outline-warning" title="Duplicar"><i class="bi bi-copy"></i></button>
                          </form>

                          <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este punto?');">
                            <input type="hidden" name="action" value="point_delete">
                            <input type="hidden" name="point_id" value="<?=h($pt['id'])?>">
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                          </form>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

              </form>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>

  </div>

  <div class="tab-pane fade" id="tab-attachments">
    <h2 class="h5"><i class="bi bi-paperclip"></i> Adjuntos del área</h2>

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
          <thead><tr><th>Archivo</th><th>Tipo</th><th class="text-muted">Fecha</th><th class="text-end" style="width:160px;">Acción</th></tr></thead>
          <tbody>
            <?php foreach($attachments as $a): ?>
              <tr>
                <td><a href="<?=h($a['stored_path'])?>" target="_blank"><?=h($a['original_name'])?></a></td>
                <td><?=h($a['file_type_name'] ?? '—')?></td>
                <td class="text-muted small"><?=h($a['created_at'])?></td>
                <td class="text-end">
                  <?php if(!$closed): ?>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#mdlAtt<?=h($a['id'])?>" title="Editar">
                      <i class="bi bi-pencil"></i>
                    </button>

                    <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar adjunto?');">
                      <input type="hidden" name="action" value="delete_attachment">
                      <input type="hidden" name="att_id" value="<?=h($a['id'])?>">
                      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>

                    <div class="modal fade" id="mdlAtt<?=h($a['id'])?>" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form method="post">
                            <input type="hidden" name="action" value="update_attachment">
                            <input type="hidden" name="att_id" value="<?=h($a['id'])?>">
                            <div class="modal-header">
                              <h5 class="modal-title">Editar adjunto</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                              <div class="mb-2">
                                <label class="form-label">Nombre visible</label>
                                <input class="form-control" name="original_name" value="<?=h($a['original_name'] ?? '')?>">
                              </div>
                              <div class="mb-2">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="file_type_id">
                                  <option value="">(sin tipo)</option>
                                  <?php foreach($fileTypes as $ft): ?>
                                    <option value="<?=h($ft['id'])?>" <?= ((string)($a['file_type_id'] ?? '')===(string)$ft['id'])?'selected':'' ?>><?=h($ft['name'])?></option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="form-text">El archivo físico no se reemplaza aquí; solo se edita nombre/tipo.</div>
                            </div>
                            <div class="modal-footer">
                              <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                              <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
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
function toggleAll(v){ document.querySelectorAll('.ptchk').forEach(ch=>ch.checked=v); }
if (location.hash==='#tab-attachments') { const btn=document.getElementById('tab-att-btn'); if(btn) btn.click(); }
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
