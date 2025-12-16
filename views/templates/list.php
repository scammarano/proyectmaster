
<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (is_post() && post_param('action') === 'create') {
    $name = trim(post_param('name'));
    $system_type = post_param('system_type', 'electrico');
    $orientation = post_param('orientation', 'H');
    if ($name !== '') {
        db_query("INSERT INTO point_templates (name, system_type, orientation) VALUES (?,?,?)",
                 [$name, $system_type, $orientation]);
    }
    redirect('index.php?page=templates');
}

$templates = db_query("SELECT pt.*, b.name AS brand_name, s.name AS series_name
                       FROM point_templates pt
                       LEFT JOIN brands b ON b.id = pt.brand_id
                       LEFT JOIN series s ON s.id = pt.series_id
                       ORDER BY pt.name")->fetchAll();

include __DIR__ . '/../layout/header.php';
?>
<h1><i class="bi bi-layout-text-sidebar"></i> Plantillas de puntos</h1>

<div class="card mb-4">
  <div class="card-header">Nueva plantilla de punto</div>
  <div class="card-body">
    <form method="post" class="row g-3">
      <input type="hidden" name="action" value="create">
      <div class="col-md-4">
        <label class="form-label">Nombre</label>
        <input type="text" name="name" class="form-control" placeholder="Dormitorio principal - cabecera" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Tipo de sistema</label>
        <select name="system_type" class="form-select">
          <option value="electrico">Eléctrico</option>
          <option value="datos">Datos</option>
          <option value="wifi">WiFi</option>
          <option value="persianas">Persianas</option>
          <option value="otros">Otros</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Orientación</label>
        <select name="orientation" class="form-select">
          <option value="H">Horizontal</option>
          <option value="V">Vertical</option>
          <option value="N">N/A</option>
        </select>
      </div>
      <div class="col-12">
        <button class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Crear plantilla</button>
      </div>
    </form>
  </div>
</div>

<table class="table table-sm table-striped align-middle">
  <thead>
    <tr>
      <th>Nombre</th><th>Sistema</th><th>Mód.</th><th>Marca/Serie</th><th>Soporte</th><th>Placa</th><th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($templates as $t): ?>
    <?php
      $support = null;
      $plate = null;
      if ($t['support_article_id']) {
        $support = db_query("SELECT code, modules FROM articles WHERE id = ?", [$t['support_article_id']])->fetch();
      }
      if ($t['plate_article_id']) {
        $plate = db_query("SELECT code FROM articles WHERE id = ?", [$t['plate_article_id']])->fetch();
      }
    ?>
    <tr>
      <td><?= h($t['name']) ?></td>
      <td><?= h($t['system_type']) ?></td>
      <td><?= h($t['modules']) ?></td>
      <td><?= h($t['brand_name']) ?> <?= $t['series_name'] ? ' / ' . h($t['series_name']) : '' ?></td>
      <td><?= $support ? h($support['code']) . ' (' . h($support['modules']) . 'M)' : '' ?></td>
      <td><?= $plate ? h($plate['code']) : '' ?></td>
      <td class="text-end">
        <a href="index.php?page=template_edit&id=<?= h($t['id']) ?>" class="btn btn-sm btn-primary">
          <i class="bi bi-pencil-square"></i> Editar
        </a>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$templates): ?>
    <tr><td colspan="7" class="text-muted">No hay plantillas creadas.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php include __DIR__ . '/../layout/footer.php'; ?>
