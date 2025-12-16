<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//////////
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/attachments.php';
require_once __DIR__ . '/includes/point_rules.php';
require_once __DIR__ . '/includes/rules.php';
require_once __DIR__ . '/includes/rbac.php';

$page=get_param('page','');
$user=current_user();
if(!$user && !in_array($page,['login'])) $page='login';

if($page==='login'){
  if(is_post()){
    $u=trim(post_param('username')); $p=trim(post_param('password'));
    $row=db_query("SELECT * FROM users WHERE username=? AND is_active=1",[$u])->fetch();
    if($row && password_verify($p,$row['password_hash'])){
      $_SESSION['user']=$row; set_flash('success','Bienvenido, '.$row['username']); redirect('index.php?page=projects');
    }else set_flash('error','Usuario o contraseña incorrectos.');
  }
  require __DIR__.'/views/auth/login.php'; exit;
}
if($page==='logout'){session_destroy();redirect('index.php?page=login');}
require_login();

switch($page){
  case 'projects':            require_permission('projects','view'); require __DIR__.'/views/projects/list.php'; break;
  case 'project_new':         require_permission('projects','create'); require __DIR__.'/views/projects/new.php'; break;
  case 'project_detail':      require_permission('projects','view'); require __DIR__.'/views/projects/detail.php'; break;
  case 'project_duplicate':   require_permission('projects','duplicate'); require __DIR__.'/views/projects/duplicate.php'; break;
  case 'project_delete':      require_permission('projects','delete'); require __DIR__.'/views/projects/delete.php'; break;

  case 'area_detail':         require_permission('areas','view'); require __DIR__.'/views/areas/detail.php'; break;
  case 'area_move':           require_permission('areas','move'); require __DIR__.'/views/areas/move.php'; break;
  case 'area_delete':         require_permission('areas','delete'); require __DIR__.'/views/areas/delete.php'; break;

  case 'point_create':        require_permission('points','create'); require __DIR__.'/views/points/create.php'; break;
  case 'point_detail':        require_permission('points','view'); require __DIR__.'/views/points/detail.php'; break;
  case 'point_edit':          require_permission('points','edit'); require __DIR__.'/views/points/edit.php'; break;

  case 'point_template_detail': require_permission('templates','edit'); require __DIR__.'/views/point_templates/detail.php'; break;

  case 'brands':              require_permission('brands','view'); require __DIR__.'/views/brands/list.php'; break;
  case 'divisions':           require_permission('divisions','view'); require __DIR__.'/views/divisions/list.php'; break;
  case 'series_families':     require_permission('series','view'); require __DIR__.'/views/series/families.php'; break;

  case 'articles':            require_permission('articles','view'); require __DIR__.'/views/articles/list.php'; break;
  case 'article_detail':      require_permission('articles','edit'); require __DIR__.'/views/articles/detail.php'; break;

  case 'catalog_rules':       require_permission('rules','view'); require __DIR__.'/views/catalog/rules.php'; break;
  case 'catalog_rules_wizard':require_permission('rules','edit'); require __DIR__.'/views/catalog/rules_wizard.php'; break;
  case 'catalog_brand_types': require_permission('catalog','edit'); require __DIR__.'/views/catalog/brand_types.php'; break;

  case 'homologations':       require_permission('homologations','view'); require __DIR__.'/views/homologations/index.php'; break;
  case 'homologations_report':require_permission('homologations','report'); require __DIR__.'/views/homologations/report.php'; break;

  case 'explosion':           require_permission('explosion','view'); require __DIR__.'/views/explosion/index.php'; break;

  case 'reports':               require_permission('reports','view'); require __DIR__.'/views/reports/index.php'; break;
  case 'report_project':       require_permission('reports','view'); require __DIR__.'/views/reports/project.php'; break;
  case 'report_project_pdf':   require_permission('reports','view'); require __DIR__.'/views/reports/project_pdf.php'; break;
  case 'report_project_excel': require_permission('reports','view'); require __DIR__.'/views/reports/project_excel.php'; break;

  case 'users':               require_permission('users','view'); require __DIR__.'/views/users/list.php'; break;
  case 'user_detail':         require_permission('users','edit'); require __DIR__.'/views/users/detail.php'; break;

  case 'roles':               require_permission('roles','view'); require __DIR__.'/views/roles/list.php'; break;
  case 'role_detail':         require_permission('roles','permissions'); require __DIR__.'/views/roles/detail.php'; break;

  case 'logs':                require_permission('logs','view'); require __DIR__.'/views/logs/list.php'; break;

  default: redirect('index.php?page=projects');
}
