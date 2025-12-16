
<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/db.php';

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function is_post(){return ($_SERVER['REQUEST_METHOD']??'GET')==='POST';}
function get_param($k,$d=null){return $_GET[$k]??$d;}
function post_param($k,$d=null){return $_POST[$k]??$d;}
function redirect($url){
  $base=rtrim(BASE_URL,'/');
  if(strpos($url,'http')===0) header('Location: '.$url);
  else{
    if($base!=='' && $url!=='' && $url[0]!=='/') $url='/'.$url;
    header('Location: '.$base.$url);
  }
  exit;
}
function set_flash($t,$m){$_SESSION['flash'][$t][]=$m;}
function get_flash(){$f=$_SESSION['flash']??[];unset($_SESSION['flash']);return $f;}

function current_user(){return $_SESSION['user']??null;}
function current_user_id(){return $_SESSION['user']['id']??null;}
function current_user_role(){return $_SESSION['user']['role']??'viewer';}
function is_admin(){return current_user_role()==='admin';}
function require_login(){if(!current_user_id())redirect('index.php?page=login');}

function user_log($action,$etype='',$eid=null,$desc=''){
  $uid=current_user_id(); if(!$uid)return;
  db_query("INSERT INTO user_logs (user_id,action,entity_type,entity_id,description) VALUES (?,?,?,?,?)",
           [$uid,$action,$etype,$eid,$desc]);
}

if (!function_exists('add_flash')) {
  function add_flash($type, $msg) {
    set_flash($type, $msg);
  }
}