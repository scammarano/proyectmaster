<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

function table_exists($name) { return (bool) db_query("SHOW TABLES LIKE ?", [$name])->fetch(); }
function column_exists($table, $col) { return (bool) db_query("SHOW COLUMNS FROM `$table` LIKE ?", [$col])->fetch(); }

$hasBrandsLogo      = column_exists('brands', 'logo_path');
$hasBrandsHasSeries = column_exists('brands', 'has_series');

$hasSeriesIsBase    = column_exists('series', 'is_base');
$hasSeriesManId     = column_exists('series', 'manufacturer_series_id');
$hasSeriesFamilyKey = column_exists('series', 'family_key');

$hasArticleType = (bool) db_query("SHOW COLUMNS FROM articles LIKE 'article_type'")->fetch();

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

  if ($mime === 'image/jpeg') $src = @imagecreatefromjpeg($file['tmp_name']);
  else $src = @imagecreatefrompng($file['tmp_name']);
  if (!$src) throw new Exception("No se pudo leer la imagen (GD).");

  $w = imagesx($src); $h = imagesy($src);

  // logo pequeño (max 160px)
  $maxSide = 160;
  $scale = min($maxSide / max($w, $h), 1.0);
  $nw = (int)round($w * $scale);
  $nh = (int)round($h * $scale);

  $dst = imagecreatetruecolor($nw, $nh);

  // mantener transparencia PNG
  if ($mime === 'image/png') {
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
  }

  imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

  $root = realpath(__DIR__ . '/../../'); // raíz del proyecto
  $dir  = $root . '/uploads/brands';
  if (!is_dir($dir)) {
    if (!@mkdir($dir, 0775, true)) throw new Exception("No se pudo crear /uploads/brands");
  }

  $ext = ($mime === 'image/png') ? 'png' : 'jpg';
  $rel = "uploads/brands/brand_{$brandId}." . $ext;
  $abs = $root . '/' . $rel;

  // guardar comprimido
  if ($mime === 'image/png') imagepng($dst, $abs, 6);
  else imagejpeg($dst, $abs, 78);

  imagedestroy($src);
  imagedestroy($dst);

  return $rel;
}

// =======================
// Reglas negocio: 1 sola base por familia (brand_id + family_key)
// =======================
function enforce_one_base_per_family(int $brandId, string $familyKey, int $keepSeriesId): void {
  if ($brandId <= 0) return;
  $familyKey = trim($familyKey);
  if ($familyKey === '') return;

  db_query(
    "UPDATE series
     SET is_base=0
     WHERE brand_id=? AND family_key=? AND id<>?",
    [$brandId, $familyKey, $keepSeriesId]
  );
}

// =======================
// POST actions (crear + editar)
// =======================
if (is_post() && current_user_role() !== 'viewer') {
  $action = post_param('action');

  // ===== Crear marca =====
  if ($action === 'add_brand') {
    $name = trim(post_param('name'));
    $has_series = isset($_POST['has_series']) ? 1 : 0;

    if ($name === '') {
      set_flash('error', 'El nombre de la marca es obligatorio.');
      redirect('index.php?page=brands');
    }

    if ($hasBrandsHasSeries) {
      db_query("INSERT INTO brands (name, has_series) VALUES (?,?)", [$name, $has_series]);
    } else {
      db_query("INSERT INTO brands (name) VALUES (?)", [$name]);
    }

    $brandId = (int)db_connect()->lastInsertId();

    if ($hasBrandsLogo && isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
      try {
        $path = save_brand_logo($_FILES['logo'], $brandId);
        if ($path) db_query("UPDATE brands SET logo_path=? WHERE id=?", [$path, $brandId]);
      } catch (Exception $e) {
        set_flash('error', 'Marca creada, pero el logo falló: ' . $e->getMessage());
        redirect('index.php?page=brands');
      }
    }

    set_flash('success', 'Marca creada.');
    redirect('index.php?page=brands');
  }

  // ===== Editar marca (nombre + has_series) =====
  if ($action === 'edit_brand') {
    $id = (int)post_param('id');
    $name = trim(post_param('name'));
    $has_series = isset($_POST['has_series']) ? 1 : 0;

    if ($id <= 0 || $name === '') {
      set_flash('error', 'Marca inválida.');
      redirect('index.php?page=brands');
    }

    if ($hasBrandsHasSeries) {
      db_query("UPDATE brands SET name=?, has_series=? WHERE id=?", [$name, $has_series, $id]);
    } else {
      db_query("UPDATE brands SET name=? WHERE id=?", [$name, $id]);
    }

    set_flash('success', 'Marca actualizada.');
    redirect('index.php?page=brands');
  }

  // ===== Cambiar logo de marca =====
  if ($action === 'brand_logo') {
    $id = (int)post_param('id');
    if ($id <= 0) {
      set_flash('error', 'Marca inválida.');
      redirect('index.php?page=brands');
    }
    if (!$hasBrandsLogo) {
      set_flash('error', 'La BD no tiene logo_path en brands.');
      redirect('index.php?page=brands');
    }
    if (!isset($_FILES['logo']) || ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      set_flash('error', 'No seleccionaste un archivo.');
      redirect('index.php?page=brands');
    }

    try {
      $path = save_brand_logo($_FILES['logo'], $id);
      if ($path) db_query("UPDATE brands SET logo_path=? WHERE id=?", [$path, $id]);
      set_flash('success', 'Logo actualizado.');
    } catch (Exception $e) {
      set_flash('error', 'Error actualizando logo: ' . $e->getMessage());
    }
    redirect('index.php?page=brands');
  }

  // ===== Crear serie =====
  if ($action === 'add_series') {
    $brand_id = (int) post_param('brand_id');
    $name = trim(post_param('name'));
    $is_base = isset($_POST['is_base']) ? 1 : 0;
    $man_id = trim(post_param('manufacturer_series_id'));
    $family_key = strtoupper(trim(post_param('family_key')));

    if ($brand_id <= 0 || $name === '') {
      set_flash('error', 'Marca y nombre de serie son obligatorios.');
      redirect('index.php?page=brands');
    }

    if ($hasSeriesFamilyKey && $family_key === '') {
      set_flash('error', 'La familia es obligatoria (ej: ARKE, PLANA, NEVE).');
      redirect('index.php?page=brands&brand_filter=' . $brand_id);
    }

    $fields = ['brand_id','name'];
    $vals = [$brand_id,$name];

    if ($hasSeriesFamilyKey) { $fields[]='family_key'; $vals[]=$family_key; }
    if ($hasSeriesIsBase)    { $fields[]='is_base'; $vals[]=$is_base; }
    if ($hasSeriesManId)     { $fields[]='manufacturer_series_id'; $vals[]=$man_id !== '' ? $man_id : null; }

    $sql = "INSERT INTO series (" . implode(',', $fields) . ")
            VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ")";
    db_query($sql, $vals);

    $newId = (int)db_connect()->lastInsertId();

    // Regla: solo 1 base por familia
    if ($hasSeriesIsBase && $hasSeriesFamilyKey && $is_base === 1) {
      enforce_one_base_per_family($brand_id, $family_key, $newId);
    }

    set_flash('success', 'Serie creada.');
    redirect('index.php?page=brands&brand_filter=' . $brand_id);
  }

  // ===== Editar serie (inline) =====
  if ($action === 'edit_series') {
    $id = (int)post_param('id');
    $brand_filter = (int)post_param('brand_filter', 0);

    $name = trim(post_param('name'));
    $is_base = isset($_POST['is_base']) ? 1 : 0;
    $man_id = trim(post_param('manufacturer_series_id'));
    $family_key = strtoupper(trim(post_param('family_key')));

    if ($id <= 0 || $name === '') {
      set_flash('error', 'Serie inválida.');
      redirect('index.php?page=brands' . ($brand_filter ? '&brand_filter='.$brand_filter : ''));
    }

    // Obtener brand_id actual (para aplicar regla base)
    $row = db_query("SELECT brand_id " . ($hasSeriesFamilyKey ? ", family_key" : "") . " FROM series WHERE id=?", [$id])->fetch();
    $brandId = (int)($row['brand_id'] ?? 0);

    if ($hasSeriesFamilyKey && $family_key === '') {
      set_flash('error', 'La familia es obligatoria (ej: ARKE, PLANA, NEVE).');
      redirect('index.php?page=brands' . ($brand_filter ? '&brand_filter='.$brand_filter : ''));
    }

    $sets = ["name=?"];
    $vals = [$name];

    if ($hasSeriesFamilyKey) { $sets[]="family_key=?"; $vals[]=$family_key; }
    if ($hasSeriesIsBase)    { $sets[]="is_base=?"; $vals[]=$is_base; }
    if ($hasSeriesManId)     { $sets[]="manufacturer_series_id=?"; $vals[]=$man_id !== '' ? $man_id : null; }

    $vals[] = $id;
    db_query("UPDATE series SET ".implode(',', $sets)." WHERE id=?", $vals);

    // Regla: solo 1 base por familia
    if ($hasSeriesIsBase && $hasSeriesFamilyKey && $is_base === 1) {
      enforce_one_base_per_family($brandId, $family_key, $id);
    }

    set_flash('success', 'Serie actualizada.');
    redirect('index.php?page=brands' . ($brand_filter ? '&brand_filter='.$brand_filter : ''));
  }
}

// =======================
// Data + filtros
// =======================
$brand_filter = (int) ($_GET['brand_filter'] ?? 0);

$brands = db_query("SELECT * FROM brands ORDER BY name")->fetchAll();

// Contadores por marca
$mapCounts = [];
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
  foreach ($brandsCounts as $r) $mapCounts[(int)$r['id']] = $r;
}

if ($brand_filter > 0) {
  $series = db_query("
    SELECT s.*, b.name AS brand_name
    FROM series s
    JOIN brands b ON b.id=s.brand_id
    WHERE s.brand_id=?
    ORDER BY s.family_key, s.name
  ", [$brand_filter])->fetchAll();
} else {
  $series = db_query("
    SELECT s.*, b.name AS brand_name
    FROM series s
    JOIN brands b ON b.id=s.brand_id
    ORDER BY b.name, s.family_key, s.name
  ")->fetchAll();
}

include __DIR__ . '/../layout/header.php';
?>

<h1 class="h3 mb-3"><i class="bi bi-collection"></i> Marcas y Series</h1>

<?php if (!$hasSeriesFamilyKey): ?>
  <div class="alert alert-warning">
    <strong>Falta la columna <code>series.family_key</code></strong>.
    Ejecuta: <code>ALTER TABLE series ADD COLUMN family_key VARCHAR(60) NULL AFTER brand_id;</code>
  </div>
<?php endif; ?>

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
      <div class="card-header">Marcas (editar en línea)</div>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
          <thead>
            <tr>
              <th style="width:60px;"></th>
              <th>Marca</th>
              <?php if ($hasBrandsHasSeries): ?><th class="text-center">Lleva series</th><?php endif; ?>
              <?php if ($hasArticleType): ?>
                <th class="text-end">Total</th>
                <th class="text-end">Placas</th>
                <th class="text-end">Soportes</th>
                <th class="text-end">Frutos</th>
              <?php endif; ?>
              <th class="text-end" style="width:110px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($brands as $b): ?>
              <?php
                $id = (int)$b['id'];
                $c = $mapCounts[$id] ?? null;
                $logo = ($hasBrandsLogo ? ($b['logo_path'] ?? '') : '');
              ?>
              <tr>
                <td>
                  <?php if ($logo): ?>
                    <img src="<?= h($logo) ?>" alt="logo" style="width:38px;height:38px;object-fit:contain;">
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>

                <td>
                  <form method="post" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="action" value="edit_brand">
                    <input type="hidden" name="id" value="<?= h($id) ?>">
                    <input type="text" name="name" class="form-control form-control-sm" value="<?= h($b['name']) ?>" required>
                </td>

                <?php if ($hasBrandsHasSeries): ?>
                  <td class="text-center">
                    <input class="form-check-input" type="checkbox" name="has_series" <?= ((int)($b['has_series'] ?? 1)===1?'checked':'') ?>>
                  </td>
                <?php endif; ?>

                <?php if ($hasArticleType): ?>
                  <td class="text-end"><?= h($c['cnt_total'] ?? 0) ?></td>
                  <td class="text-end"><?= h($c['cnt_placa'] ?? 0) ?></td>
                  <td class="text-end"><?= h($c['cnt_soporte'] ?? 0) ?></td>
                  <td class="text-end"><?= h($c['cnt_fruto'] ?? 0) ?></td>
                <?php endif; ?>

                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" title="Guardar">
                    <i class="bi bi-save"></i>
                  </button>
                  </form>

                  <?php if ($hasBrandsLogo): ?>
                    <form method="post" enctype="multipart/form-data" class="d-inline-block ms-1">
                      <input type="hidden" name="action" value="brand_logo">
                      <input type="hidden" name="id" value="<?= h($id) ?>">
                      <label class="btn btn-sm btn-outline-secondary mb-0" title="Cambiar logo">
                        <i class="bi bi-image"></i>
                        <input type="file" name="logo" accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                               style="display:none" onchange="this.form.submit()">
                      </label>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$brands): ?>
              <tr><td colspan="9" class="text-muted text-center py-2">No hay marcas.</td></tr>
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

          <div class="col-md-4">
            <label class="form-label">Serie</label>
            <input type="text" name="name" class="form-control" required>
          </div>

          <div class="col-md-2">
            <label class="form-label">Familia</label>
            <input type="text" name="family_key" class="form-control" placeholder="ARKE" <?= $hasSeriesFamilyKey ? 'required' : '' ?>>
          </div>

          <div class="col-md-2">
            <label class="form-label">ID fabricante</label>
            <input type="text" name="manufacturer_series_id" class="form-control" placeholder="ARKE-GRIS">
          </div>

          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_base" id="is_base">
              <label class="form-check-label" for="is_base">Esta serie es COLOR BASE (única por familia)</label>
            </div>
          </div>

          <div class="col-12 d-flex justify-content-end mt-2">
            <button class="btn btn-success"><i class="bi bi-plus-circle"></i> Crear</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card mb-2">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Series (editar en línea)</span>

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
              <th style="width:120px;">Familia</th>
              <th class="text-center" style="width:90px;">Base</th>
              <th style="width:200px;">ID Fabricante</th>
              <th class="text-end" style="width:90px;">Guardar</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($series as $s): ?>
              <?php $sid = (int)$s['id']; ?>
              <tr>
                <td><?= h($s['brand_name']) ?></td>
                <td>
                  <form method="post" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="action" value="edit_series">
                    <input type="hidden" name="id" value="<?= h($sid) ?>">
                    <input type="hidden" name="brand_filter" value="<?= h($brand_filter) ?>">
                    <input type="text" name="name" class="form-control form-control-sm" value="<?= h($s['name']) ?>" required>
                </td>

                <td>
                  <input type="text" name="family_key" class="form-control form-control-sm"
                         value="<?= h($hasSeriesFamilyKey ? ($s['family_key'] ?? '') : '') ?>"
                         <?= $hasSeriesFamilyKey ? 'required' : '' ?>>
                </td>

                <td class="text-center">
                  <input class="form-check-input" type="checkbox" name="is_base"
                    <?= ($hasSeriesIsBase && (int)($s['is_base'] ?? 0) === 1) ? 'checked' : '' ?>>
                </td>

                <td>
                  <input type="text" name="manufacturer_series_id" class="form-control form-control-sm"
                         value="<?= h($hasSeriesManId ? ($s['manufacturer_series_id'] ?? '') : '') ?>">
                </td>

                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" title="Guardar">
                    <i class="bi bi-save"></i>
                  </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$series): ?>
              <tr><td colspan="6" class="text-muted text-center py-2">No hay series.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="card-footer text-muted small">
        Regla activa: si marcas una serie como <strong>Base</strong>, el sistema desmarca automáticamente cualquier otra base de la misma <strong>Familia</strong> dentro de la misma marca.
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
