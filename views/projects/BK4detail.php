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

  echo '<div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2" style="padding-left:'.($pad+10).'px">';
  echo '<div class="d-flex align-items-center gap-2">';
  if ($hasKids) echo '<button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#'.$cid.'"><i class="bi bi-chevron-down"></i></button>';
  else echo '<span style="width:34px;display:inline-block"></span>';
  echo '<a class="text-decoration-none fw-semibold" href="index.php?page=area_detail&id='.$id.'">'.h($area['name']).'</a>';
  echo '<span class="badge text-bg-secondary">'.$pts.' pts</span>';
  echo '</div>';
  echo '<div><a class="btn btn-sm btn-outline-primary" href="index.php?page=area_detail&id='.$id.'"><i class="bi bi-box-arrow-in-right"></i></a></div>';
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
    if ($name==='') { add_flash('error','Nombre requerido'); redirect('index.php?page=project_detail&id='.$project_id); }
    db_query("INSERT INTO areas(project_id,parent_area_id,name,created_at) VALUES (?,?,?,NOW())", [$project_id, $parent, $name]);
    add_flash('success','Área creada.');
    redirect('index.php?page=project_detail&id='.$project_id);
  }

  if ($action === 'upload_attachment') {
    try {
      $ft = post_param('file_type_id') !== '' ? (int)post_param('file_type_id') : null;
      att_upload('project',$project_id,$_FILES['file'] ?? [],$ft);
      add_flash('success','Adjunto cargado.');
    } catch (Exception $e) {
      add_flash('error','Error adjunto: '.$e->getMessage());
    }
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-attachments');
  }

  if ($action === 'delete_attachment') {
    att_delete((int)post_param('att_id'));
    add_flash('success','Adjunto eliminado.');
    redirect('index.php?page=project_detail&id='.$project_id.'#tab-attachments');
  }
}

$allAreas = db_query("SELECT id,name,parent_area_id FROM areas WHERE project_id=? ORDER BY name", [$project_id])->fetchAll();
$by = $HAS_PARENT ? areas_tree($project_id) : [0=>$allAreas];
$roots = $HAS_PARENT ? ($by[0] ?? []) : $allAreas;

$attachments = att_list('project',$project_id);
$fileTypes = table_exists('file_types') ? db_query("SELECT id,name FROM file_types ORDER BY name")->fetchAll() : [];

include __DIR__ . '/../layout/header.php';
?>
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
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card">
          <div class="card-header d-flex justify-content-between">
            <span>Áreas del proyecto</span>
            <span class="text-muted small">Colapsar/expandir</span>
          </div>
          <div class="card-body">
            <?php if(!$roots): ?>
              <div class="text-muted">No hay áreas.</div>
            <?php else: ?>
              <?php foreach($roots as $r) render_area($r,$by,0); ?>
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
if (location.hash==='#tab-attachments') {
  const btn = document.getElementById('tab-att-btn');
  if(btn) btn.click();
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
