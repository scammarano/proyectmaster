
<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$brands = db_query("SELECT * FROM brands ORDER BY name")->fetchAll();
$series = db_query("SELECT s.*, b.name AS brand_name FROM series s JOIN brands b ON b.id = s.brand_id ORDER BY b.name, s.name")->fetchAll();

if (is_post() && post_param('action') === 'create') {
    $name = trim(post_param('name'));
    $client = trim(post_param('client'));
    $address = trim(post_param('address'));
    $desc = trim(post_param('description'));
    $default_brand_id = post_param('default_brand_id') ? (int) post_param('default_brand_id') : null;
    $default_series_id = post_param('default_series_id') ? (int) post_param('default_series_id') : null;

    if ($name !== '') {
        db_query("INSERT INTO projects (name, description, client, address, default_brand_id, default_series_id)
                  VALUES (?,?,?,?,?,?)",
                  [$name, $desc, $client, $address, $default_brand_id, $default_series_id]);
        redirect('index.php?page=projects');
    }
}

$projects = db_query("SELECT p.*, b.name AS brand_name, s.name AS series_name
                      FROM projects p
                      LEFT JOIN brands b ON b.id = p.default_brand_id
                      LEFT JOIN series s ON s.id = p.default_series_id
                      ORDER BY p.id DESC")->fetchAll();

include __DIR__ . '/../layout/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1><i class="bi bi-diagram-3"></i> Proyectos</h1>
  <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#newProject">
    <i class="bi bi-plus-circle"></i> Nuevo proyecto
  </button>
</div>

<div class="collapse mb-4" id="newProject">
  <div class="card card-body">
    <form method="post">
      <input type="hidden" name="action" value="create">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre del proyecto</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Cliente</label>
          <input type="text" name="client" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label">Dirección</label>
          <input type="text" name="address" class="form-control">
        </div>
      </div>
      <div class="row g-3 mt-2">
        <div class="col-md-6">
          <label class="form-label">Marca por defecto</label>
          <select name="default_brand_id" class="form-select">
            <option value="">(ninguna)</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= h($b['id']) ?>"><?= h($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Serie por defecto</label>
          <select name="default_series_id" class="form-select">
            <option value="">(ninguna)</option>
            <?php foreach ($series as $s): ?>
              <option value="<?= h($s['id']) ?>">
                [<?= h($s['brand_name']) ?>] <?= h($s['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mt-3">
        <label class="form-label">Descripción</label>
        <textarea name="description" class="form-control" rows="2"></textarea>
      </div>
      <button type="submit" class="btn btn-success mt-3">
        <i class="bi bi-check-lg"></i> Guardar
      </button>
    </form>
  </div>
</div>

<table class="table table-striped align-middle">
  <thead>
    <tr>
      <th>ID</th><th>Nombre</th><th>Cliente</th><th>Marca/Serie por defecto</th><th>Acciones</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($projects as $p): ?>
    <tr>
      <td><?= h($p['id']) ?></td>
      <td><?= h($p['name']) ?></td>
      <td><?= h($p['client']) ?></td>
      <td>
        <?php if ($p['brand_name'] || $p['series_name']): ?>
          <?= h($p['brand_name']) ?> <?= $p['series_name'] ? ' / ' . h($p['series_name']) : '' ?>
        <?php else: ?>
          <span class="text-muted">No definido</span>
        <?php endif; ?>
      </td>
      <td>
        <a href="index.php?page=project_detail&id=<?= h($p['id']) ?>" class="btn btn-sm btn-primary">
          <i class="bi bi-folder2-open"></i> Abrir
        </a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php include __DIR__ . '/../layout/footer.php'; ?>
