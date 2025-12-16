<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attachments.php';
require_login();

$project_id = (int)get_param('id', 0);
$project = db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch();
if (!$project) die('Proyecto no encontrado');

$HAS_PARENT = (bool)db_query("SHOW COLUMNS FROM areas LIKE 'parent_area_id'")->fetch();
$HAS_DIVS = (bool)db_query("SHOW TABLES LIKE 'divisions'")->fetch();
$HAS_PDIV = (bool)db_query("SHOW TABLES LIKE 'project_divisions'")->fetch();

$divs = [];
if ($HAS_DIVS && $HAS_PDIV) {
  $divs = db_query("
    SELECT d.*
    FROM project_divisions pd
    JOIN divisions d ON d.id = pd.division_id
    WHERE pd.project_id=?
    ORDER BY d.name
  ", [$project_id])->fetchAll();
}

$areas = db_query("SELECT * FROM areas WHERE project_id=? ORDER BY name", [$project_id])->fetchAll();

$points = db_query("SELECT * FROM points WHERE project_id=? ORDER BY area_id, id", [$project_id])->fetchAll();
$pointsByArea = [];
foreach($points as $pt){
  $aid = (int)($pt['area_id'] ?? 0);
  if (!isset($pointsByArea[$aid])) $pointsByArea[$aid]=[];
  $pointsByArea[$aid][] = $pt;
}

$attachmentsProject = att_list('project', $project_id);

// helper: count points per area including subareas
$children = [];
if ($HAS_PARENT) {
  foreach($areas as $a){
    $pid = $a['parent_area_id'];
    $k = $pid===null ? 0 : (int)$pid;
    $children[$k][] = $a;
  }
}
function subtree_points_total($area_id, $children, $pointsByArea){
  $total = count($pointsByArea[$area_id] ?? []);
  foreach(($children[$area_id] ?? []) as $ch){
    $total += subtree_points_total((int)$ch['id'], $children, $pointsByArea);
  }
  return $total;
}
function render_area_report($area, $children, $pointsByArea, $level=0){
  $id = (int)$area['id'];
  $pad = $level*16;
  $total = subtree_points_total($id, $children, $pointsByArea);

  echo '<div class="border rounded p-2 mb-2" style="padding-left:'.($pad+12).'px">';
  echo '<div class="d-flex justify-content-between align-items-center">';
  echo '<div class="fw-semibold">'.h($area['name']).' <span class="badge text-bg-secondary ms-2">'.$total.' pts</span></div>';
  echo '<div class="d-flex gap-2">';
  echo '<a class="btn btn-sm btn-outline-secondary" href="index.php?page=area_detail&id='.$id.'"><i class="bi bi-box-arrow-in-right"></i></a>';
  echo '</div></div>';

  $pts = $pointsByArea[$id] ?? [];
  if ($pts){
    echo '<div class="table-responsive mt-2"><table class="table table-sm table-striped align-middle mb-0">';
    echo '<thead><tr><th style="width:90px;">Código</th><th>Nombre</th><th class="text-end">Módulos</th><th class="text-muted">Ubicación</th><th class="text-muted">Orientación</th></tr></thead><tbody>';
    foreach($pts as $p){
      echo '<tr>';
      echo '<td>'.h($p['code'] ?? $p['id']).'</td>';
      echo '<td><a href="index.php?page=point_detail&id='.h($p['id']).'">'.h($p['name'] ?? ('Punto '.$p['id'])).'</a></td>';
      echo '<td class="text-end">'.h($p['modules'] ?? '').'</td>';
      echo '<td class="text-muted">'.h($p['location'] ?? '').'</td>';
      echo '<td class="text-muted">'.h($p['orientation'] ?? '').'</td>';
      echo '</tr>';
    }
    echo '</tbody></table></div>';
  } else {
    echo '<div class="text-muted mt-2">Sin puntos directos en esta área.</div>';
  }

  echo '</div>';

  foreach(($children[$id] ?? []) as $ch){
    render_area_report($ch, $children, $pointsByArea, $level+1);
  }
}

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('Reporte: <?=h(addslashes($project["name"] ?? ""))?>', `
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=reports"><i class="bi bi-arrow-left"></i> Volver</a>
  <a class="btn btn-sm btn-outline-success" href="index.php?page=report_project_excel&id=<?=h($project_id)?>"><i class="bi bi-filetype-csv"></i> Excel</a>
  <a class="btn btn-sm btn-outline-danger" target="_blank" href="index.php?page=report_project_pdf&id=<?=h($project_id)?>"><i class="bi bi-filetype-pdf"></i> PDF</a>
`);
</script>

<h1 class="h4 mb-3"><i class="bi bi-file-earmark-text"></i> Informe del proyecto</h1>

<div class="card mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-6"><div class="text-muted small">Proyecto</div><div class="fw-semibold"><?=h($project['name'] ?? '')?></div></div>
      <div class="col-md-6"><div class="text-muted small">ID</div><div class="fw-semibold"><?=h($project_id)?></div></div>
    </div>

    <hr>

    <div class="fw-semibold mb-2"><i class="bi bi-diagram-3"></i> Divisiones</div>
    <?php if(!$divs): ?>
      <div class="text-muted">Sin divisiones asociadas (o no existe project_divisions).</div>
    <?php else: ?>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach($divs as $d): ?>
          <span class="badge text-bg-light border"><?=h($d['name'] ?? '')?><?= !empty($d['prefix']) ? ' — '.h($d['prefix']) : '' ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <hr>

    <div class="fw-semibold mb-2"><i class="bi bi-paperclip"></i> Adjuntos del proyecto</div>
    <?php if(!$attachmentsProject): ?>
      <div class="text-muted">Sin adjuntos.</div>
    <?php else: ?>
      <ul class="mb-0">
        <?php foreach($attachmentsProject as $a): ?>
          <li><a href="<?=h($a['stored_path'])?>" target="_blank"><?=h($a['original_name'])?></a> <span class="text-muted small"><?=h($a['created_at'] ?? '')?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="fw-semibold mb-2"><i class="bi bi-layers"></i> Áreas y puntos</div>
<?php
if (!$areas) {
  echo '<div class="text-muted">No hay áreas en este proyecto.</div>';
} else {
  if ($HAS_PARENT) {
    foreach(($children[0] ?? []) as $root){
      render_area_report($root, $children, $pointsByArea, 0);
    }
  } else {
    // sin jerarquía
    foreach($areas as $a){
      render_area_report($a, [], $pointsByArea, 0);
    }
  }
}
?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
