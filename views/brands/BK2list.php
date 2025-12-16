<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// Helpers
function table_exists($name) { return (bool) db_query("SHOW TABLES LIKE ?", [$name])->fetch(); }
function column_exists($table, $col) { return (bool) db_query("SHOW COLUMNS FROM `$table` LIKE ?", [$col])->fetch(); }

$hasBrandsLogo     = column_exists('brands', 'logo_path');
$hasBrandsHasSeries= column_exists('brands', 'has_series');

$hasSeriesIsBase   = column_exists('series', 'is_base');
$hasSeriesManId    = column_exists('series', 'manufacturer_series_id');

// =======================
// Imagen: resize + compress
// =======================
function save_brand_logo(array $file, int $brandId): ?string {
  if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
  if (!is_uploaded_file($file['tmp_name'])) return null;

  $maxBytes = 3 * 1024 * 1024; // 3MB
  if (($file['size'] ?? 0) > $maxBytes) throw new Exception("Logo demasiado grande (máx 3MB).");

  $info = @getimagesize($file['tmp_name']);
  if (!$info) throw new Exception("El archivo no es una imagen válida.");

  $mime = $info['mime'] ?? '';
  if (!in_array($mime, ['image/jpeg','image/png'], true)) {
    throw new Exception("Logo debe ser JPG o PNG.");
  }

  // Cargar imagen
  if ($mime === 'image/jpeg') $src = @imagecreatefromjpeg($file['tmp_name']);
  else $src = @imagecreatefrompng($file['tmp_name']);
  if (!$src) throw new Exception("No se pudo leer la imagen (GD).");

  $w = imagesx($src); $h = imagesy($src);

  // Resize a logo pequeño (ej: 160px max)
  $maxSide = 160;
  $scale = min($maxSide / max($w, $h), 1.0);
  $nw = (int)round($w * $scale);
  $nh = (int)round($h * $scale);

  $dst = imagecreatetruecolor($nw, $nh);

  // Preservar transparencia si PNG
  if ($mime === 'image/png') {
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
  }

  imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

  // Destino
  $root = realpath(__DIR__ . '/../../'); // raíz del proyecto
  $dir  = $root . '/uploads/brands';
  if (!is_dir($dir)) {
    if (!@mkdir($dir, 0775, true)) throw new Exception("No se pudo crear /uploads/brands");
  }

  $ext = ($mime === 'image/png') ? 'png' : 'jpg';
  $rel = "uploads/brands/brand_{$brandId}." . $ext;
  $abs = $root . '/' . $rel;

  // Guardar comprimido
  if ($mime === 'image/png') {
    // compresión 0-9 (más = más compresión)
    imagepng($dst, $abs, 6);
  } else {
    // calidad 0-100
    imagejpeg($dst, $abs, 78);
  }

  imagedestroy($src);
  imagedestroy($dst);

  return $rel;
}

// =======================
// Acciones POST
// =======================
if (is_post()) {
  $action = post_param('action');

  // Crear marca (con logo + has_series)
  if ($action === 'add_brand') {
    $name = trim(post_param('name'));
    $has_series = isset($_POST['has_series']) ? 1 : 0;

    if ($name === '') {
      set_flash('error', 'El nombre de la marca es obligatorio.');
      redirect('index.php?page=brands');
    }

    // Insert base
    if ($hasBrandsLogo && $hasBrandsHasSeries) {
      db_query("INSERT INTO brands (name, has_series) VALUES (?,?)", [$name, $has_series]);
    } elseif ($hasBrandsHasSeries) {
      db_query("INSERT INTO brands (name, has_series) VALUES (?,?)", [$name, $has_series]);
    } else {
      db_query("INSERT INTO brands (name) VALUES (?)", [$name]);
    }

    $brandId = (int)db_connect()->lastInsertId();

    // Subir logo si existe
    if ($hasBrandsLogo && isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
      try {
        $path = save_brand_logo($_FILES['logo'], $brandId);
        if ($path) {
          db_query("UPDATE brands SET logo_path=? WHERE id=?", [$path, $brandId]);
        }
      } catch (Exception $e) {
        set_flash('error', 'Marca creada, pero el logo falló: ' . $e->getMessage());
        redirect('index.php?page=brands');
      }
    }

    set_flash('success', 'Marca creada.');
    redirect('index.php?page=brands');
  }

  // Crear serie (con is_base + manufacturer_series_id)
  if ($action === 'add_series') {
    $brand_id = (int) post_param('brand_id');
    $name = trim(post_param('name'));
    $is_base = isset($_POST['is_base']) ? 1 : 0;
    $man_id = trim(post_param('manufacturer_series_id'));

    if ($brand_id <= 0 || $name === '') {
      set_flash('error', 'Marca y nombre de serie son obligatorios.');
      redirect('index.php?page=brands');
    }

    // Inserta según columnas disponibles
    $fields = ['brand_id','name'];
    $vals = [$brand_id,$name];

    if ($hasSeriesIsBase) { $fields[]='is_base'; $vals[]=$is_base; }
    if ($hasSeriesManId)  { $fields[]='manufacturer_series_id'; $vals[]=$man_id !== '' ? $man_id : null; }

    $sql = "INSERT INTO series (" . implode(',', $fields) . ") VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ")";
    db_query($sql, $vals);

    set_flash('success', 'Serie creada.');
    redirect('index.php?page=brands&brand_filter=' . $brand_id);
  }
}

// =======================
// Data + filtros
// =======================

// Filtro de series por marca
$brand_filter = (int) ($_GET['brand_filter'] ?? 0);

$brands = db_query("SELECT * FROM brands ORDER BY name")->fetchAll();

// Contadores por tipo (si existe article_type)
$hasArticleType = (bool) db_query("SHOW COLUMNS FROM articles LIKE 'article_type'")->fetch();
if ($hasArticleType) {
  $brandsCounts = db_query("
    SELECT b.id,
           COALESCE(SUM(a.article_type='placa'),0)      AS cnt_placa,
           COALESCE(SUM(a.article_type='soporte'),0)    AS cnt_soporte,
           COALESCE(SUM(a.article_type='fruto'),0)      AS cnt_fruto,
           COALESCE(SUM(a.article_type='cubretecla'),0) AS cnt_cubretecla,
           COALESCE(SUM(a.article_type='cajetin'),0)    AS cnt_cajetin,
           COUNT(a.id) AS cnt_total
    FROM brands b
    LEFT JOIN articles a ON a.brand_id=b.id
    GROUP BY b.id
  ")->fetchAll();
  $mapCounts = [];
  foreach ($brandsCounts as $r) $mapCounts[(int)$r['id']] = $r;
} else {
  $mapCounts = [];
}

if ($brand_filter > 0) {
  $series = db_query("
    SELECT s.*, b.name AS brand_name
    FROM series s
    JOIN brands b ON b.id=s.brand_id
    WHERE s.brand_id=?
    ORDER BY s.name
  ", [$brand_filter])->fetchAll();
} else {
  $series = db_query("
    SELECT s.*, b.name AS brand_name
    FROM series s
    JOIN brands b ON b.id=s.brand_id
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
        <form method="post" enctype="multipart/form-data" class="row g-2">
          <input type="hidden" name="action" value="add_brand">

          <div class="col-12">
            <label class="form-label">Nombre</label>
            <input type="text" name="name" class="form-control" required>
          </div>

          <div class="col-12">
            <label class="form-label">Logo (PNG/JPG, pequeño)</label>
            <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
            <div class="form-text">Se redimensiona automáticamente a ~160px.</div>
          </div>

          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="has_series" id="has_series" checked>
              <label class="form-check-label" for="has_series">Esta marca maneja series</label>
            </div>
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
              <th style="width:54px;"></th>
              <th>Marca</th>
              <?php if ($hasBrandsHasSeries): ?><th class="text-center">Series</th><?php endif; ?>
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
              <?php
                $c = $mapCounts[(int)$b['id']] ?? null;
                $logo = ($hasBrandsLogo ? ($b['logo_path'] ?? '') : '');
              ?>
              <tr>
                <td>
                  <?php if ($logo): ?>
                    <img src="<?= h($logo) ?>" alt="logo" style="width:36px;height:36px;object-fit:contain;">
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?= h($b['name']) ?></td>
                <?php if ($hasBrandsHasSeries): ?>
                  <td class="text-center">
                    <?php if ((int)($b['has_series'] ?? 1) === 1): ?>
                      <span class="badge text-bg-success">Sí</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary">No</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>

                <?php if ($hasArticleType): ?>
                  <td class="text-end"><?= h($c['cnt_total'] ?? 0) ?></td>
                  <td class="text-end"><?= h($c['cnt_placa'] ?? 0) ?></td>
                  <td class="text-end"><?= h($c['cnt_soporte'] ?? 0) ?></td>
                  <td class="text-end"><?= h($c['cnt_fruto'] ?? 0) ?></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (!$brands): ?>
              <tr><td colspan="7" class="text-muted text-center py-2">No hay marcas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
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
                <?php if ($hasBrandsHasSeries && (int)($b['has_series'] ?? 1) === 0) continue; ?>
                <option value="<?= h($b['id']) ?>" <?= ($brand_filter === (int)$b['id'] ? 'selected' : '') ?>>
                  <?= h($b['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-5">
            <label class="form-label">Nombre de serie</label>
            <input type="text" name="name" class="form-control" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">ID fabricante</label>
            <input type="text" name="manufacturer_series_id" class="form-control" placeholder="ej: ARKE-GRIS">
          </div>

          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_base" id="is_base">
              <label class="form-check-label" for="is_base">Esta serie es COLOR BASE</label>
            </div>
            <div class="form-text">Ej: Arké Gris = base, Arké Blanco/Metal = hermanas.</div>
          </div>

          <div class="col-12 d-flex justify-content-end mt-2">
            <button class="btn btn-success"><i class="bi bi-plus-circle"></i> Crear</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Series</span>

        <form method="get" class="d-flex gap-2 align-items-center">
          <input type="hidden" name="page" value="brands">
          <label class="small text-muted">Filtrar marca</label>
          <select name="brand_filter" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="0">(todas)</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= h($b['id']) ?>" <?= ($brand_filter === (int)$b['id'] ? 'selected' : '') ?>>
                <?= h($b['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Marca</th>
              <th>Serie</th>
              <th class="text-center" style="width:110px;">Base</th>
              <th style="width:180px;">ID Fabricante</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($series as $s): ?>
              <tr>
                <td><?= h($s['brand_name']) ?></td>
                <td><?= h($s['name']) ?></td>
                <td class="text-center">
                  <?php if ($hasSeriesIsBase && (int)($s['is_base'] ?? 0) === 1): ?>
                    <span class="badge text-bg-success">Base</span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?= h($hasSeriesManId ? ($s['manufacturer_series_id'] ?? '') : '') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$series): ?>
              <tr><td colspan="4" class="text-muted text-center py-2">No hay series.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
