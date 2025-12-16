<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$project_id = (int) get_param('id', 0);
if ($project_id <= 0) {
  set_flash('error', 'Proyecto inválido.');
  redirect('index.php?page=projects');
}

// Helpers: existe tabla/columna
function table_exists($name) {
  return (bool) db_query("SHOW TABLES LIKE ?", [$name])->fetch();
}
function column_exists($table, $col) {
  return (bool) db_query("SHOW COLUMNS FROM `$table` LIKE ?", [$col])->fetch();
}

$hasDivisions        = table_exists('divisions');
$hasProjectDivisions = table_exists('project_divisions');
$hasAreaDivisions    = table_exists('area_divisions');
$hasAreas            = table_exists('areas');
$hasPoints           = table_exists('points');

$hasPointsAreaId     = $hasPoints ? column_exists('points','area_id') : false;
$hasPointsDivisionId = $hasPoints ? column_exists('points','division_id') : false;

// Cargar proyecto
$project = db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch();
if (!$project) {
  set_flash('error', 'Proyecto no encontrado.');
  redirect('index.php?page=projects');
}

// ======= POST actions =======
if (is_post() && current_user_role() !== 'viewer') {
  $action = post_param('action');

  // Crear área
  if ($action === 'add_area') {
    if (!$hasAreas) {
      set_flash('error', 'La tabla areas no existe en la BD.');
      redirect('index.php?page=project_detail&id=' . $project_id);
    }

    $name = trim(post_param('name'));
    if ($name === '') {
      set_flash('error', 'El nombre del área es obligatorio.');
      redirect('index.php?page=project_detail&id=' . $project_id);
    }

    db_query("INSERT INTO areas (project_id, name) VALUES (?,?)", [$project_id, $name]);
    user_log('area_create', 'area', (int)db_connect()->lastInsertId(), "project=$project_id name=$name");
    set_flash('success', 'Área creada.');
    redirect('index.php?page=project_detail&id=' . $project_id);
  }

  // Editar nombre de área
  if ($action === 'rename_area') {
    $area_id = (int) post_param('area_id', 0);
    $name = trim(post_param('name'));

    if ($area_id <= 0 || $name === '') {
      set_flash('error', 'Área o nombre inválido.');
      redirect('index.php?page=project_detail&id=' . $project_id);
    }

    // Validar que el área sea del proyecto
    $chk = db_query("SELECT id FROM areas WHERE id=? AND project_id=?", [$area_id, $project_id])->fetch();
    if (!$chk) {
      set_flash('error', 'Área inválida para este proyecto.');
      redirect('index.php?page=project_detail&id=' . $project_id);
    }

    db_query("UPDATE areas SET name=? WHERE id=?", [$name, $area_id]);
    user_log('area_rename', 'area', $area_id, "name=$name");
    set_flash('success', 'Nombre del área actualizado.');
    redirect('index.php?page=project_detail&id=' . $project_id);
  }

  // Eliminar área (solo si está vacía de puntos)
  if ($action === 'delete_area') {
    $area_id = (int) post_param('area_id', 0);

    // Validar que el área sea del proyecto
    $chk = db_query("SELECT id,name FROM areas WHERE id=? AND project_id=?", [$area_id, $project_id])->fetch();
    if (!$chk) {
      set_flash('error', 'Área inválida para este proyecto.');
      redirect('index.php?page=project_detail&id=' . $project_id);
    }

    // Si existe points.area_id, bloquear si tiene puntos
    if ($hasPointsAreaId) {
      $cntRow = db_query("SELECT COUNT(*) AS c FROM points WHERE area_id=?", [$area_id])->fetch();
      $cnt = (int)($cntRow['c'] ?? 0);
      if ($cnt > 0) {
        set_flash('error', "No se puede eliminar: el área tiene $cnt punto(s).");
        redirect('index.php?page=project_detail&id=' . $project_id);
      }
    }

    db_query("DELETE FROM areas WHERE id=?", [$area_id]);
    user_log('area_delete', 'area', $area_id, "name=".$chk['name']);
    set_flash('success', 'Área eliminada.');
    redirect('index.php?page=project_detail&id=' . $project_id);
  }

  // Guardar divisiones del proyecto (checkbox)
  if ($action === 'save_project_divisions') {
    if (!$hasDivisions || !$hasProjectDivisions) {
      set_flash('error', 'Faltan tablas divisions o project_divisions en la BD.');
      redirect('index.php?page=project_detail&id=' . $project_id);
    }

    $divIds = post_param('division_ids', []);
    if (!is_array($divIds)) $divIds = [];

    db_query("DELETE FROM project_divisions WHERE project_id=?", [$project_id]);
    foreach ($divIds as $did) {
      $did = (int)$did;
      if ($did > 0) {
        db_query("INSERT INTO project_divisions (project_id, division_id) VALUES (?,?)", [$project_id, $did]);
      }
    }

    user_log('project_divisions_set', 'project', $project_id, 'divisions='.implode(',', $divIds));
    set_flash('success', 'Divisiones del proyecto actualizadas.');
    redirect('index.php?page=project_detail&id=' . $project_id);
  }

  // Guardar divisiones de un área (multi-select)
  if ($action === 'save_area_divisions') {
    if (!$hasDivisions || !$hasAreaDivisions) {
      set_flash('error', 'Faltan tablas divisions o area_divisions en la BD.');
      redirect('index.php?page=project_detail&id=' . $project_id);
    }

    $area_id = (int) post_param('area_id', 0);
    $divIds  = post_param('division_ids', []);
    if (!is_array($divIds)) $divIds = [];

    // Validar que el área sea del proyecto
    $chk = db_query("SELECT id FROM areas WHERE id=? AND project_id=?", [$area_id, $project_id])->fetch();
    if (!$chk) {
      set_flash('error', 'Área inválida para este proyecto.');
      redirect('index.php?page=project_detail&id=' . $project_id);
    }

    db_query("DELETE FROM area_divisions WHERE area_id=?", [$area_id]);
    foreach ($divIds as $did) {
      $did = (int)$did;
      if ($did > 0) {
        db_query("INSERT INTO area_divisions (area_id, division_id) VALUES (?,?)", [$area_id, $did]);
      }
    }

    user_log('area_divisions_set', 'area', $area_id, 'divisions='.implode(',', $divIds));
    set_flash('success', 'Divisiones del área actualizadas.');
    redirect('index.php?page=project_detail&id=' . $project_id);
  }
}

// ======= Data =======

// Divisiones
$divisions = [];
$hasPrefix = false;
if ($hasDivisions) {
  $hasPrefix = column_exists('divisions', 'code_prefix');
  $divisions = db_query("SELECT * FROM divisions ORDER BY name")->fetchAll();
}

// Divisiones del proyecto
$projectDivisionIds = [];
if ($hasProjectDivisions) {
  $rows = db_query("SELECT division_id FROM project_divisions WHERE project_id=?", [$project_id])->fetchAll();
  foreach ($rows as $r) $projectDivisionIds[] = (int)$r['division_id'];
}

// Áreas del proyecto (con contador de puntos si aplica)
$areas = [];
if ($hasAreas) {
  if ($hasPointsAreaId) {
    $areas = db_query("
      SELECT a.*,
        (SELECT COUNT(*) FROM points p WHERE p.area_id=a.id) AS points_count
      FROM areas a
      WHERE a.project_id=?
      ORDER BY a.name
    ", [$project_id])->fetchAll();
  } else {
    $areas = db_query("SELECT a.* FROM areas a WHERE a.project_id=? ORDER BY a.name", [$project_id])->fetchAll();
    foreach ($areas as &$a) $a['points_count'] = null;
    unset($a);
  }
}

// Divisiones por área
$areaDivMap = []; // area_id => [division_id...]
if ($hasAreaDivisions && $hasAreas) {
  $rows = db_query("
    SELECT a.id AS area_id, ad.division_id
    FROM areas a
    LEFT JOIN area_divisions ad ON ad.area_id=a.id
    WHERE a.project_id=?
  ", [$project_id])->fetchAll();

  foreach ($rows as $r) {
    $aid = (int)$r['area_id'];
    if (!isset($areaDivMap[$aid])) $areaDivMap[$aid] = [];
    if (!empty($r['division_id'])) $areaDivMap[$aid][] = (int)$r['division_id'];
  }
}

// Para los 2 reportes (Área->Divisiones y División->Áreas)
$areasMap = [];
$divsMap  = [];

// A) Área -> Divisiones
if ($hasAreas && $hasAreaDivisions && $hasDivisions) {
  $areaRows = db_query("
    SELECT a.id AS area_id, a.name AS area_name,
           d.id AS division_id, d.name AS division_name,
           " . ($hasPrefix ? "d.code_prefix" : "'' AS code_prefix") . "
    FROM areas a
    LEFT JOIN area_divisions ad ON ad.area_id=a.id
    LEFT JOIN divisions d ON d.id=ad.division_id
    WHERE a.project_id=?
    ORDER BY a.name, d.name
  ", [$project_id])->fetchAll();

  foreach ($areaRows as $r) {
    $aid = (int)$r['area_id'];
    if (!isset($areasMap[$aid])) {
      $areasMap[$aid] = ['name' => $r['area_name'], 'divs' => []];
    }
    if (!empty($r['division_id'])) {
      $areasMap[$aid]['divs'][] = [
        'id'     => (int)$r['division_id'],
        'name'   => $r['division_name'],
        'prefix' => $r['code_prefix'] ?? '',
      ];
    }
  }
} else {
  foreach ($areas as $a) {
    $aid = (int)$a['id'];
    $areasMap[$aid] = ['name'=>$a['name'], 'divs'=>[]];
  }
}

// B) División -> Áreas
if ($hasAreas && $hasAreaDivisions && $hasDivisions && $hasProjectDivisions) {
  $divRows = db_query("
    SELECT d.id AS division_id, d.name AS division_name,
           " . ($hasPrefix ? "d.code_prefix" : "'' AS code_prefix") . ",
           a.id AS area_id, a.name AS area_name
    FROM divisions d
    JOIN project_divisions pd ON pd.division_id=d.id AND pd.project_id=?
    LEFT JOIN area_divisions ad ON ad.division_id=d.id
    LEFT JOIN areas a ON a.id=ad.area_id AND a.project_id=?
    ORDER BY d.name, a.name
  ", [$project_id, $project_id])->fetchAll();

  foreach ($divRows as $r) {
    $did = (int)$r['division_id'];
    if (!isset($divsMap[$did])) {
      $divsMap[$did] = ['name'=>$r['division_name'], 'prefix'=>$r['code_prefix'] ?? '', 'areas'=>[]];
    }
    if (!empty($r['area_id'])) {
      $divsMap[$did]['areas'][] = ['id'=>(int)$r['area_id'], 'name'=>$r['area_name']];
    }
  }
} else {
  if ($hasDivisions) {
    foreach ($divisions as $d) {
      $did = (int)$d['id'];
      $divsMap[$did] = ['name'=>$d['name'], 'prefix'=>$hasPrefix?($d['code_prefix']??''):'', 'areas'=>[]];
    }
  }
}

include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h1 class="h3 mb-0">
    <i class="bi bi-folder2-open"></i> <?= h($project['name']) ?>
  </h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="index.php?page=projects">
      <i class="bi bi-arrow-left"></i> Volver
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">Resumen</div>
      <div class="card-body">
        <div><strong>Proyecto:</strong> <?= h($project['name']) ?></div>
        <?php if (!empty($project['client'])): ?><div><strong>Cliente:</strong> <?= h($project['client']) ?></div><?php endif; ?>
        <?php if (!empty($project['address'])): ?><div><strong>Dirección:</strong> <?= h($project['address']) ?></div><?php endif; ?>
        <?php if (!empty($project['created_at'])): ?><div class="text-muted small mt-2">Creado: <?= h($project['created_at']) ?></div><?php endif; ?>
      </div>
    </div>

    <?php if (!$hasDivisions || !$hasProjectDivisions): ?>
      <div class="alert alert-warning">
        <strong>Faltan tablas para Divisiones del proyecto.</strong><br>
        Necesitas <code>divisions</code> y <code>project_divisions</code>.
      </div>
    <?php else: ?>
      <div class="card mb-3">
        <div class="card-header">Divisiones del proyecto</div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="action" value="save_project_divisions">
            <?php foreach ($divisions as $d): ?>
              <?php
                $did = (int)$d['id'];
                $checked = in_array($did, $projectDivisionIds, true);
              ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="division_ids[]"
                       value="<?= h($did) ?>" <?= $checked ? 'checked' : '' ?>>
                <label class="form-check-label">
                  <?= h($d['name']) ?>
                  <?php if ($hasPrefix && !empty($d['code_prefix'])): ?>
                    <span class="badge text-bg-dark ms-1"><?= h($d['code_prefix']) ?></span>
                  <?php endif; ?>
                </label>
              </div>
            <?php endforeach; ?>
            <div class="d-flex justify-content-end mt-3">
              <button class="btn btn-primary btn-sm">
                <i class="bi bi-save"></i> Guardar
              </button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <?php if (current_user_role() !== 'viewer'): ?>
    <div class="card mb-3">
      <div class="card-header">Nueva área</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="add_area">
          <div class="col-12">
            <label class="form-label">Nombre</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-12 d-flex justify-content-end mt-2">
            <button class="btn btn-success btn-sm">
              <i class="bi bi-plus-circle"></i> Crear
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header">Áreas</div>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Área</th>
              <th style="width: 90px;">Puntos</th>
              <th>Divisiones del área</th>
              <th style="width: 330px;">Editar divisiones</th>
              <th class="text-end" style="width: 90px;">Abrir</th>
              <th class="text-end" style="width: 110px;">Eliminar</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($areas as $a): ?>
            <?php
              $aid = (int)$a['id'];
              $selectedDivs = $areaDivMap[$aid] ?? [];

              // Solo permitir escoger divisiones que estén activas en el proyecto
              $allowedDivs = $projectDivisionIds ?: array_map(fn($d)=> (int)$d['id'], $divisions);
            ?>
            <tr>
              <td>
                <?php if (current_user_role() === 'viewer'): ?>
                  <strong><?= h($a['name']) ?></strong>
                <?php else: ?>
                  <form method="post" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="action" value="rename_area">
                    <input type="hidden" name="area_id" value="<?= h($aid) ?>">
                    <input type="text" name="name" value="<?= h($a['name']) ?>"
                           class="form-control form-control-sm" style="max-width:280px;">
                    <button class="btn btn-sm btn-outline-primary" title="Guardar nombre">
                      <i class="bi bi-save"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($a['points_count'] !== null): ?>
                  <span class="badge text-bg-secondary"><?= h($a['points_count']) ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if (!$hasDivisions || !$hasAreaDivisions): ?>
                  <span class="text-muted">—</span>
                <?php else: ?>
                  <?php
                    $badges = [];
                    foreach ($divisions as $d) {
                      $did = (int)$d['id'];
                      if (in_array($did, $selectedDivs, true)) {
                        $txt = $d['name'];
                        if ($hasPrefix && !empty($d['code_prefix'])) $txt .= ' ('.$d['code_prefix'].')';
                        $badges[] = $txt;
                      }
                    }
                  ?>
                  <?php if (!$badges): ?>
                    <span class="text-muted">—</span>
                  <?php else: ?>
                    <?php foreach ($badges as $txt): ?>
                      <span class="badge text-bg-secondary me-1"><?= h($txt) ?></span>
                    <?php endforeach; ?>
                  <?php endif; ?>
                <?php endif; ?>
              </td>

              <td>
                <?php if (!$hasDivisions || !$hasAreaDivisions): ?>
                  <span class="text-muted">Falta area_divisions/divisions</span>
                <?php else: ?>
                  <form method="post" class="d-flex gap-2">
                    <input type="hidden" name="action" value="save_area_divisions">
                    <input type="hidden" name="area_id" value="<?= h($aid) ?>">
                    <select name="division_ids[]" class="form-select form-select-sm" multiple style="min-width:180px;">
                      <?php foreach ($divisions as $d): ?>
                        <?php
                          $did = (int)$d['id'];
                          if (!in_array($did, $allowedDivs, true)) continue; // filtra por divisiones del proyecto
                          $sel = in_array($did, $selectedDivs, true);
                          $lbl = $d['name'];
                          if ($hasPrefix && !empty($d['code_prefix'])) $lbl .= ' ('.$d['code_prefix'].')';
                        ?>
                        <option value="<?= h($did) ?>" <?= $sel ? 'selected' : '' ?>><?= h($lbl) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-primary" title="Guardar divisiones del área">
                      <i class="bi bi-save"></i>
                    </button>
                  </form>
                  <div class="small text-muted mt-1">Ctrl/⌘ para seleccionar múltiples</div>
                <?php endif; ?>
              </td>

              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="index.php?page=area_detail&id=<?= h($aid) ?>">
                  <i class="bi bi-box-arrow-up-right"></i>
                </a>
              </td>

              <td class="text-end">
                <?php if (current_user_role() === 'viewer'): ?>
                  <span class="text-muted">—</span>
                <?php else: ?>
                  <form method="post"
                        onsubmit="return confirm('¿Eliminar el área <?= h($a['name']) ?>?\\n\\nEsto NO se puede deshacer.') && confirm('CONFIRMACIÓN FINAL: ¿Seguro que deseas eliminarla?');">
                    <input type="hidden" name="action" value="delete_area">
                    <input type="hidden" name="area_id" value="<?= h($aid) ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Eliminar área">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (!$areas): ?>
            <tr><td colspan="6" class="text-muted text-center py-3">No hay áreas.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Reportes solicitados -->
    <div class="card mb-3">
      <div class="card-header">Áreas y Divisiones participantes</div>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead><tr><th>Área</th><th>Divisiones</th></tr></thead>
          <tbody>
            <?php foreach ($areasMap as $a): ?>
              <tr>
                <td style="width:240px"><strong><?= h($a['name']) ?></strong></td>
                <td>
                  <?php if (!$a['divs']): ?>
                    <span class="text-muted">—</span>
                  <?php else: ?>
                    <?php foreach ($a['divs'] as $d): ?>
                      <span class="badge text-bg-secondary me-1">
                        <?= h($d['name']) ?><?= !empty($d['prefix']) ? ' ('.h($d['prefix']).')' : '' ?>
                      </span>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Divisiones y Áreas donde están involucradas</div>
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead><tr><th>División</th><th>Áreas</th></tr></thead>
          <tbody>
            <?php foreach ($divsMap as $d): ?>
              <tr>
                <td style="width:260px">
                  <strong><?= h($d['name']) ?></strong>
                  <?php if (!empty($d['prefix'])): ?>
                    <span class="badge text-bg-dark ms-2"><?= h($d['prefix']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!$d['areas']): ?>
                    <span class="text-muted">—</span>
                  <?php else: ?>
                    <?php foreach ($d['areas'] as $a): ?>
                      <span class="badge text-bg-light border me-1"><?= h($a['name']) ?></span>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$divsMap): ?>
              <tr><td colspan="2" class="text-muted text-center py-3">No hay divisiones asociadas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
