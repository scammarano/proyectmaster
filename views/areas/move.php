<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if (!is_post()) { echo json_encode(['ok'=>false,'error'=>'Método no permitido']); exit; }

$area_id = (int)post_param('area_id', 0);
$project_id = (int)post_param('project_id', 0);
$new_parent_raw = post_param('new_parent_id', '');
$new_parent_id = ($new_parent_raw === '' || $new_parent_raw === null) ? null : (int)$new_parent_raw;

if ($area_id<=0 || $project_id<=0) { echo json_encode(['ok'=>false,'error'=>'Datos incompletos']); exit; }

$HAS_CLOSED = (bool)db_query("SHOW COLUMNS FROM projects LIKE 'is_closed'")->fetch();
if ($HAS_CLOSED) {
  $p = db_query("SELECT is_closed FROM projects WHERE id=?", [$project_id])->fetch();
  if ($p && (int)$p['is_closed']===1) { echo json_encode(['ok'=>false,'error'=>'Proyecto cerrado']); exit; }
}

$a = db_query("SELECT id, parent_area_id FROM areas WHERE id=? AND project_id=?", [$area_id,$project_id])->fetch();
if(!$a){ echo json_encode(['ok'=>false,'error'=>'Área no válida']); exit; }

if($new_parent_id!==null){
  if($new_parent_id===$area_id){ echo json_encode(['ok'=>false,'error'=>'No puede ser padre de sí misma']); exit; }
  $p = db_query("SELECT id FROM areas WHERE id=? AND project_id=?", [$new_parent_id,$project_id])->fetch();
  if(!$p){ echo json_encode(['ok'=>false,'error'=>'Área padre no válida']); exit; }

  $cur = $new_parent_id;
  for($i=0;$i<200;$i++){
    $row = db_query("SELECT parent_area_id FROM areas WHERE id=?", [$cur])->fetch();
    if(!$row) break;
    $par = $row['parent_area_id'];
    if($par===null) break;
    $par = (int)$par;
    if($par===$area_id){
      echo json_encode(['ok'=>false,'error'=>'Movimiento inválido: crea ciclo']); exit;
    }
    $cur = $par;
  }
}

db_query("UPDATE areas SET parent_area_id=? WHERE id=? AND project_id=?", [$new_parent_id, $area_id, $project_id]);

if(function_exists('user_log')){
  user_log('area_move','area',$area_id,'Move parent_area_id to '.($new_parent_id===null?'NULL':$new_parent_id));
}

echo json_encode(['ok'=>true]);
