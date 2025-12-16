
<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$area_id = (int) get_param('id', 0);
$area = db_query("SELECT * FROM areas WHERE id = ?", [$area_id])->fetch();
if (!$area) die('Área no encontrada');

$project_id = $area['project_id'];

if (is_post()) {
    $new_name = trim(post_param('name'));
    $copy_points = post_param('copy_points') === '1';

    if ($new_name === '') {
        $new_name = $area['name'] . ' (copia)';
    }

    db_query("INSERT INTO areas (project_id, name, brand_id, series_id) VALUES (?,?,?,?)",
             [$project_id, $new_name, $area['brand_id'], $area['series_id']]);
    $new_area_id = db_connect()->lastInsertId();

    if ($copy_points) {
        $points = db_query("SELECT * FROM points WHERE area_id = ?", [$area_id])->fetchAll();
        foreach ($points as $pt) {
            db_query("INSERT INTO points (project_id, area_id, code, description, system_type, brand_id, series_id, modules, orientation,
                                          support_article_id, box_article_id, plate_article_id, cover_article_id, quantity)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                     [$pt['project_id'], $new_area_id, $pt['code'], $pt['description'], $pt['system_type'],
                      $pt['brand_id'], $pt['series_id'], $pt['modules'], $pt['orientation'],
                      $pt['support_article_id'], $pt['box_article_id'], $pt['plate_article_id'], $pt['cover_article_id'],
                      $pt['quantity']]);
            $new_point_id = db_connect()->lastInsertId();
            $pcs = db_query("SELECT * FROM point_components WHERE point_id = ?", [$pt['id']])->fetchAll();
            foreach ($pcs as $pc) {
                db_query("INSERT INTO point_components (point_id, article_id, position) VALUES (?,?,?)",
                         [$new_point_id, $pc['article_id'], $pc['position']]);
            }
        }
    }
    redirect('index.php?page=project_detail&id=' . $project_id);
}

include __DIR__ . '/../layout/header.php';
?>
<h1><i class="bi bi-files"></i> Duplicar área</h1>
<p>Área original: <strong><?= h($area['name']) ?></strong></p>

<form method="post" class="card p-4">
  <div class="mb-3">
    <label class="form-label">Nombre para la nueva área</label>
    <input type="text" name="name" class="form-control" placeholder="<?= h($area['name'] . ' (copia)') ?>">
  </div>
  <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="copy_points" value="1" id="copyPoints" checked>
    <label class="form-check-label" for="copyPoints">
      Incluir también los puntos y sus componentes
    </label>
  </div>
  <button class="btn btn-success"><i class="bi bi-check-lg"></i> Duplicar</button>
  <a href="index.php?page=project_detail&id=<?= h($project_id) ?>" class="btn btn-secondary">Cancelar</a>
</form>

<?php include __DIR__ . '/../layout/footer.php'; ?>
