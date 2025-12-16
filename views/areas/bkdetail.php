<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$area_id = (int) get_param('id', 0);
$area = db_query("SELECT a.*, p.name AS project_name, p.id AS project_id 
                  FROM areas a 
                  JOIN projects p ON p.id = a.project_id 
                  WHERE a.id = ?", [$area_id])->fetch();
if (!$area) die('Área no encontrada');

$project_id = $area['project_id'];

$templates = db_query("SELECT * FROM point_templates ORDER BY name")->fetchAll();

// Crear punto en área
if (is_post() && post_param('action') === 'create_point') {
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
                 [$project_id, $area_id, $code, $desc, $system_type, $modules, $orientation, $quantity]);
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
        redirect('index.php?page=area_detail&id=' . $area_id);
    }
}

$points = db_query("SELECT * FROM points WHERE area_id = ? ORDER BY id ASC", [$area_id])->fetchAll();

include __DIR__ . '/../layout/header.php';
?>
<h1><i class="bi bi-door-open"></i> Área: <?= h($area['name']) ?></h1>
<p class="text-muted">
  Proyecto: <a href="index.php?page=project_detail&id=<?= h($area['project_id']) ?>"><?= h($area['project_name']) ?></a>
</p>

<a class="btn btn-sm btn-outline-primary"
   href="index.php?page=point_detail&area_id=<?=h($area_id)?>">
  <i class="bi bi-plus-circle"></i> Agregar punto
</a>



<div id="newPoint" class="collapse mb-3">
  <div class="card card-body">
    <form method="post">
      <input type="hidden" name="action" value="create_point">
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
      <button class="btn btn-success"><i class="bi bi-check-lg"></i> Guardar punto</button>
    </form>
  </div>
</div>

<table class="table table-sm table-striped align-middle">
  <thead>
    <tr><th>Código</th><th>Sistema</th><th>Mód.</th><th>Cant.</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($points as $pt): ?>
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
          <a href="index.php?page=point_move&id=<?= h($pt['id']) ?>" class="btn btn-outline-warning" title="Mover / sacar de área">
            <i class="bi bi-arrow-left-right"></i>
          </a>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$points): ?>
    <tr><td colspan="5" class="text-muted">Sin puntos en esta área.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php include __DIR__ . '/../layout/footer.php'; ?>
