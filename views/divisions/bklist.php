<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (is_post()) {
  $action = post_param('action');

  if ($action === 'add_division') {
    $name = trim(post_param('name'));
    if ($name !== '') {
      db_query("INSERT INTO divisions (name) VALUES (?)", [$name]);
      set_flash('success', 'División creada.');
    } else {
      set_flash('error', 'El nombre de la división es obligatorio.');
    }
    redirect('index.php?page=divisions');
  }

  if ($action === 'assign_brand') {
    $division_id = (int) post_param('division_id');
    $brand_id    = (int) post_param('brand_id');
    if ($division_id > 0 && $brand_id > 0) {
      db_query("INSERT IGNORE INTO division_brands (division_id, brand_id) VALUES (?,?)", [$division_id, $brand_id]);
      set_flash('success', 'Marca asignada a la división.');
    } else {
      set_flash('error', 'Selecciona división y marca.');
    }
    redirect('index.php?page=divisions');
  }

  if ($action === 'remove_brand') {
    $division_id = (int) post_param('division_id');
    $brand_id    = (int) post_param('brand_id');
    db_query("DELETE FROM division_brands WHERE division_id=? AND brand_id=?", [$division_id, $brand_id]);
    set_flash('success', 'Marca removida de la división.');
    redirect('index.php?page=divisions');
  }
}

$divisions = db_query("SELECT * FROM divisions ORDER BY name")->fetchAll();
$brands    = db_query("SELECT * FROM brands ORDER BY name")->fetchAll();

$map = db_query("
  SELECT db.division_id, b.id AS brand_id, b.name AS brand_name
  FROM division_brands db
  JOIN brands b ON b.id = db.brand_id
  ORDER BY b.name
")->fetchAll();

$brandsByDivision = [];
foreach ($map as $row) {
  $brandsByDivision[$row['division_id']][] = $row;
}

include __DIR__ . '/../layout/header.php';
?>

<h1 class="h3 mb-3"><i class="bi bi-diagram-2"></i> Divisiones y Marcas</h1>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">Nueva división</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="add_division">
          <div class="col-12">
            <label class="form-label">Nombre</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-12 d-flex justify-content-end mt-2">
            <button class="btn btn-success"><i class="bi bi-plus-circle"></i> Crear</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Asignar marca a división</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="assign_brand">
          <div class="col-12">
            <label class="form-label">División</label>
            <select name="division_id" class="form-select" required>
              <option value="">Seleccione</option>
              <?php foreach ($divisions as $d): ?>
                <option value="<?= h($d['id']) ?>"><?= h($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Marca</label>
            <select name="brand_id" class="form-select" required>
              <option value="">Seleccione</option>
              <?php foreach ($brands as $b): ?>
                <option value="<?= h($b['id']) ?>"><?= h($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 d-flex justify-content-end mt-2">
            <button class="btn btn-primary"><i class="bi bi-link-45deg"></i> Asignar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Divisiones</div>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>División</th>
              <th>Marcas</th>
              <th class="text-end">Acción</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($divisions as $d): ?>
            <tr>
              <td style="width: 200px;"><strong><?= h($d['name']) ?></strong></td>
              <td>
                <?php $rows = $brandsByDivision[$d['id']] ?? []; ?>
                <?php if (!$rows): ?>
                  <span class="text-muted">Sin marcas asignadas</span>
                <?php else: ?>
                  <?php foreach ($rows as $r): ?>
                    <span class="badge text-bg-secondary me-1">
                      <?= h($r['brand_name']) ?>
                    </span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <?php if (!empty($rows)): ?>
                  <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                    <?php foreach ($rows as $r): ?>
                      <form method="post" onsubmit="return confirm('¿Quitar <?= h($r['brand_name']) ?> de <?= h($d['name']) ?>?');">
                        <input type="hidden" name="action" value="remove_brand">
                        <input type="hidden" name="division_id" value="<?= h($d['id']) ?>">
                        <input type="hidden" name="brand_id" value="<?= h($r['brand_id']) ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                      </form>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$divisions): ?>
            <tr><td colspan="3" class="text-muted text-center py-3">No hay divisiones.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
