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

$area_id = (int)get_param('id', 0);
$area = db_query("SELECT * FROM areas WHERE id=?", [$area_id])->fetch();
if (!$area) die('Área no encontrada');

$project_id = (int)($area['project_id'] ?? 0);
$project = $project_id ? db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch() : null;

$HAS_CLOSED = $project ? col_exists('projects','is_closed') : false;
$closed = ($HAS_CLOSED && $project) ? ((int)($project['is_closed'] ?? 0)===1) : false;

$fileTypes = table_exists('file_types') ? db_query("SELECT id,name FROM file_types ORDER BY name")->fetchAll() : [];

if (is_post()) {
  if ($closed) { add_flash('error','Proyecto cerrado: no se permiten cambios.'); header('Location: index.php?page=area_detail&id='.$area_id); exit; }
  $action = post_param('action','');

  if ($action==='upload_attachment') {
    try {
      $ft = post_param('file_type_id') !== '' ? (int)post_param('file_type_id') : null;
      att_upload('area',$area_id,$_FILES['file'] ?? [],$ft);
      add_flash('success','Adjunto cargado.');
    } catch (Exception $e) {
      add_flash('error','Error adjunto: '.$e->getMessage());
    }
    header('Location: index.php?page=area_detail&id='.$area_id.'#tab-attachments'); exit;
  }

  if ($action==='delete_attachment') {
    att_delete((int)post_param('att_id'));
    add_flash('success','Adjunto eliminado.');
    header('Location: index.php?page=area_detail&id='.$area_id.'#tab-attachments'); exit;
  }
}

$points = table_exists('points') ? db_query("
  SELECT p.*
  FROM points p
  WHERE p.area_id=?
  ORDER BY p.id DESC
", [$area_id])->fetchAll() : [];

$attachments = att_list('area',$area_id);

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('Área: <?=h(addslashes($area['name'] ?? ''))?>', `
  <?php if($project_id): ?>
    <a class="btn btn-sm btn-outline-secondary" href="index.php?page=project_detail&id=<?=h($project_id)?>#tab-areas"><i class="bi bi-arrow-left"></i> Volver</a>
  <?php else: ?>
    <a class="btn btn-sm btn-outline-secondary" href="index.php?page=projects"><i class="bi bi-arrow-left"></i> Volver</a>
  <?php endif; ?>
  <a class="btn btn-sm btn-outline-primary" href="index.php?page=point_detail&area_id=<?=h($area_id)?>"><i class="bi bi-plus-circle"></i> Agregar punto</a>
  <a class="btn btn-sm btn-outline-info" href="#tab-attachments" onclick="document.getElementById('tab-att-btn').click()"><i class="bi bi-paperclip"></i> Adjuntos</a>
`);
</script>

<?php if($closed): ?>
  <div class="alert alert-warning"><i class="bi bi-lock"></i> Proyecto cerrado: no se permiten cambios.</div>
<?php endif; ?>

<h1 class="h4 mb-2"><?=h($area['name'] ?? '')?></h1>
<?php if($project): ?>
  <div class="text-muted mb-3">Proyecto: <a href="index.php?page=project_detail&id=<?=h($project_id)?>"><?=h($project['name'] ?? '')?></a></div>
<?php endif; ?>

<ul class="nav nav-tabs">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-points" id="tab-points-btn">Puntos</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-attachments" id="tab-att-btn">Adjuntos</button></li>
</ul>

<div class="tab-content border border-top-0 p-3">

  <div class="tab-pane fade show active" id="tab-points">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="text-muted small">Listado de puntos del área</div>
      <a class="btn btn-sm btn-primary" href="index.php?page=point_detail&area_id=<?=h($area_id)?>" <?= $closed?'disabled':'' ?>><i class="bi bi-plus-circle"></i> Nuevo punto</a>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th class="text-muted">División</th>
            <th class="text-end" style="width:160px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($points as $p): ?>
            <tr>
              <td class="fw-semibold"><?=h($p['code'] ?? $p['id'])?></td>
              <td><?=h($p['name'] ?? $p['notes'] ?? '')?></td>
              <td class="text-muted small"><?=h($p['division_id'] ?? '')?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" title="Editar" href="index.php?page=point_detail&id=<?=h($p['id'])?>"><i class="bi bi-pencil"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$points): ?>
            <tr><td colspan="4" class="text-muted text-center py-3">No hay puntos en esta área.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
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
if (location.hash==='#tab-attachments') { const btn=document.getElementById('tab-att-btn'); if(btn) btn.click(); }
if (location.hash==='#tab-points') { const btn=document.getElementById('tab-points-btn'); if(btn) btn.click(); }
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
