
<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$point_id = (int) get_param('id', 0);
$pt = db_query("SELECT * FROM points WHERE id = ?", [$point_id])->fetch();
if (!$pt) die('Punto no encontrado');

$project_id = $pt['project_id'];
$areas = db_query("SELECT * FROM areas WHERE project_id = ? ORDER BY name", [$project_id])->fetchAll();

if (is_post()) {
    $dest_area_id = post_param('area_id', '');
    $dest_area_id = $dest_area_id === '' ? null : (int)$dest_area_id;

    db_query("INSERT INTO points (project_id, area_id, code, description, system_type, brand_id, series_id, modules, orientation,
                                  support_article_id, box_article_id, plate_article_id, cover_article_id, quantity)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
             [$pt['project_id'], $dest_area_id, $pt['code'], $pt['description'], $pt['system_type'],
              $pt['brand_id'], $pt['series_id'], $pt['modules'], $pt['orientation'],
              $pt['support_article_id'], $pt['box_article_id'], $pt['plate_article_id'], $pt['cover_article_id'], $pt['quantity']]);
    $new_point_id = db_connect()->lastInsertId();
    $pcs = db_query("SELECT * FROM point_components WHERE point_id = ?", [$pt['id']])->fetchAll();
    foreach ($pcs as $pc) {
        db_query("INSERT INTO point_components (point_id, article_id, position) VALUES (?,?,?)",
                 [$new_point_id, $pc['article_id'], $pc['position']]);
    }

    if ($dest_area_id) {
        redirect('index.php?page=area_detail&id=' . $dest_area_id);
    } else {
        redirect('index.php?page=project_detail&id=' . $project_id);
    }
}

include __DIR__ . '/../layout/header.php';
?>
<h1><i class="bi bi-files"></i> Duplicar punto</h1>
<p>Código: <strong><?= h($pt['code']) ?></strong> — Proyecto ID: <?= h($project_id) ?></p>

<form method="post" class="card p-4">
  <div class="mb-3">
    <label class="form-label">Área destino</label>
    <select name="area_id" class="form-select">
      <option value="">Sin área (punto rápido del proyecto)</option>
      <?php foreach ($areas as $a): ?>
        <option value="<?= h($a['id']) ?>"><?= h($a['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-success"><i class="bi bi-check-lg"></i> Duplicar punto</button>
  <a href="index.php?page=project_detail&id=<?= h($project_id) ?>" class="btn btn-secondary">Cancelar</a>
</form>

<?php include __DIR__ . '/../layout/footer.php'; ?>
