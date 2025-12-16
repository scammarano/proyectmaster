<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attachments.php';

require_login();

$project_id = (int) get_param('id', 0);
$project = db_query("SELECT * FROM projects WHERE id = ?", [$project_id])->fetch();
if (!$project) die('Proyecto no encontrado');

$HAS_CLOSED = (bool)db_query("SHOW COLUMNS FROM projects LIKE 'is_closed'")->fetch();
$closed = $HAS_CLOSED ? ((int)($project['is_closed'] ?? 0)===1) : false;

$HAS_PARENT = (bool)db_query("SHOW COLUMNS FROM areas LIKE 'parent_area_id'")->fetch();

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

function render_area($area, $by, $level=0) {
  $id=(int)$area['id'];
  $kids=$by[$id] ?? [];
  $hasKids=count($kids)>0;
  $pts=(int)db_query("SELECT COUNT(*) c FROM points WHERE area_id=?",[$id])->fetch()['c'];
  $pad = $level*18;
  $cid="kids_$id";

  echo '<div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2 area-node" '
     . ' data-area-id="'.$id.'" draggable="true" '
     . ' style="padding-left:'.($pad+10).'px">';
  echo '<div class="d-flex align-items-center gap-2">';
  if ($hasKids) {
    echo '<button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#'.$cid.'"><i class="bi bi-chevron-down"></i></button>';
  } else {
    echo '<span style="width:34px;display:inline-block"></span>';
  }

  echo '<span class="drop-hint me-1" title="Arrastra y suelta para reubicar"><i class="bi bi-grip-vertical"></i></span>';

  echo '<a class="text-decoration-none fw-semibold" href="index.php?page=area_detail&id='.$id.'">'.h($area['name']).'</a>';
  echo '<span class="badge text-bg-secondary">'.$pts.' pts</span>';
  echo '</div>';

  echo '<div class="d-flex gap-1">';
  echo '<a class="btn btn-sm btn-outline-primary" title="Abrir área" href="index.php?page=area_detail&id='.$id.'"><i class="bi bi-box-arrow-in-right"></i></a>';
  echo '</div>';

  echo '</div>';

  if ($hasKids) {
    echo '<div class="collapse show ms-2" id="'.$cid.'">';
    foreach ($kids as $k) render_area($k,$by,$level+1);
    echo '</div>';
  }
}

if (is_post() && !$closed) {
  $action = post_param('action');

  if ($action === 'add_area') {
    $name = trim(post_param('name'));
    $parent = post_param('parent_area_id') !== '' ? (int)post_param('parent_area_id') : null;
    if ($name==='') { set_flash('error','Nombre requerido'); redirect('index.php?page=project_detail&id='.$project_id); }
    try {
      db_query("INSERT INTO areas(project_id,parent_area_id,name,created_at) VALUES (?,?,?,NOW())", [$project_id, $parent, $name]);
    } catch (Throwable $e) {
      db_query("INSERT INTO areas(project_id,parent_area_id,name) VALUES (?,?,?)", [$project_id, $parent, $name]);
    }
    set_flash('success','Área creada.');
    redirect('index.php?page=project_detail&id='.$project_id);
  }

  if ($action === 'upload_attachment') {
    try {
      $ft = post_param('file_type_id') !== '' ? (int)post_param('file_type_id') : null;
      att_upload('project',$project_id,$_FILES['file'] ?? [],$ft);
      set_flash('success','Adjunto cargado.');
    } catch (Exception $e) {
      set_flash('error','Error adjunto: '.$e->getMessage());
    }
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-attachments');
  }

  if ($action === 'delete_attachment') {
    att_delete((int)post_param('att_id'));
    set_flash('success','Adjunto eliminado.');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-attachments');
  }
}

$allAreas = db_query("SELECT id,name,parent_area_id FROM areas WHERE project_id=? ORDER BY name", [$project_id])->fetchAll();
$by = $HAS_PARENT ? areas_tree($project_id) : [0=>$allAreas];
$roots = $HAS_PARENT ? ($by[0] ?? []) : $allAreas;

$attachments = att_list('project',$project_id);
try { $fileTypes = db_query("SELECT id,name FROM file_types ORDER BY name")->fetchAll(); } catch (Throwable $e) { $fileTypes = []; }

include __DIR__ . '/../layout/header.php';
?>
<style>
.area-node{background:#fff}
.area-node.dragging{opacity:.55}
.area-node.drop-target{outline:2px dashed rgba(0,0,0,.35); outline-offset:2px}
.drop-hint{color:rgba(0,0,0,.35); cursor:grab}
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
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-areas">Áreas</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-attachments" id="tab-att-btn">Adjuntos</button></li>
</ul>

<div class="tab-content border border-top-0 p-3">
  <div class="tab-pane fade show active" id="tab-areas">
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
                </div>
                <div class="col-12 d-grid">
                  <button class="btn btn-primary"><i class="bi bi-plus-circle"></i> Crear</button>
                </div>
              </form>
              <div class="form-text mt-2">
                <i class="bi bi-arrows-move"></i> Puedes <b>arrastrar y soltar</b> áreas en el árbol (a la derecha) para cambiar su área padre.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card">
          <div class="card-header d-flex justify-content-between">
            <span>Áreas del proyecto</span>
            <span class="text-muted small">Colapsar/expandir • Drag & Drop</span>
          </div>
          <div class="card-body">
            <?php if(!$roots): ?>
              <div class="text-muted">No hay áreas.</div>
            <?php else: ?>
              <?php foreach($roots as $r) render_area($r,$by,0); ?>
            <?php endif; ?>
          </div>
          <?php if(!$closed): ?>
          <div class="card-footer text-muted small">
            Para dejar un área como raíz, suéltala sobre el espacio en blanco del árbol y confirma.
          </div>
          <?php endif; ?>
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
if (location.hash==='#tab-attachments') {
  const btn = document.getElementById('tab-att-btn');
  if(btn) btn.click();
}

(function(){
  const closed = <?= $closed ? 'true' : 'false' ?>;
  if (closed) return;

  let draggingId = null;

  function postMove(areaId, newParentId){
    const fd = new FormData();
    fd.append('area_id', areaId);
    fd.append('new_parent_id', newParentId===null ? '' : newParentId);
    fd.append('project_id', '<?= (int)$project_id ?>');

    return fetch('index.php?page=area_move', {method:'POST', body: fd, credentials:'same-origin'})
      .then(r => r.json());
  }

  document.querySelectorAll('.area-node').forEach(node=>{
    node.addEventListener('dragstart', e=>{
      draggingId = node.getAttribute('data-area-id');
      node.classList.add('dragging');
      e.dataTransfer.effectAllowed='move';
    });
    node.addEventListener('dragend', ()=>{
      node.classList.remove('dragging');
      document.querySelectorAll('.area-node').forEach(n=>n.classList.remove('drop-target'));
      draggingId = null;
    });
    node.addEventListener('dragover', e=>{
      if(!draggingId) return;
      e.preventDefault();
      node.classList.add('drop-target');
    });
    node.addEventListener('dragleave', ()=> node.classList.remove('drop-target'));
    node.addEventListener('drop', async e=>{
      e.preventDefault();
      node.classList.remove('drop-target');
      const targetId = node.getAttribute('data-area-id');
      if(!draggingId || draggingId===targetId) return;
      if(!confirm('¿Mover el área #' + draggingId + ' para que sea hija de #' + targetId + '?')) return;

      try{
        const res = await postMove(draggingId, targetId);
        if(res && res.ok){ location.reload(); }
        else alert(res.error || 'No se pudo mover.');
      }catch(err){
        alert('Error moviendo: ' + err);
      }
    });
  });

  const treeCardBody = document.querySelector('#tab-areas .col-lg-7 .card-body');
  if(treeCardBody){
    treeCardBody.addEventListener('dragover', e=>{
      if(!draggingId) return;
      e.preventDefault();
    });
    treeCardBody.addEventListener('drop', async e=>{
      if(e.target.closest('.area-node')) return;
      if(!draggingId) return;
      if(!confirm('¿Mover el área #' + draggingId + ' al nivel raíz (sin padre)?')) return;
      try{
        const res = await postMove(draggingId, null);
        if(res && res.ok){ location.reload(); }
        else alert(res.error || 'No se pudo mover.');
      }catch(err){
        alert('Error moviendo: ' + err);
      }
    });
  }
})();
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
