<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$project_id = (int)$_GET['project_id'];

$project = db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch();
$areas = db_query("SELECT COUNT(*) c FROM areas WHERE project_id=?", [$project_id])->fetch()['c'];
$puntos = db_query("
  SELECT COUNT(*) c FROM points p
  JOIN areas a ON a.id=p.area_id
  WHERE a.project_id=?", [$project_id])->fetch()['c'];
?>
<link rel="stylesheet" href="/assets/css/report.css">

<div class="report">
  <h1><?= htmlspecialchars($project['name'] ?? 'Proyecto') ?></h1>
  <p><strong>Cliente:</strong> <?= htmlspecialchars($project['client'] ?? '') ?></p>
  <p><strong>Dirección:</strong> <?= htmlspecialchars($project['address'] ?? '') ?></p>

  <hr>

  <h2>Resumen</h2>
  <ul>
    <li>Áreas: <?= $areas ?></li>
    <li>Puntos: <?= $puntos ?></li>
  </ul>

  <a href="export_pdf.php?type=general&project_id=<?= $project_id ?>" class="btn">Exportar PDF</a>
  <a href="export_excel.php?type=general&project_id=<?= $project_id ?>" class="btn">Exportar Excel</a>
</div>
