<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$project_id = (int)($_GET['project_id'] ?? 0);
?>
<h1>Reportes del Proyecto</h1>
<ul>
  <li><a href="report_general.php?project_id=<?= $project_id ?>">Informe General</a></li>
</ul>
