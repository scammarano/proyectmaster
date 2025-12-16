<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attachments.php';
require_login();

$project_id = (int)get_param('id', 0);
$project = db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch();
if (!$project) die('Proyecto no encontrado');

$areas = db_query("SELECT * FROM areas WHERE project_id=? ORDER BY name", [$project_id])->fetchAll();
$pts = db_query("SELECT x.*, a.name AS area_name FROM points x LEFT JOIN areas a ON a.id=x.area_id WHERE x.project_id=? ORDER BY a.name, x.id", [$project_id])->fetchAll();
$atts = att_list('project', $project_id);

?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reporte Proyecto <?=h($project_id)?></title>
  <style>
    body{ font-family: Arial, sans-serif; margin:24px; }
    h1,h2{ margin:0 0 8px 0; }
    .muted{ color:#666; font-size:12px; }
    table{ width:100%; border-collapse:collapse; margin-top:10px; }
    th,td{ border:1px solid #ddd; padding:6px 8px; font-size:12px; vertical-align:top; }
    th{ background:#f3f3f3; }
    .right{text-align:right;}
    @media print{
      a{ color:inherit; text-decoration:none; }
    }
  </style>
</head>
<body>
  <h1>Informe del Proyecto</h1>
  <div class="muted">Proyecto: <b><?=h($project['name'] ?? '')?></b> — ID: <?=h($project_id)?> — Generado: <?=h(date('Y-m-d H:i'))?></div>

  <h2 style="margin-top:18px;">Resumen</h2>
  <table>
    <tr><th>Áreas</th><td class="right"><?=count($areas)?></td></tr>
    <tr><th>Puntos</th><td class="right"><?=count($pts)?></td></tr>
  </table>

  <h2 style="margin-top:18px;">Adjuntos del proyecto</h2>
  <?php if(!$atts): ?>
    <div class="muted">Sin adjuntos</div>
  <?php else: ?>
    <ul>
      <?php foreach($atts as $a): ?>
        <li><?=h($a['original_name'])?> <span class="muted"><?=h($a['created_at'] ?? '')?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h2 style="margin-top:18px;">Puntos</h2>
  <table>
    <thead>
      <tr>
        <th>Área</th><th>Código</th><th>Nombre</th><th class="right">Mód</th><th>Ubicación</th><th>Orientación</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($pts as $p): ?>
      <tr>
        <td><?=h($p['area_name'] ?? '')?></td>
        <td><?=h($p['code'] ?? $p['id'])?></td>
        <td><?=h($p['name'] ?? '')?></td>
        <td class="right"><?=h($p['modules'] ?? '')?></td>
        <td><?=h($p['location'] ?? '')?></td>
        <td><?=h($p['orientation'] ?? '')?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$pts): ?><tr><td colspan="6" class="muted">Sin puntos</td></tr><?php endif; ?>
    </tbody>
  </table>

  <script>
    // abre diálogo de impresión si quieres:
    // window.print();
  </script>
</body>
</html>
