<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();

if (!function_exists('add_flash') && function_exists('set_flash')) { function add_flash($t,$m){ set_flash($t,$m); } }

if (!function_exists('col_exists')) {
  function col_exists(string $table, string $col): bool {
    try { return (bool)db_query("SHOW COLUMNS FROM `$table` LIKE ?", [$col])->fetch(); }
    catch (Throwable $e) { return false; }
  }
}

$HAS_CLOSED = col_exists('projects','is_closed');
$HAS_CREATED_BY = col_exists('projects','created_by');
$HAS_CREATED_AT = col_exists('projects','created_at');

$clientCol = null;
foreach (['client','client_name','customer','customer_name'] as $c) { if (col_exists('projects',$c)) { $clientCol = $c; break; } }

if (is_post()) {
  $name = trim(post_param('name',''));
  if ($name==='') { add_flash('error','Nombre requerido'); redirect('index.php?page=project_new'); }

  $created_by = current_user_id();

  $insCols=[]; $insVals=[];
  $insCols[]='name'; $insVals[]=$name;
  if ($clientCol) { $insCols[]=$clientCol; $insVals[]=trim(post_param('client','')); }
  if ($HAS_CLOSED) { $insCols[]='is_closed'; $insVals[]=0; }
  if ($HAS_CREATED_BY) { $insCols[]='created_by'; $insVals[]=$created_by; }
  if ($HAS_CREATED_AT) { $insCols[]='created_at'; $insVals[]=date('Y-m-d H:i:s'); }

  db_query("INSERT INTO projects(".implode(',',$insCols).") VALUES (".implode(',',array_fill(0,count($insCols),'?')).")", $insVals);
  $newId = (int)db_connect()->lastInsertId();

  add_flash('success','Proyecto creado.');
  redirect('index.php?page=project_detail&id='.$newId);
}

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('Nuevo proyecto', `
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=projects"><i class="bi bi-arrow-left"></i> Volver</a>
`);
</script>

<h1 class="h3 mb-3"><i class="bi bi-plus-circle"></i> Nuevo proyecto</h1>

<div class="card">
  <div class="card-body">
    <form method="post" class="row g-3">
      <div class="col-md-7">
        <label class="form-label">Nombre del proyecto</label>
        <input class="form-control" name="name" required autofocus>
      </div>
      <div class="col-md-5">
        <label class="form-label">Cliente</label>
        <?php if($clientCol): ?>
          <input class="form-control" name="client" placeholder="Nombre del cliente (opcional)">
        <?php else: ?>
          <input class="form-control" value="—" disabled>
          <div class="text-muted small mt-1">Tu tabla <code>projects</code> no tiene columna cliente; si agregas <code>client</code> aparecerá aquí.</div>
        <?php endif; ?>
      </div>
      <div class="col-12 d-grid">
        <button class="btn btn-primary btn-lg"><i class="bi bi-check2"></i> Crear proyecto</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
