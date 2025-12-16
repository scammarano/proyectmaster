<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$project_id = (int)$_GET['project_id'];
echo "<h2>Áreas y Puntos del Proyecto #$project_id</h2>";
