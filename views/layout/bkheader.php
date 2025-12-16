
<?php $flash=get_flash(); $user=current_user(); ?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8">
<title>TOTOProjectS</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f5f6fa;}
.navbar-brand{font-weight:600;}
.content-wrapper{padding:20px;}
.table-sm th,.table-sm td{vertical-align:middle;}
</style>
</head><body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">TOTOProjectS</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <?php if($user): ?>
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="index.php?page=projects"><i class="bi bi-diagram-3"></i> Proyectos</a></li>
	<li class="nav-item"> <a class="nav-link" href="index.php?page=divisions">  <i class="bi bi-diagram-2"></i> Divisiones  </a></li>        
	<li class="nav-item"><a class="nav-link" href="index.php?page=brands"><i class="bi bi-collection"></i> Marcas / Series</a></li>

<a class="nav-link <?= (($_GET['page'] ?? '')==='series_families'?'active':'') ?>"
   href="index.php?page=series_families">
  <i class="bi bi-diagram-3"></i> Series (Familias)
</a>
        <li class="nav-item"><a class="nav-link" href="index.php?page=articles"><i class="bi bi-list-ul"></i> Artículos</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php?page=homologations_report"><i class="bi bi-link-45deg"></i> Homologaciones</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php?page=explosion"><i class="bi bi-box-seam"></i> Explosión</a></li>
        <?php if(is_admin()):?><li class="nav-item"><a class="nav-link" href="index.php?page=users"><i class="bi bi-people"></i> Usuarios</a></li><?php endif;?>
      </ul>
      <span class="navbar-text me-3"><i class="bi bi-person-circle"></i> <?=h($user['username'])?> (<?=h($user['role'])?>)</span>
      <a href="index.php?page=logout" class="btn btn-outline-light btn-sm">Salir</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="container-fluid content-wrapper">
<?php foreach($flash as $t=>$ms):foreach($ms as $m):?>
<div class="alert alert-<?=$t==='error'?'danger':$t?> alert-dismissible fade show" role="alert">
  <?=h($m)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endforeach;endforeach;?>
