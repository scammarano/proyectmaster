<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login();

$project_id = (int)get_param('id', 0);
$p = db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch();
if (!$p) die('Proyecto no encontrado');

$rows = db_query("
  SELECT x.*, a.name AS area_name
  FROM points x
  LEFT JOIN areas a ON a.id = x.area_id
  WHERE x.project_id=?
  ORDER BY a.name, x.id
", [$project_id])->fetchAll();

$fn = 'reporte_proyecto_'.$project_id.'_'.date('Ymd_His').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$fn.'"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Proyecto', $p['name'] ?? '', 'ID', $project_id]);
fputcsv($out, []);
fputcsv($out, ['Area','Codigo','Nombre','Division','Marca','Serie','Tipo punto','Modulos','Ubicacion','Orientacion','Notas']);

foreach($rows as $r){
  fputcsv($out, [
    $r['area_name'] ?? '',
    $r['code'] ?? $r['id'],
    $r['name'] ?? '',
    $r['division_id'] ?? '',
    $r['brand_id'] ?? '',
    $r['series_id'] ?? '',
    $r['point_type_id'] ?? '',
    $r['modules'] ?? '',
    $r['location'] ?? '',
    $r['orientation'] ?? '',
    $r['notes'] ?? '',
  ]);
}
fclose($out);
exit;
