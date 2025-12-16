
<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$project_id = (int) get_param('id', 0);
$project = db_query("SELECT p.*, b.name AS brand_name, s.name AS series_name
                     FROM projects p
                     LEFT JOIN brands b ON b.id = p.default_brand_id
                     LEFT JOIN series s ON s.id = p.default_series_id
                     WHERE p.id = ?", [$project_id])->fetch();
if (!$project) die('Proyecto no encontrado');

$brands = db_query("SELECT * FROM brands ORDER BY name")->fetchAll();
$series = db_query("SELECT s.*, b.name AS brand_name FROM series s JOIN brands b ON b.id = s.brand_id ORDER BY b.name, s.name")->fetchAll();

// actualizar defaults
if (is_post() && post_param('action') === 'update_defaults') {
    $default_brand_id = post_param('default_brand_id') ? (int) post_param('default_brand_id') : null;
    $default_series_id = post_param('default_series_id') ? (int) post_param('default_series_id') : null;
    db_query("UPDATE projects SET default_brand_id=?, default_series_id=? WHERE id=?",
             [$default_brand_id, $default_series_id, $project_id]);
    redirect('index.php?page=project_detail&id=' . $project_id);
}

// Crear área
if (is_post() && post_param('action') === 'create_area') {
    $name = trim(post_param('name'));
    if ($name !== '') {
        db_query("INSERT INTO areas (project_id, name) VALUES (?,?)", [$project_id, $name]);
        redirect('index.php?page=project_detail&id=' . $project_id);
    }
}

// plantillas
$templates = db_query("SELECT * FROM point_templates ORDER BY name")->fetchAll();

// Crear punto sin área
if (is_post() && post_param('action') === 'create_point_no_area') {
    $code = trim(post_param('code'));
    $desc = trim(post_param('description'));
    $system_type = post_param('system_type', 'electrico');
    $modules = (int) post_param('modules', 2);
    $orientation = post_param('orientation', 'H');
    $quantity = (int) post_param('quantity', 1);
    $template_id = (int) post_param('template_id', 0);

    if ($code !== '') {
        db_query("INSERT INTO points (project_id, area_id, code, description, system_type, modules, orientation, quantity)
                  VALUES (?,?,?,?,?,?,?,?)",
                 [$project_id, null, $code, $desc, $system_type, $modules, $orientation, $quantity]);
        $new_point_id = db_connect()->lastInsertId();

        if ($template_id) {
            $tpl = db_query("SELECT * FROM point_templates WHERE id = ?", [$template_id])->fetch();
            if ($tpl) {
                db_query("UPDATE points SET system_type=?, brand_id=?, series_id=?, modules=?, orientation=?, support_article_id=?, plate_article_id=?
                          WHERE id=?",
                          [$tpl['system_type'], $tpl['brand_id'], $tpl['series_id'], $tpl['modules'], $tpl['orientation'],
                           $tpl['support_article_id'], $tpl['plate_article_id'], $new_point_id]);

                $tpl_comps = db_query("SELECT * FROM point_template_components WHERE template_id = ? ORDER BY position", [$template_id])->fetchAll();
                foreach ($tpl_comps as $tc) {
                    db_query("INSERT INTO point_components (point_id, article_id, position) VALUES (?,?,?)",
                             [$new_point_id, $tc['article_id'], $tc['position']]);
                }
            }
        }
        redirect('index.php?page=project_detail&id=' . $project_id);
    }
}

$areas = db_query("SELECT * FROM areas WHERE project_id = ? ORDER BY id ASC", [$project_id])->fetchAll();
$points_no_area = db_query("SELECT * FROM points WHERE project_id = ? AND area_id IS NULL ORDER BY id ASC", [$project_id])->fetchAll();

include __DIR__ . '/../layout/header.php';
?>
<h1><i class="bi bi-folder2-open"></i> Proyecto: <?= h($project['name']) ?></h1>
<p class="text-muted mb-1"><?= nl2br(h($project['description'])) ?></p>
<p class="text-muted">
  Cliente: <?= h($project['client']) ?> — Dirección: <?= h($project['address']) ?>
</p>

<div class="card mb-4">
  <div class="card-header">Marca / serie por defecto del proyecto</div>
  <div class="card-body">
    <form method="post" class="row g-3">
      <input type="hidden" name="action" value="update_defaults">
      <div class="col-md-6">
        <label class="form-label">Marca</label>
        <select name="default_brand_id" class="form-select">
          <option value="">(ninguna)</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?= h($b['id']) ?>" <?= $project['default_brand_id']==$b['id']?'selected':'' ?>>
              <?= h($b['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Serie</label>
        <select name="default_series_id" class="form-select">
          <option value="">(ninguna)</option>
          <?php foreach ($series as $s): ?>
            <option value="<?= h($s['id']) ?>" <?= $project['default_series_id']==$s['id']?'selected':'' ?>>
              [<?= h($s['brand_name']) ?>] <?= h($s['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<div class="row">
  <div class="col-md-6">
    <h2 class="h4"><i class="bi bi-grid-3x3-gap"></i> Áreas</h2>
    <button class="btn btn-sm btn-primary mb-3" data-bs-toggle="collapse" data-bs-target="#newArea">
      <i class="bi bi-plus-circle"></i> Nueva área
    </button>
    <div id="newArea" class="collapse mb-3">
      <div class="card card-body">
        <form method="post">
          <input type="hidden" name="action" value="create_area">
          <div class="mb-3">
            <label class="form-label">Nombre del área</label>
            <input type="text" name="name" class="form-control" placeholder="Dormitorio principal, Cocina..." required>
          </div>
          <button class="btn btn-success"><i class="bi bi-check-lg"></i> Guardar área</button>
        </form>
      </div>
    </div>
    <ul class="list-group mb-4">
      <?php foreach ($areas as $a): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <span><i class="bi bi-door-open"></i> <?= h($a['name']) ?></span>
          <span class="btn-group btn-group-sm">
            <a href="index.php?page=area_detail&id=<?= h($a['id']) ?>" class="btn btn-outline-primary">
              <i class="bi bi-list-task"></i> Puntos
            </a>
            <a href="index.php?page=area_duplicate&id=<?= h($a['id']) ?>" class="btn btn-outline-secondary" title="Duplicar área">
              <i class="bi bi-files"></i>
            </a>
          </span>
        </li>
      <?php endforeach; ?>
      <?php if (!$areas): ?>
        <li class="list-group-item text-muted">Sin áreas aún.</li>
      <?php endif; ?>
    </ul>
  </div>

  <div class="col-md-6">
    <h2 class="h4"><i class="bi bi-pin-map"></i> Puntos rápidos (sin área)</h2>
    <button class="btn btn-sm btn-primary mb-3" data-bs-toggle="collapse" data-bs-target="#newPointNoArea">
      <i class="bi bi-plus-circle"></i> Nuevo punto rápido
    </button>
    <div id="newPointNoArea" class="collapse mb-3">
      <div class="card card-body">
        <form method="post">
          <input type="hidden" name="action" value="create_point_no_area">
          <div class="mb-3">
            <label class="form-label">Código</label>
            <input type="text" name="code" class="form-control" placeholder="P01, S1, etc." required>
          </div>
          <div class="mb-3">
            <label class="form-label">Descripción</label>
            <input type="text" name="description" class="form-control" placeholder="Interruptor + conmutador 3way...">
          </div>
          <div class="mb-3">
            <label class="form-label">Plantilla (opcional)</label>
            <select name="template_id" class="form-select">
              <option value="">(ninguna)</option>
              <?php foreach ($templates as $t): ?>
                <option value="<?= h($t['id']) ?>">
                  <?= h($t['name']) ?> (<?= h($t['modules']) ?>M)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Si eliges una plantilla, se usarán su soporte, placa y frutos.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Tipo de sistema</label>
            <select name="system_type" class="form-select">
              <option value="electrico">Eléctrico</option>
              <option value="datos">Datos</option>
              <option value="wifi">WiFi</option>
              <option value="persianas">Persianas</option>
              <option value="otros">Otros</option>
            </select>
          </div>
          <div class="row mb-3">
            <div class="col">
              <label class="form-label">Módulos</label>
              <select name="modules" class="form-select">
                <option value="1">1</option>
                <option value="2" selected>2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="7">7</option>
              </select>
            </div>
            <div class="col">
              <label class="form-label">Orientación</label>
              <select name="orientation" class="form-select">
                <option value="H" selected>Horizontal</option>
                <option value="V">Vertical</option>
                <option value="N">N/A</option>
              </select>
            </div>
            <div class="col">
              <label class="form-label">Cantidad</label>
              <input type="number" name="quantity" class="form-control" value="1" min="1">
            </div>
          </div>
          <button class="btn btn-success"><i class="bi bi-check-lg"></i> Guardar punto rápido</button>
        </form>
      </div>
    </div>
    <table class="table table-sm table-striped align-middle">
      <thead>
        <tr><th>Código</th><th>Sistema</th><th>Mód.</th><th>Cant.</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($points_no_area as $pt): ?>
        <tr>
          <td><?= h($pt['code']) ?></td>
          <td><?= h($pt['system_type']) ?></td>
          <td><?= h($pt['modules']) ?></td>
          <td><?= h($pt['quantity']) ?></td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <a href="index.php?page=point_edit&id=<?= h($pt['id']) ?>" class="btn btn-outline-primary" title="Editar">
                <i class="bi bi-pencil-square"></i>
              </a>
              <a href="index.php?page=point_duplicate&id=<?= h($pt['id']) ?>" class="btn btn-outline-secondary" title="Duplicar">
                <i class="bi bi-files"></i>
              </a>
              <a href="index.php?page=point_move&id=<?= h($pt['id']) ?>" class="btn btn-outline-warning" title="Asignar a área">
                <i class="bi bi-arrow-left-right"></i>
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$points_no_area): ?>
        <tr><td colspan="5" class="text-muted">Sin puntos rápidos aún.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
