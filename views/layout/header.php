<?php
$flash = function_exists('get_flash') ? get_flash() : [];
$user  = function_exists('current_user') ? current_user() : null;
$page  = $_GET['page'] ?? '';
require_once __DIR__ . '/../../includes/rbac.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Vimar Project</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f5f6fa;}
.navbar-brand{font-weight:600;}
.content-wrapper{padding:20px;}
.table-sm th,.table-sm td{vertical-align:middle;}
.ctxbar{background:#fff;border-bottom:1px solid rgba(0,0,0,.06)}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Vimar Project</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain"><span class="navbar-toggler-icon"></span></button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">

        <li class="nav-item">
          <a class="nav-link <?= in_array($page,['projects','project_detail','area_detail'])?'active':'' ?>" href="index.php?page=projects">
            <i class="bi bi-kanban"></i> Proyectos
          </a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= in_array($page,['brands','series_families','articles','homologations'])?'active':'' ?>" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-boxes"></i> Catálogo
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="index.php?page=divisions"><i class="bi bi-collection"></i> Divisiones</a></li>
            <li><a class="dropdown-item" href="index.php?page=brands"><i class="bi bi-collection"></i> Marcas y Series</a></li>
            <li><a class="dropdown-item" href="index.php?page=catalog_brand_types"><i class="bi bi-collection"></i> Marcas y Tipos Articulo</a></li>
            <li><a class="dropdown-item" href="index.php?page=series_families"><i class="bi bi-diagram-3"></i> Series (Familias)</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="index.php?page=articles"><i class="bi bi-list-check"></i> Artículos</a></li>
            <li><a class="dropdown-item" href="index.php?page=catalog_rules"><i class="bi bi-list-check"></i> Reglas de Puntos</a></li>
            <li><a class="dropdown-item" href="index.php?page=catalog_rules_wizard"><i class="bi bi-list-check"></i> Asistente Reglas</a></li>            
	    <li><a class="dropdown-item" href="index.php?page=homologations"><i class="bi bi-shuffle"></i> Homologaciones</a></li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= in_array($page,['brands','series_families','articles','homologations'])?'active':'' ?>" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-boxes"></i> Reportes
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="index.php?page=reports"><i class="bi bi-collection"></i> Reportes     </a></li>


          </ul>
        </li>

        <?php if((function_exists('can_perm') && (can_perm('users','view') || can_perm('roles','view') || can_perm('logs','view'))) || (function_exists('is_admin') && is_admin())): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= in_array($page,['users','logs'])?'active':'' ?>" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-shield-lock"></i> Admin
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="index.php?page=users"><i class="bi bi-people"></i> Usuarios</a></li>
            <?php if(function_exists('can_perm') && can_perm('roles','view')): ?>
            <li><a class="dropdown-item" href="index.php?page=roles"><i class="bi bi-person-badge"></i> Roles</a></li>
            <?php endif; ?>
            <li><a class="dropdown-item" href="index.php?page=logs"><i class="bi bi-journal-text"></i> Logs</a></li>
          </ul>
        </li>
        <?php endif; ?>
      </ul>

      <?php if($user): ?>
        <span class="navbar-text me-3 text-white-50">
          <i class="bi bi-person-circle"></i> <?=h($user['username'])?> (<?=h($user['role'] ?? '')?>)
        </span>
        <a href="index.php?page=logout" class="btn btn-outline-light btn-sm">Salir</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="ctxbar">
  <div class="container-fluid py-2 d-flex justify-content-between align-items-center">
    <div class="text-muted small" id="ctxTitle"></div>
    <div class="d-flex gap-2" id="ctxActions"></div>
  </div>
</div>

<div class="container-fluid content-wrapper">
<?php foreach($flash as $t=>$ms): foreach($ms as $m): ?>
  <div class="alert alert-<?=($t==='error'?'danger':$t)?> alert-dismissible fade show" role="alert">
    <?=h($m)?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; endforeach; ?>
