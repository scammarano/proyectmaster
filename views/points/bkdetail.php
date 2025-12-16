<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rules.php';

require_login();

$point_id = (int)get_param('id', 0);
$area_id  = (int)get_param('area_id', 0);

// Cargar punto / area / proyecto
$point = null;
if ($point_id > 0) {
  $point = db_query("SELECT * FROM points WHERE id=?", [$point_id])->fetch();
  if (!$point) die('Punto no encontrado');
  $area_id = (int)($point['area_id'] ?? 0);
}
$area = null;
if ($area_id > 0) $area = db_query("SELECT * FROM areas WHERE id=?", [$area_id])->fetch();
if (!$area) die('Área no encontrada');

$project_id = (int)$area['project_id'];
$project = db_query("SELECT * FROM projects WHERE id=?", [$project_id])->fetch();
if (!$project) die('Proyecto no encontrado');

$HAS_CLOSED = (bool)db_query("SHOW COLUMNS FROM projects LIKE 'is_closed'")->fetch();
$closed = $HAS_CLOSED ? ((int)($project['is_closed'] ?? 0)===1) : false;

$divisions = db_query("SELECT id, name, prefix FROM divisions ORDER BY name")->fetchAll();

function get_division_row(int $division_id) {
  return db_query("SELECT * FROM divisions WHERE id=?", [$division_id])->fetch();
}

function next_point_code(int $area_id, int $division_id): array {
  $div = get_division_row($division_id);
  if (!$div) return ['seq'=>null,'code'=>null,'prefix'=>''];
  $prefix = trim((string)($div['prefix'] ?? ''));
  // Autonumeración por área + división
  $next = (int)db_query("SELECT COALESCE(MAX(seq),0)+1 AS n FROM points WHERE area_id=? AND division_id=?", [$area_id,$division_id])->fetch()['n'];
  $code = $prefix . str_pad((string)$next, 2, '0', STR_PAD_LEFT);
  return ['seq'=>$next,'code'=>$code,'prefix'=>$prefix];
}

$errors = [];
$success = false;

if (is_post() && !$closed) {
  $division_id = (int)post_param('division_id', 0);
  $name = trim((string)post_param('name',''));
  $location = trim((string)post_param('location',''));
  $notes = trim((string)post_param('notes',''));

  if ($division_id <= 0) $errors[] = 'Selecciona una división.';
  if ($name === '') $name = 'Punto';

  // Cargar reglas del wizard
  $divRow = $division_id ? get_division_row($division_id) : null;
  $ruleKey = $divRow ? ($divRow['code'] ?? $divRow['name'] ?? $division_id) : $division_id;
  $rules = rules_points_for_division($ruleKey);

  // Guardar
  if (!$errors) {
    if ($point_id <= 0) {
      $auto = next_point_code($area_id, $division_id);
      if (!$auto['seq'] || !$auto['code']) $errors[] = 'No se pudo generar código del punto (prefijo / división).';

      if (!$errors) {
        db_query("INSERT INTO points (project_id, area_id, division_id, seq, code, name, location, notes, created_at)
                  VALUES (?,?,?,?,?,?,?,?,NOW())",
                [$project_id, $area_id, $division_id, $auto['seq'], $auto['code'], $name, $location, $notes]);
        $newId = (int)db_connect()->lastInsertId();
        user_log('point_create','point',$newId,'Creó punto '.$auto['code'].' en área '.$area_id);
        add_flash('success','Punto creado: '.$auto['code']);
        redirect('index.php?page=area_detail&id='.$area_id);
      }
    } else {
      // Editar: NO cambiamos seq/code automáticamente (para no romper nomenclatura),
      // salvo que el usuario haya cambiado la división explícitamente y marque "recalcular".
      $recalc = (int)post_param('recalc_code',0)===1;

      $old_div = (int)($point['division_id'] ?? 0);
      $new_div = $division_id;

      $seq = $point['seq'] ?? null;
      $code = $point['code'] ?? null;

      if ($recalc || ($old_div!==$new_div && (!$seq || !$code))) {
        $auto = next_point_code($area_id, $new_div);
        $seq = $auto['seq'];
        $code = $auto['code'];
      }

      db_query("UPDATE points
                SET division_id=?, seq=?, code=?, name=?, location=?, notes=?
                WHERE id=?",
              [$new_div, $seq, $code, $name, $location, $notes, $point_id]);

      user_log('point_update','point',$point_id,'Editó punto '.$code);
      add_flash('success','Punto actualizado.');
      redirect('index.php?page=area_detail&id='.$area_id);
    }
  } else {
    add_flash('error', implode(' ', $errors));
  }
}

// Valores para pintar form
$val_division = (int)($point['division_id'] ?? 0);
$val_name = $point['name'] ?? '';
$val_location = $point['location'] ?? '';
$val_notes = $point['notes'] ?? '';
$val_code = $point['code'] ?? '';

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('<?= $point_id>0 ? "Editar punto" : "Nuevo punto" ?>', `
  <a class="btn btn-sm btn-outline-secondary" href="index.php?page=area_detail&id=<?=h($area_id)?>">
    <i class="bi bi-arrow-left"></i> Volver al área
  </a>
`);
</script>

<?php if($closed): ?>
  <div class="alert alert-warning"><i class="bi bi-lock"></i> Proyecto cerrado: no se permiten cambios.</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h1 class="h4 mb-0"><?= $point_id>0 ? 'Punto '.h($val_code ?: ('#'.$point_id)) : 'Crear punto' ?></h1>
  <div class="text-muted small">
    Área: <a href="index.php?page=area_detail&id=<?=h($area_id)?>"><?=h($area['name'] ?? '')?></a> ·
    Proyecto: <a href="index.php?page=project_detail&id=<?=h($project_id)?>"><?=h($project['name'] ?? '')?></a>
  </div>
</div>

<form method="post" class="card">
  <div class="card-body row g-3">

    <div class="col-md-3">
      <label class="form-label">División *</label>
      <select class="form-select" name="division_id" required <?= $closed?'disabled':'' ?>>
        <option value="">(selecciona)</option>
        <?php foreach($divisions as $d): ?>
          <option value="<?=h($d['id'])?>" <?= ((int)$d['id']===$val_division?'selected':'') ?>>
            <?=h(($d['prefix']?($d['prefix'].' — '):'').$d['name'])?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">La división define reglas, prefijo y catálogo permitido.</div>
    </div>

    <div class="col-md-3">
      <label class="form-label">Código</label>
      <input class="form-control" value="<?=h($val_code ?: 'Se generará al guardar')?>" disabled>
      <?php if($point_id>0): ?>
        <div class="form-text">
          <label class="form-check-label">
            <input class="form-check-input" type="checkbox" name="recalc_code" value="1">
            Recalcular código (solo si cambiaste división)
          </label>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-md-6">
      <label class="form-label">Nombre / descripción</label>
      <input class="form-control" name="name" value="<?=h($val_name)?>" <?= $closed?'disabled':'' ?>>
    </div>

    <div class="col-md-6">
      <label class="form-label">Ubicación (dentro del área)</label>
      <input class="form-control" name="location" value="<?=h($val_location)?>" placeholder="Ej: Entrada, mesita, techo..." <?= $closed?'disabled':'' ?>>
    </div>

    <div class="col-md-12">
      <label class="form-label">Notas</label>
      <textarea class="form-control" name="notes" rows="3" <?= $closed?'disabled':'' ?>><?=h($val_notes)?></textarea>
    </div>

    <hr class="my-2">

    <div class="col-12">
      <div class="alert alert-info mb-0">
        <strong>Siguiente paso (núcleo Vimar):</strong>
        este formulario ya arranca con <em>División → prefijo → autonumeración</em>.
        En el próximo patch enchufamos la construcción:
        <em>Soporte → Frutos → Placa</em> con validación de módulos/cubretecla/ciegos,
        usando las reglas del wizard.
      </div>
    </div>

  </div>
  <div class="card-footer d-flex gap-2">
    <button class="btn btn-primary" <?= $closed?'disabled':'' ?>><i class="bi bi-save"></i> Guardar</button>
    <a class="btn btn-outline-secondary" href="index.php?page=area_detail&id=<?=h($area_id)?>">Cancelar</a>
  </div>
</form>

<?php include __DIR__ . '/../layout/footer.php'; ?>
