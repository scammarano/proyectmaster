
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
    db_query("UPDATE points SET area_id = ? WHERE id = ?", [$dest_area_id, $point_id]);

    if ($dest_area_id) {
        redirect('index.php?page=area_detail&id=' . $dest_area_id);
    } else {
        redirect('index.php?page=project_detail&id=' . $project_id);
    }
}

include __DIR__ . '/../layout/header.php';
?>
<h1><i class="bi bi-arrow-left-right"></i> Mover punto</h1>
<p>Código: <strong><?= h($pt['code']) ?></strong> — Proyecto ID: <?= h($project_id) ?></p>

<form method="post" class="card p-4">
  <div class="mb-3">
    <label class="form-label">Área destino</label>
    <select name="area_id" class="form-select">
      <option value="">Sin área (punto rápido del proyecto)</option>
      <?php foreach ($areas as $a): ?>
        <option value="<?= h($a['id']) ?>" <?= $pt['area_id']==$a['id']?'selected':'' ?>><?= h($a['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="form-text">
      Esto permite pasar un punto libre a un área, cambiarlo de área, o dejarlo como punto rápido.
    </div>
  </div>
  <button class="btn btn-success"><i class="bi bi-check-lg"></i> Mover punto</button>
  <a href="index.php?page=project_detail&id=<?= h($project_id) ?>" class="btn btn-secondary">Cancelar</a>
</form>

<?php include __DIR__ . '/../layout/footer.php'; ?>
