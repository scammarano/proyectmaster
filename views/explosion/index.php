
<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$project_id = (int) get_param('project_id', 0);

$projects = db_query("SELECT * FROM projects ORDER BY id DESC")->fetchAll();

$rows = [];
if ($project_id) {
    $rows = db_query("
      SELECT a.code, a.name, b.name AS brand_name, s.name AS series_name,
             SUM(qty) AS total_qty
      FROM (
        SELECT p.id AS point_id, p.project_id, p.quantity AS qty,
               p.support_article_id AS article_id
        FROM points p
        WHERE p.project_id = ? AND p.support_article_id IS NOT NULL

        UNION ALL

        SELECT p.id, p.project_id, p.quantity, p.box_article_id
        FROM points p
        WHERE p.project_id = ? AND p.box_article_id IS NOT NULL

        UNION ALL

        SELECT p.id, p.project_id, p.quantity, p.plate_article_id
        FROM points p
        WHERE p.project_id = ? AND p.plate_article_id IS NOT NULL

        UNION ALL

        SELECT p.id, p.project_id, p.quantity, p.cover_article_id
        FROM points p
        WHERE p.project_id = ? AND p.cover_article_id IS NOT NULL

        UNION ALL

        SELECT p.id, p.project_id, p.quantity * 1 AS qty, pc.article_id
        FROM points p
        JOIN point_components pc ON pc.point_id = p.id
        WHERE p.project_id = ?
      ) x
      JOIN articles a ON a.id = x.article_id
      JOIN brands b ON b.id = a.brand_id
      LEFT JOIN series s ON s.id = a.series_id
      GROUP BY a.id
      ORDER BY b.name, s.name, a.code
    ", [$project_id, $project_id, $project_id, $project_id, $project_id])->fetchAll();
}

include __DIR__ . '/../layout/header.php';
?>
<h1><i class="bi bi-collection"></i> Explosión de materiales</h1>

<form method="get" class="row g-3 mb-4">
  <input type="hidden" name="page" value="explosion">
  <div class="col-md-6">
    <label class="form-label">Proyecto</label>
    <select name="project_id" class="form-select">
      <option value="">Seleccione...</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= h($p['id']) ?>" <?= $project_id==$p['id']?'selected':'' ?>>
          <?= h($p['id']) ?> — <?= h($p['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2 d-flex align-items-end">
    <button class="btn btn-primary"><i class="bi bi-search"></i> Ver</button>
  </div>
</form>

<?php if ($project_id): ?>
  <table class="table table-sm table-striped align-middle">
    <thead>
      <tr>
        <th>Código</th><th>Nombre</th><th>Marca/Serie</th><th>Cantidad total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r['code']) ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h($r['brand_name']) ?> <?= $r['series_name'] ? ' / ' . h($r['series_name']) : '' ?></td>
          <td><?= h($r['total_qty']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="4" class="text-muted">No hay componentes para este proyecto.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
