<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Detectar columnas para compatibilidad con BD vieja/nueva
$hasSeriesBaseColor = (bool) db_query("SHOW COLUMNS FROM series LIKE 'base_color_code'")->fetch();

// POST actions
if (is_post()) {
    $action = post_param('action');

    if ($action === 'add_brand') {
        $name = trim(post_param('name'));
        if ($name !== '') {
            db_query("INSERT INTO brands (name) VALUES (?)", [$name]);
            set_flash('success', 'Marca creada.');
        } else {
            set_flash('error', 'El nombre de la marca es obligatorio.');
        }
        redirect('index.php?page=brands');
    }

    if ($action === 'add_series') {
        $brand_id = (int) post_param('brand_id');
        $name = trim(post_param('name'));
        $base_color_code = trim(post_param('base_color_code'));

        if ($brand_id <= 0 || $name === '') {
            set_flash('error', 'Marca y nombre de serie son obligatorios.');
            redirect('index.php?page=brands');
        }

        if ($hasSeriesBaseColor) {
            db_query("INSERT INTO series (brand_id, name, base_color_code) VALUES (?,?,?)",
                [$brand_id, $name, $base_color_code !== '' ? $base_color_code : null]
            );
        } else {
            db_query("INSERT INTO series (brand_id, name) VALUES (?,?)", [$brand_id, $name]);
        }

        set_flash('success', 'Serie creada.');
        redirect('index.php?page=brands');
    }
}

// Marcas con contadores de artículos por tipo (si existe article_type)
$hasArticleType = (bool) db_query("SHOW COLUMNS FROM articles LIKE 'article_type'")->fetch();

if ($hasArticleType) {
    $brands = db_query("
        SELECT b.*,
               COALESCE(SUM(a.article_type='placa'),0)      AS cnt_placa,
               COALESCE(SUM(a.article_type='soporte'),0)    AS cnt_soporte,
               COALESCE(SUM(a.article_type='fruto'),0)      AS cnt_fruto,
               COALESCE(SUM(a.article_type='cubretecla'),0) AS cnt_cubretecla,
               COALESCE(SUM(a.article_type='cajetin'),0)    AS cnt_cajetin,
               COUNT(a.id) AS cnt_total
        FROM brands b
        LEFT JOIN articles a ON a.brand_id = b.id
        GROUP BY b.id
        ORDER BY b.name
    ")->fetchAll();
} else {
    $brands = db_query("SELECT * FROM brands ORDER BY name")->fetchAll();
}

// Series
if ($hasSeriesBaseColor) {
    $series = db_query("
        SELECT s.*, b.name AS brand_name
        FROM series s
        JOIN brands b ON b.id = s.brand_id
        ORDER BY b.name, s.name
    ")->fetchAll();
} else {
    $series = db_query("
        SELECT s.id, s.brand_id, s.name, b.name AS brand_name
        FROM series s
        JOIN brands b ON b.id = s.brand_id
        ORDER BY b.name, s.name
    ")->fetchAll();
}

include __DIR__ . '/../layout/header.php';
?>

<h1 class="h3 mb-3"><i class="bi bi-collection"></i> Marcas y Series</h1>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header">Nueva marca</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="add_brand">
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
      <div class="card-header">Marcas</div>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Marca</th>
              <?php if ($hasArticleType): ?>
                <th class="text-end">Total</th>
                <th class="text-end">Placas</th>
                <th class="text-end">Soportes</th>
                <th class="text-end">Frutos</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($brands as $b): ?>
              <tr>
                <td><?= h($b['name']) ?></td>
                <?php if ($hasArticleType): ?>
                  <td class="text-end"><?= h($b['cnt_total']) ?></td>
                  <td class="text-end"><?= h($b['cnt_placa']) ?></td>
                  <td class="text-end"><?= h($b['cnt_soporte']) ?></td>
                  <td class="text-end"><?= h($b['cnt_fruto']) ?></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (!$brands): ?>
              <tr><td colspan="<?= $hasArticleType ? 5 : 1 ?>" class="text-muted text-center py-2">No hay marcas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($hasArticleType): ?>
      <div class="card-footer small text-muted">
        * Los contadores se calculan desde <code>articles.article_type</code>.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">Nueva serie</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="add_series">
          <div class="col-md-4">
            <label class="form-label">Marca</label>
            <select name="brand_id" class="form-select" required>
              <option value="">Seleccione</option>
              <?php foreach ($brands as $b): ?>
                <option value="<?= h($b['id']) ?>"><?= h($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label">Nombre de serie</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <?php if ($hasSeriesBaseColor): ?>
          <div class="col-md-3">
            <label class="form-label">Color base</label>
            <input type="text" name="base_color_code" class="form-control" placeholder="#FFFFFF">
          </div>
          <?php endif; ?>
          <div class="col-12 d-flex justify-content-end mt-2">
            <button class="btn btn-success"><i class="bi bi-plus-circle"></i> Crear</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Series</div>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Marca</th>
              <th>Serie</th>
              <?php if ($hasSeriesBaseColor): ?><th>Color base</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($series as $s): ?>
              <tr>
                <td><?= h($s['brand_name']) ?></td>
                <td><?= h($s['name']) ?></td>
                <?php if ($hasSeriesBaseColor): ?>
                  <td><?= h($s['base_color_code'] ?? '') ?></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (!$series): ?>
              <tr><td colspan="<?= $hasSeriesBaseColor ? 3 : 2 ?>" class="text-muted text-center py-2">No hay series aún.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
