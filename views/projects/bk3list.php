<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();

if (!function_exists('add_flash') && function_exists('set_flash')) {
  function add_flash($t,$m){ set_flash($t,$m); }
}

if (!function_exists('table_exists')) {
  function table_exists(string $table): bool {
    try { $stmt = db_query("SHOW TABLES LIKE ?", [$table]); return (bool)$stmt->fetch(); }
    catch (Throwable $e) { return false; }
  }
}
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
foreach (['client','client_name','customer','customer_name'] as $c) {
  if (col_exists('projects',$c)) { $clientCol = $c; break; }
}

$HAS_ATTACH = table_exists('attachments'); // si existe tu módulo de adjuntos usa esta tabla
$HAS_PDIV = table_exists('project_divisions');

function project_counts(): array {
  $rows = db_query("
    SELECT p.*,
      (SELECT COUNT(*) FROM areas a WHERE a.project_id=p.id) AS areas_count,
      (SELECT COUNT(*) FROM points x WHERE x.project_id=p.id) AS points_count
    FROM projects p
    ORDER BY p.id DESC
  ")->fetchAll();
  return $rows ?: [];
}

function project_has_any_children(int $pid): bool {
  $a = (int)db_query("SELECT COUNT(*) c FROM areas WHERE project_id=?",[$pid])->fetch()['c'];
  $p = (int)db_query("SELECT COUNT(*) c FROM points WHERE project_id=?",[$pid])->fetch()['c'];
  $att = 0;
  if (table_exists('attachments')) {
    // attachments: entity_type, entity_id
    if (col_exists('attachments','entity_type') && col_exists('attachments','entity_id')) {
      $att = (int)db_query("SELECT COUNT(*) c FROM attachments WHERE entity_type='project' AND entity_id=?",[$pid])->fetch()['c'];
    }
  }
  return ($a+$p+$att)>0;
}

if (is_post()) {
  $action = post_param('action','');

  if ($action === 'toggle_close' && $HAS_CLOSED) {
    $id = (int)post_param('id');
    $p = db_query("SELECT is_closed FROM projects WHERE id=?", [$id])->fetch();
    if ($p) {
      $new = ((int)$p['is_closed']===1) ? 0 : 1;
      db_query("UPDATE projects SET is_closed=? WHERE id=?", [$new, $id]);
      add_flash('success', $new ? 'Proyecto cerrado.' : 'Proyecto reabierto.');
    }
    redirect('index.php?page=projects');
  }

  if ($action === 'duplicate_project') {
    $id = (int)post_param('id');
    $p = db_query("SELECT * FROM projects WHERE id=?", [$id])->fetch();
    if (!$p) { add_flash('error','Proyecto no encontrado'); redirect('index.php?page=projects'); }

    $name = ($p['name'] ?? 'Proyecto') . ' (Copia)';
    $created_by = current_user_id();

    // copiar campos base
    $cols = db_query("SHOW COLUMNS FROM projects")->fetchAll();
    $colNames = array_map(fn($r)=>$r['Field'], $cols);
    $skip = ['id','created_at','updated_at'];
    $insCols=[]; $insVals=[];
    foreach($colNames as $c){
      if (in_array($c,$skip,true)) continue;
      if ($c==='name'){ $insCols[]=$c; $insVals[]=$name; continue; }
      if ($c==='is_closed'){ $insCols[]=$c; $insVals[]=0; continue; }
      if ($c==='created_by'){ $insCols[]=$c; $insVals[]=$created_by; continue; }
      $insCols[]=$c; $insVals[]=$p[$c] ?? null;
    }
    // fallback si no existían columnas esperadas
    if (!in_array('name',$insCols,true)) { $insCols[]='name'; $insVals[]=$name; }
    if ($HAS_CREATED_BY && !in_array('created_by',$insCols,true)) { $insCols[]='created_by'; $insVals[]=$created_by; }
    if ($HAS_CREATED_AT && !in_array('created_at',$insCols,true)) { $insCols[]='created_at'; $insVals[]=date('Y-m-d H:i:s'); }

    db_query("INSERT INTO projects(".implode(',',$insCols).") VALUES (".implode(',',array_fill(0,count($insCols),'?')).")", $insVals);
    $newProjectId = (int)db_connect()->lastInsertId();

    // duplicar project_divisions
    if ($HAS_PDIV) {
      $divs = db_query("SELECT division_id FROM project_divisions WHERE project_id=?",[$id])->fetchAll();
      foreach($divs as $d){
        db_query("INSERT IGNORE INTO project_divisions(project_id,division_id) VALUES (?,?)", [$newProjectId,(int)$d['division_id']]);
      }
    }

    // duplicar áreas (mantiene jerarquía si existe parent_area_id)
    $HAS_PARENT = col_exists('areas','parent_area_id');
    $HAS_AREA_CREATED_AT = col_exists('areas','created_at');

    $areas = db_query("SELECT * FROM areas WHERE project_id=? ORDER BY id", [$id])->fetchAll();
    $map = [];
    foreach ($areas as $a) {
      if ($HAS_AREA_CREATED_AT) {
        db_query("INSERT INTO areas(project_id,parent_area_id,name,created_at) VALUES (?,?,?,NOW())", [$newProjectId, null, $a['name']]);
      } else {
        db_query("INSERT INTO areas(project_id,parent_area_id,name) VALUES (?,?,?)", [$newProjectId, null, $a['name']]);
      }
      $map[(int)$a['id']] = (int)db_connect()->lastInsertId();
    }
    if ($HAS_PARENT) {
      foreach ($areas as $a) {
        $oldId = (int)$a['id'];
        $oldParent = (int)($a['parent_area_id'] ?? 0);
        if ($oldParent>0 && isset($map[$oldParent])) {
          db_query("UPDATE areas SET parent_area_id=? WHERE id=?", [$map[$oldParent], $map[$oldId]]);
        }
      }
    }

    // duplicar puntos
    $pts = db_query("SELECT * FROM points WHERE project_id=? ORDER BY id", [$id])->fetchAll();
    $cols = db_query("SHOW COLUMNS FROM points")->fetchAll();
    $colNames = array_map(fn($r)=>$r['Field'], $cols);
    $skip = ['id','created_at','updated_at'];

    foreach ($pts as $pt) {
      $insCols=[]; $insVals=[];
      foreach ($colNames as $c) {
        if (in_array($c,$skip,true)) continue;
        if ($c==='project_id') { $insCols[]=$c; $insVals[]=$newProjectId; continue; }
        if ($c==='area_id') {
          $oldArea = (int)($pt['area_id'] ?? 0);
          $insCols[]=$c; $insVals[] = ($oldArea>0 && isset($map[$oldArea])) ? $map[$oldArea] : null;
          continue;
        }
        $insCols[]=$c; $insVals[]=$pt[$c] ?? null;
      }
      db_query("INSERT INTO points(".implode(',',$insCols).") VALUES (".implode(',',array_fill(0,count($insCols),'?')).")", $insVals);
    }

    add_flash('success','Proyecto duplicado.');
    redirect('index.php?page=projects');
  }

  if ($action === 'delete_project') {
    $id = (int)post_param('id');
    $p = db_query("SELECT id,name FROM projects WHERE id=?",[$id])->fetch();
    if(!$p){ add_flash('error','Proyecto no encontrado'); redirect('index.php?page=projects'); }

    if (project_has_any_children($id)) {
      add_flash('error','No se puede eliminar: el proyecto tiene áreas/puntos/adjuntos. Elimina primero el contenido.');
      redirect('index.php?page=projects');
    }

    if ($HAS_PDIV) db_query("DELETE FROM project_divisions WHERE project_id=?",[$id]);
    db_query("DELETE FROM projects WHERE id=?",[$id]);
    add_flash('success','Proyecto eliminado.');
    redirect('index.php?page=projects');
  }
}

$rows = project_counts();

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('Proyectos', `
  <a class="btn btn-sm btn-primary" href="index.php?page=projects&action=new"><i class="bi bi-plus-circle"></i> Nuevo</a>
`);
</script>

<h1 class="h3 mb-3"><i class="bi bi-kanban"></i> Proyectos</h1>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
      <thead>
        <tr>
          <th>Proyecto</th>
          <th>Cliente</th>
          <th class="text-end">Áreas</th>
          <th class="text-end">Puntos</th>
          <th class="text-center">Estado</th>
          <th class="text-end" style="width:320px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $p): $closed = $HAS_CLOSED ? ((int)($p['is_closed'] ?? 0)===1) : false; ?>
        <tr>
          <td>
            <a class="fw-semibold text-decoration-none" href="index.php?page=project_detail&id=<?=h($p['id'])?>"><?=h($p['name'])?></a>
            <?php if($HAS_CREATED_BY && !empty($p['created_by'])): ?>
              <div class="text-muted small">Creado por: <?=h($p['created_by'])?></div>
            <?php endif; ?>
          </td>
          <td><?= $clientCol ? h($p[$clientCol] ?? '') : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end"><?=h($p['areas_count'])?></td>
          <td class="text-end"><?=h($p['points_count'])?></td>
          <td class="text-center">
            <?php if(!$HAS_CLOSED): ?><span class="badge text-bg-secondary">—</span>
            <?php else: ?>
              <?= $closed ? '<span class="badge text-bg-secondary"><i class="bi bi-lock"></i> Cerrado</span>' :
                           '<span class="badge text-bg-success"><i class="bi bi-unlock"></i> Abierto</span>' ?>
            <?php endif; ?>
          </td>
          <td class="text-end">

            <a class="btn btn-sm btn-outline-primary" title="Abrir" href="index.php?page=project_detail&id=<?=h($p['id'])?>"><i class="bi bi-box-arrow-in-right"></i></a>
            <a class="btn btn-sm btn-outline-secondary" title="Adjuntos" href="index.php?page=project_detail&id=<?=h($p['id'])?>#tab-attachments"><i class="bi bi-paperclip"></i></a>
            <a class="btn btn-sm btn-outline-success" title="Explosión (placeholder)" href="index.php?page=project_report&id=<?=h($p['id'])?>"><i class="bi bi-file-earmark-text"></i></a>

            <form method="post" class="d-inline" onsubmit="return confirm('¿Duplicar proyecto? Se duplican divisiones, áreas (incl. subáreas) y puntos.');">
              <input type="hidden" name="action" value="duplicate_project">
              <input type="hidden" name="id" value="<?=h($p['id'])?>">
              <button class="btn btn-sm btn-outline-warning" title="Duplicar"><i class="bi bi-copy"></i></button>
            </form>

            <?php if($HAS_CLOSED): ?>
            <form method="post" class="d-inline" onsubmit="return confirm('¿Cambiar estado del proyecto?');">
              <input type="hidden" name="action" value="toggle_close">
              <input type="hidden" name="id" value="<?=h($p['id'])?>">
              <button class="btn btn-sm btn-outline-dark" title="<?= $closed?'Reabrir':'Cerrar' ?>">
                <?= $closed ? '<i class="bi bi-unlock"></i>' : '<i class="bi bi-lock"></i>' ?>
              </button>
            </form>
            <?php endif; ?>

            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar proyecto? Solo permitido si NO tiene áreas, puntos ni adjuntos.');">
              <input type="hidden" name="action" value="delete_project">
              <input type="hidden" name="id" value="<?=h($p['id'])?>">
              <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
            </form>

          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?><tr><td colspan="6" class="text-muted text-center py-4">No hay proyectos.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
