<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

function column_exists($table, $col) { return (bool) db_query("SHOW COLUMNS FROM `$table` LIKE ?", [$col])->fetch(); }

$hasBrandsLogo       = column_exists('brands', 'logo_path');
$hasBrandsHasSeries  = column_exists('brands', 'has_series');

$hasSeriesParent     = column_exists('series', 'parent_series_id');
$hasSeriesIsBase     = column_exists('series', 'is_base');
$hasSeriesManId      = column_exists('series', 'manufacturer_series_id');

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

  if ($mime === 'image/png') {
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
  }

  imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

  $root = realpath(__DIR__ . '/../../');
  $dir  = $root . '/uploads/brands';
  if (!is_dir($dir)) {
    if (!@mkdir($dir, 0775, true)) throw new Exception("No se pudo crear /uploads/brands");
  }

  $ext = ($mime === 'image/png') ? 'png' : 'jpg';
  $rel = "uploads/brands/brand_{$brandId}." . $ext;
  $abs = $root . '/' . $rel;

  if ($mime === 'image/png') imagepng($dst, $abs, 6);
  else imagejpeg($dst, $abs, 78);

  imagedestroy($src);
  imagedestroy($dst);

  return $rel;
}

// =======================
// Regla: 1 sola base por familia
// (familia = mismo parent_series_id)
// =======================
function enforce_one_base_per_family(int $parentId, int $keepSeriesId): void {
  if ($parentId <= 0) return;
  db_query("UPDATE series SET is_base=0 WHERE parent_series_id=? AND id<>?", [$parentId, $keepSeriesId]);
}

// =======================
// POST actions
// =======================
if (is_post() && current_user_role() !== 'viewer') {
  $action = post_param('action');

  // Crear marca
  if ($action === 'add_brand') {
    $name = trim(post_param('name'));
    $has_series = isset($_POST['has_series']) ? 1 : 0;

    if ($name === '') {
      set_flash('error', 'El nombre de la marca es obligatorio.');
      redirect('index.php?page=brands');
    }

    if ($hasBrandsHasSeries) db_query("INSERT INTO brands (name, has_series) VALUES (?,?)", [$name, $has_series]);
    else db_query("INSERT INTO brands (name) VALUES (?)", [$name]);

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

  // Editar marca
  if ($action === 'edit_brand') {
    $id = (int)post_param('id');
    $name = trim(post_param('name'));
    $has_series = isset($_POST['has_series']) ? 1 : 0;

    if ($id <= 0 || $name === '') {
      set_flash('error', 'Marca inválida.');
      redirect('index.php?page=brands');
    }

    if ($hasBrandsHasSeries) db_query("UPDATE brands SET name=?, has_series=? WHERE id=?", [$name, $has_series, $id]);
    else db_query("UPDATE brands SET name=? WHERE id=?", [$name, $id]);

    set_flash('success', 'Marca actualizada.');
    redirect('index.php?page=brands');
  }

  // Cambiar logo marca
  if ($action === 'brand_logo') {
    $id = (int)post_param('id');

    if ($id <= 0) { set_flash('error', 'Marca inválida.'); redirect('index.php?page=brands'); }
    if (!$hasBrandsLogo) { set_flash('error', 'BD no tiene brands.logo_path'); redirect('index.php?page=brands'); }

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

  // Crear serie
  if ($action === 'add_series') {
    $brand_id = (int) post_param('brand_id');
    $name = trim(post_param('name'));
    $manufacturer_series_id = trim(post_param('manufacturer_series_id'));

    $is_family = isset($_POST['is_family']) ? 1 : 0;

    if ($brand_id <= 0 || $name === '') {
      set_flash('error', 'Marca y nombre de serie son obligatorios.');
      redirect('index.php?page=brands');
    }

    if (!$hasSeriesParent || !$hasSeriesIsBase) {
      set_flash('error', 'Faltan columnas en series: parent_series_id e is_base.');
      redirect('index.php?page=brands');
    }

    $parent_series_id = null;
    $is_base = 0;

    if ($is_family) {
      // Padre/familia: no es base y no tiene padre
      $parent_series_id = null;
      $is_base = 0;
    } else {
      // Hija: padre obligatorio
      $parent_series_id = (int) post_param('parent_series_id', 0);
      $is_base = isset($_POST['is_base']) ? 1 : 0;

      if ($parent_series_id <= 0) {
        set_flash('error', 'Debes seleccionar la SERIE PADRE (familia).');
        redirect('index.php?page=brands&brand_filter=' . $brand_id);
      }
    }

    // Insert
    $fields = ['brand_id','name','parent_series_id','is_base'];
    $vals = [$brand_id, $name, $parent_series_id, $is_base];

    if ($hasSeriesManId) { $fields[]='manufacturer_series_id'; $vals[] = ($manufacturer_series_id !== '' ? $manufacturer_series_id : null); }

    $sql = "INSERT INTO series (" . implode(',', $fields) . ")
            VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")";
    db_query($sql, $vals);

    $newId = (int)db_connect()->lastInsertId();

    // Si es hija y base => desmarcar otras hijas base en esa familia
    if (!$is_family && $is_base === 1) {
      enforce_one_base_per_family($parent_series_id, $newId);
    }

    set_flash('success', 'Serie creada.');
    redirect('index.php?page=brands&brand_filter=' . $brand_id);
  }

  // Editar serie
  if ($action === 'edit_series') {
    $id = (int) post_param('id');
    $brand_filter = (int) post_param('brand_filter', 0);

    if ($id <= 0) {
      set_flash('error', 'Serie inválida.');
      redirect('index.php?page=brands' . ($brand_filter ? '&brand_filter='.$brand_filter : ''));
    }

    if (!$hasSeriesParent || !$hasSeriesIsBase) {
      set_flash('error', 'Faltan columnas en series: parent_series_id e is_base.');
      redirect('index.php?page=brands' . ($brand_filter ? '&brand_filter='.$brand_filter : ''));
    }

    $name = trim(post_param('name'));
    $manufacturer_series_id = trim(post_param('manufacturer_series_id'));
    $is_family = isset($_POST['is_family']) ? 1 : 0;

    if ($name === '') {
      set_flash('error', 'Nombre de serie obligatorio.');
      redirect('index.php?page=brands' . ($brand_filter ? '&brand_filter='.$brand_filter : ''));
    }

    // Obtener brand_id actual para validar “padre pertenece a la misma marca”
    $current = db_query("SELECT brand_id FROM series WHERE id=?", [$id])->fetch();
    $brand_id = (int)($current['brand_id'] ?? 0);

    $parent_series_id = null;
    $is_base = 0;

    if ($is_family) {
      $parent_series_id = null;
      $is_base = 0;
    } else {
      $parent_series_id = (int) post_param('parent_series_id', 0);
      $is_base = isset($_POST['is_base']) ? 1 : 0;

      if ($parent_series_id <= 0) {
        set_flash('error', 'Debes seleccionar la SERIE PADRE (familia).');
        redirect('index.php?page=brands' . ($brand_filter ? '&brand_filter='.$brand_filter : ''));
      }

      // Validar que el padre pertenezca a la misma marca
      $p = db_query("SELECT id FROM series WHERE id=? AND brand_id=? AND parent_series_id IS NULL", [$parent_series_id, $brand_id])->fetch();
      if (!$p) {
        set_flash('error', 'Serie padre inválida (debe ser de la misma marca y ser padre/familia).');
        redirect('index.php?page=brands' . ($brand_filter ? '&brand_filter='.$brand_filter : ''));
      }
    }

    // Update
    $sets = ["name=?", "parent_series_id=?", "is_base=?"];
    $vals = [$name, $parent_series_id, $is_base];

    if ($hasSeriesManId) { $sets[] = "manufacturer_series_id=?"; $vals[] = ($manufacturer_series_id !== '' ? $manufacturer_series_id : null); }

    $vals[] = $id;

    db_query("UPDATE series SET " . implode(',', $sets) . " WHERE id=?", $vals);

    // Regla 1 base por familia (si es hija y base)
    if (!$is_family && $is_base === 1) {
      enforce_one_base_per_family($parent_series_id, $id);
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
  $rows = db_query("
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
  foreach ($rows as $r) $mapCounts[(int)$r['id']] = $r;
}

// Familias por marca (series padre: parent_series_id IS NULL)
$familiesByBrand = []; // brand_id => [ [id,name], ...]
if ($hasSeriesParent) {
  $fams = db_query("SELECT id, brand_id, name FROM series WHERE parent_series_id IS NULL ORDER BY name")->fetchAll();
  foreach ($fams as $f) {
    $bid = (int)$f['brand_id'];
    if (!isset($familiesByBrand[$bid])) $familiesByBrand[$bid] = [];
    $familiesByBrand[$bid][] = ['id'=>(int)$f['id'], 'name'=>$f['name']];
  }
}

// Series (filtradas por marca si aplica)
if ($brand_filter > 0) {
  $series = db_query("
    SELECT s.*, b.name AS brand_name,
      p.name AS parent_name
    FROM series s
    JOIN brands b ON b.id=s.brand_id
    LEFT JOIN series p ON p.id=s.parent_series_id
    WHERE s.brand_id=?
    ORDER BY (s.parent_series_id IS NULL) DESC, s.parent_series_id, s.is_base DESC, s.name
  ", [$brand_filter])->fetchAll();
} else {
  $series = db_query("
    SELECT s.*, b.name AS brand_name,
      p.name AS parent_name
    FROM series s
    JOIN brands b ON b.id=s.brand_id
    LEFT JOIN series p ON p.id=s.parent_series_id
    ORDER BY b.name, (s.parent_series_id IS NULL) DESC, s.parent_series_id, s.is_base DESC, s.name
  ")->fetchAll();
}

include __DIR__ . '/../layout/header.php';
?>

<h1 class="h3 mb-3"><i class="bi bi-collection"></i> Marcas y Series</h1>

<?php if (!$hasSeriesParent || !$hasSeriesIsBase): ?>
  <div class="alert alert-warning">
    <strong>Faltan columnas en <code>series</code>:</strong>
    necesitas <code>parent_series_id</code> e <code>is_base</code> para el modelo Padre/Hijas/Base.
  </div>
<?php endif; ?>

<div class="row g-3">
  <!-- ===== Marcas ===== -->
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

  <!-- ===== Series ===== -->
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">Nueva serie</div>
      <div class="card-body">
        <form method="post" class="row g-2" id="frmAddSeries">
          <input type="hidden" name="action" value="add_series">

          <div class="col-md-4">
            <label class="form-label">Marca</label>
            <select name="brand_id" class="form-select" required id="add_brand_id">
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
            <label class="form-label">Nombre serie</label>
            <input type="text" name="name" class="form-control" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">ID fabricante</label>
            <input type="text" name="manufacturer_series_id" class="form-control" placeholder="opcional">
          </div>

          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_family" id="add_is_family">
              <label class="form-check-label" for="add_is_family">Esta serie es PADRE / FAMILIA (ej: ARKÉ, PLANA)</label>
            </div>
            <div class="form-text">Si es familia, no tiene base ni padre.</div>
          </div>

          <div class="col-md-6" id="add_parent_wrap">
            <label class="form-label">Serie padre (familia)</label>
            <select name="parent_series_id" class="form-select" id="add_parent_series_id">
              <option value="">Seleccione...</option>
              <?php
                // mostramos familias solo del brand_filter si existe, si no, quedan vacías al inicio
                $bf = $brand_filter;
                if ($bf > 0 && isset($familiesByBrand[$bf])) {
                  foreach ($familiesByBrand[$bf] as $f) {
                    echo '<option value="'.h($f['id']).'">'.h($f['name']).'</option>';
                  }
                }
              ?>
            </select>
          </div>

          <div class="col-md-6 d-flex align-items-end" id="add_base_wrap">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_base" id="add_is_base">
              <label class="form-check-label" for="add_is_base">Es BASE de su familia (1 sola por familia)</label>
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
              <th style="width:220px;">Padre (Familia)</th>
              <th class="text-center" style="width:80px;">¿Padre?</th>
              <th class="text-center" style="width:80px;">Base</th>
              <th style="width:180px;">ID fabricante</th>
              <th class="text-end" style="width:90px;">Guardar</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($series as $s): ?>
              <?php
                $sid = (int)$s['id'];
                $bid = (int)$s['brand_id'];
                $isFamily = $hasSeriesParent ? (empty($s['parent_series_id']) ? 1 : 0) : 0;
                $parentId = $hasSeriesParent ? (int)($s['parent_series_id'] ?? 0) : 0;
              ?>
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
                  <?php if (!$hasSeriesParent): ?>
                    <span class="text-muted">—</span>
                  <?php else: ?>
                    <select name="parent_series_id" class="form-select form-select-sm parent-select"
                            data-row="<?= h($sid) ?>" <?= $isFamily ? 'disabled' : '' ?>>
                      <option value="">Seleccione...</option>
                      <?php foreach (($familiesByBrand[$bid] ?? []) as $f): ?>
                        <option value="<?= h($f['id']) ?>" <?= ($parentId === (int)$f['id'] ? 'selected' : '') ?>>
                          <?= h($f['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                </td>

                <td class="text-center">
                  <input class="form-check-input is-family" type="checkbox" name="is_family"
                         data-row="<?= h($sid) ?>" <?= $isFamily ? 'checked' : '' ?>>
                </td>

                <td class="text-center">
                  <?php if (!$hasSeriesIsBase): ?>
                    <span class="text-muted">—</span>
                  <?php else: ?>
                    <input class="form-check-input is-base" type="checkbox" name="is_base"
                           data-row="<?= h($sid) ?>"
                           <?= (!$isFamily && (int)($s['is_base'] ?? 0) === 1) ? 'checked' : '' ?>
                           <?= $isFamily ? 'disabled' : '' ?>>
                  <?php endif; ?>
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
              <tr><td colspan="7" class="text-muted text-center py-2">No hay series.</td></tr>
            <?php endif; ?>

          </tbody>
        </table>
      </div>

      <div class="card-footer text-muted small">
        <strong>Reglas:</strong>
        Serie <em>Padre/Familia</em> no tiene base. La base se marca en una <em>hija</em> y solo puede haber <strong>una</strong> base por familia.
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // Nueva serie: si es familia, ocultar padre/base
  const addIsFamily = document.getElementById('add_is_family');
  const addParentWrap = document.getElementById('add_parent_wrap');
  const addBaseWrap = document.getElementById('add_base_wrap');
  const addIsBase = document.getElementById('add_is_base');

  function toggleAdd() {
    if (!addIsFamily) return;
    const isFam = addIsFamily.checked;
    if (addParentWrap) addParentWrap.style.display = isFam ? 'none' : '';
    if (addBaseWrap) addBaseWrap.style.display = isFam ? 'none' : '';
    if (isFam && addIsBase) addIsBase.checked = false;
  }
  if (addIsFamily) {
    addIsFamily.addEventListener('change', toggleAdd);
    toggleAdd();
  }

  // Edit inline: si marca "padre", deshabilitar parent select y base
  document.querySelectorAll('.is-family').forEach(chk=>{
    chk.addEventListener('change', ()=>{
      const row = chk.getAttribute('data-row');
      const sel = document.querySelector('.parent-select[data-row="'+row+'"]');
      const base = document.querySelector('.is-base[data-row="'+row+'"]');
      if (chk.checked) {
        if (sel) { sel.value = ''; sel.disabled = true; }
        if (base) { base.checked = false; base.disabled = true; }
      } else {
        if (sel) sel.disabled = false;
        if (base) base.disabled = false;
      }
    });
  });
})();
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
