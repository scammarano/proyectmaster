<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();

/** Helpers de esquema */
function col_exists($table, $col) {
  return (bool) db_query("SHOW COLUMNS FROM `$table` LIKE ?", [$col])->fetch();
}

/** Flags (compatibilidad con tu BD real) */
$HAS_BRAND_LOGO      = col_exists('brands', 'logo_path');
$HAS_BRAND_HAS_SERIES= col_exists('brands', 'has_series');
$HAS_BRAND_ACTIVE    = col_exists('brands', 'is_active');

$HAS_SER_PARENT      = col_exists('series', 'parent_series_id');
$HAS_SER_IS_BASE     = col_exists('series', 'is_base');
$HAS_SER_MAN_ID      = col_exists('series', 'manufacturer_series_id');
$HAS_SER_ACTIVE      = col_exists('series', 'is_active');

$HAS_ARTICLES        = table_exists('articles');
$HAS_ARTICLE_TYPE    = $HAS_ARTICLES && col_exists('articles', 'article_type');
$HAS_ARTICLE_BRANDID = $HAS_ARTICLES && col_exists('articles', 'brand_id');
$HAS_ARTICLE_SERIESID= $HAS_ARTICLES && col_exists('articles', 'series_id');

/** ===============================
 *  Logo upload: resize + compress
 *  =============================== */
function save_brand_logo(array $file, int $brandId): ?string {
  if (!isset($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
  if (!is_uploaded_file($file['tmp_name'])) return null;

  $maxBytes = 3 * 1024 * 1024;
  if (($file['size'] ?? 0) > $maxBytes) throw new Exception("Logo demasiado grande (máx 3MB).");

  $info = @getimagesize($file['tmp_name']);
  if (!$info) throw new Exception("El archivo no es una imagen válida.");
  $mime = $info['mime'] ?? '';
  if (!in_array($mime, ['image/jpeg','image/png'], true)) throw new Exception("Logo debe ser JPG o PNG.");

  if (!function_exists('imagecreatefrompng')) throw new Exception("GD no disponible en el servidor (PHP-GD).");

  $src = ($mime === 'image/jpeg') ? @imagecreatefromjpeg($file['tmp_name']) : @imagecreatefrompng($file['tmp_name']);
  if (!$src) throw new Exception("No se pudo leer la imagen.");

  $w = imagesx($src); $h = imagesy($src);
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

/** ===============================
 *  Validación: 1 base por familia
 *  familia = mismo parent_series_id
 *  =============================== */
function enforce_one_base_per_family(int $parentId, int $keepSeriesId): void {
  if ($parentId <= 0) return;
  db_query("UPDATE series SET is_base=0 WHERE parent_series_id=? AND id<>?", [$parentId, $keepSeriesId]);
}

/** ===============================
 *  Validación de uso (para borrar)
 *  =============================== */
function brand_usage_counts(int $brandId): array {
  $seriesCnt = (int) db_query("SELECT COUNT(*) c FROM series WHERE brand_id=?", [$brandId])->fetch()['c'];
  $articlesCnt = 0;
  if (table_exists('articles') && col_exists('articles','brand_id')) {
    $articlesCnt = (int) db_query("SELECT COUNT(*) c FROM articles WHERE brand_id=?", [$brandId])->fetch()['c'];
  }
  return ['series'=>$seriesCnt, 'articles'=>$articlesCnt];
}
function series_usage_counts(int $seriesId): array {
  $childrenCnt = 0;
  if (col_exists('series','parent_series_id')) {
    $childrenCnt = (int) db_query("SELECT COUNT(*) c FROM series WHERE parent_series_id=?", [$seriesId])->fetch()['c'];
  }
  $articlesCnt = 0;
  if (table_exists('articles') && col_exists('articles','series_id')) {
    $articlesCnt = (int) db_query("SELECT COUNT(*) c FROM articles WHERE series_id=?", [$seriesId])->fetch()['c'];
  }
  return ['children'=>$childrenCnt, 'articles'=>$articlesCnt];
}
function set_inactive($table, int $id): void {
  if (!col_exists($table,'is_active')) throw new Exception("La tabla {$table} no tiene is_active.");
  db_query("UPDATE `$table` SET is_active=0 WHERE id=?", [$id]);
}
function hard_delete($table, int $id): void {
  db_query("DELETE FROM `$table` WHERE id=?", [$id]);
}

/** ===============================
 *  POST actions
 *  =============================== */
if (is_post() && function_exists('current_user_role') ? (current_user_role() !== 'viewer') : true) {
  $action = post_param('action');

  // Crear marca
  if ($action === 'add_brand') {
    $name = trim(post_param('name'));
    $has_series = isset($_POST['has_series']) ? 1 : 0;
    if ($name === '') { set_flash('error', 'El nombre de la marca es obligatorio.'); redirect('index.php?page=brands'); }

    if ($HAS_BRAND_HAS_SERIES && $HAS_BRAND_ACTIVE) {
      db_query("INSERT INTO brands (name, has_series, is_active) VALUES (?,?,1)", [$name, $has_series]);
    } elseif ($HAS_BRAND_HAS_SERIES) {
      db_query("INSERT INTO brands (name, has_series) VALUES (?,?)", [$name, $has_series]);
    } elseif ($HAS_BRAND_ACTIVE) {
      db_query("INSERT INTO brands (name, is_active) VALUES (?,1)", [$name]);
    } else {
      db_query("INSERT INTO brands (name) VALUES (?)", [$name]);
    }

    $brandId = (int)db_connect()->lastInsertId();

    if ($HAS_BRAND_LOGO && isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
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

  // Actualizar logo marca
  if ($action === 'brand_logo') {
    $id = (int)post_param('id');
    if ($id <= 0) { set_flash('error', 'Marca inválida.'); redirect('index.php?page=brands'); }
    if (!$HAS_BRAND_LOGO) { set_flash('error', 'BD no tiene brands.logo_path'); redirect('index.php?page=brands'); }
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
        redirect('index.php?page=brands&brand_filter=' . $brand_id);
      }
    }

    $fields = ['brand_id','name'];
    $vals = [$brand_id, $name];

    if ($HAS_SER_PARENT) { $fields[]='parent_series_id'; $vals[]=$parent_series_id; }
    if ($HAS_SER_IS_BASE) { $fields[]='is_base'; $vals[]=$is_base; }
    if ($HAS_SER_MAN_ID)  { $fields[]='manufacturer_series_id'; $vals[]=($manufacturer_series_id !== '' ? $manufacturer_series_id : null); }
    if ($HAS_SER_ACTIVE)  { $fields[]='is_active'; $vals[]=1; }

    $sql = "INSERT INTO series (" . implode(',', $fields) . ") VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ")";
    db_query($sql, $vals);

    $newId = (int)db_connect()->lastInsertId();

    if (!$is_family && $is_base === 1 && $HAS_SER_PARENT && $HAS_SER_IS_BASE) {
      enforce_one_base_per_family($parent_series_id, $newId);
    }

    set_flash('success', 'Serie creada.');
    redirect('index.php?page=brands&brand_filter=' . $brand_id);
  }

  // Eliminar marca (hard si se puede / soft si está en uso)
  if ($action === 'delete_brand') {
    $id = (int)post_param('id');
    if ($id <= 0) { set_flash('error','Marca inválida.'); redirect('index.php?page=brands'); }
    $u = brand_usage_counts($id);

    if ($u['series'] === 0 && $u['articles'] === 0) {
      hard_delete('brands',$id);
      set_flash('success','Marca eliminada.');
    } else {
      if ($HAS_BRAND_ACTIVE) {
        set_inactive('brands',$id);
        set_flash('success','Marca desactivada (no se puede eliminar): tiene '.$u['series'].' series y '.$u['articles'].' artículos.');
      } else {
        set_flash('error','No se puede eliminar: tiene '.$u['series'].' series y '.$u['articles'].' artículos. Agrega brands.is_active para desactivar.');
      }
    }
    redirect('index.php?page=brands');
  }

  // Eliminar serie (hard si se puede / soft si está en uso)
  if ($action === 'delete_series') {
    $id = (int)post_param('id');
    $brand_filter = (int)post_param('brand_filter', 0);
    if ($id <= 0) { set_flash('error','Serie inválida.'); redirect('index.php?page=brands'); }
    $u = series_usage_counts($id);

    if ($u['children'] === 0 && $u['articles'] === 0) {
      hard_delete('series',$id);
      set_flash('success','Serie eliminada.');
    } else {
      if ($HAS_SER_ACTIVE) {
        set_inactive('series',$id);
        set_flash('success','Serie desactivada (no se puede eliminar): tiene '.$u['children'].' hijas y '.$u['articles'].' artículos.');
      } else {
        set_flash('error','No se puede eliminar: tiene '.$u['children'].' hijas y '.$u['articles'].' artículos. Agrega series.is_active para desactivar.');
      }
    }
    redirect('index.php?page=brands' . ($brand_filter ? '&brand_filter='.$brand_filter : ''));
  }

  // Guardar cambios masivos
  if ($action === 'bulk_save') {
    $brand_filter = (int)post_param('brand_filter', 0);

    // Brands bulk
    if (isset($_POST['brand_name']) && is_array($_POST['brand_name'])) {
      foreach ($_POST['brand_name'] as $bid => $bname) {
        $bid = (int)$bid;
        $bname = trim((string)$bname);
        if ($bid <= 0 || $bname === '') continue;

        $has_series = ($HAS_BRAND_HAS_SERIES && isset($_POST['brand_has_series'][$bid])) ? 1 : 0;
        $is_active  = ($HAS_BRAND_ACTIVE && isset($_POST['brand_is_active'][$bid])) ? 1 : 0;

        $sets = ["name=?"];
        $vals = [$bname];

        if ($HAS_BRAND_HAS_SERIES) { $sets[]="has_series=?"; $vals[]=$has_series; }
        if ($HAS_BRAND_ACTIVE) { $sets[]="is_active=?"; $vals[]=$is_active; }

        $vals[] = $bid;
        db_query("UPDATE brands SET ".implode(',', $sets)." WHERE id=?", $vals);
      }
    }

    // Series bulk
    if (isset($_POST['series_name']) && is_array($_POST['series_name'])) {
      foreach ($_POST['series_name'] as $sid => $sname) {
        $sid = (int)$sid;
        $sname = trim((string)$sname);
        if ($sid <= 0 || $sname === '') continue;

        $is_family = isset($_POST['series_is_family'][$sid]) ? 1 : 0;

        $parent_id = null;
        $is_base = 0;
        if (!$is_family) {
          $parent_id = (int)($_POST['series_parent'][$sid] ?? 0);
          $is_base   = ($HAS_SER_IS_BASE && isset($_POST['series_is_base'][$sid])) ? 1 : 0;
          if ($parent_id <= 0) continue; // no guardamos si falta padre
        }

        $man = $HAS_SER_MAN_ID ? trim((string)($_POST['series_manufacturer_id'][$sid] ?? '')) : '';
        $is_active = ($HAS_SER_ACTIVE && isset($_POST['series_is_active'][$sid])) ? 1 : 0;

        $sets = ["name=?"];
        $vals = [$sname];

        if ($HAS_SER_PARENT) { $sets[]="parent_series_id=?"; $vals[]=$is_family ? null : $parent_id; }
        if ($HAS_SER_IS_BASE) { $sets[]="is_base=?"; $vals[]=$is_family ? 0 : $is_base; }
        if ($HAS_SER_MAN_ID) { $sets[]="manufacturer_series_id=?"; $vals[] = ($man !== '' ? $man : null); }
        if ($HAS_SER_ACTIVE) { $sets[]="is_active=?"; $vals[] = $is_active; }

        $vals[] = $sid;
        db_query("UPDATE series SET ".implode(',', $sets)." WHERE id=?", $vals);

        if (!$is_family && $HAS_SER_PARENT && $HAS_SER_IS_BASE && $is_base === 1) {
          enforce_one_base_per_family($parent_id, $sid);
        }
      }
    }

    set_flash('success', 'Cambios guardados.');
    redirect('index.php?page=brands' . ($brand_filter ? '&brand_filter='.$brand_filter : ''));
  }
}

/** ===============================
 *  Datos + filtros
 *  =============================== */
$brand_filter  = (int) ($_GET['brand_filter'] ?? 0);
$show_inactive = isset($_GET['show_inactive']) ? 1 : 0;

$whereBrands = [];
if ($HAS_BRAND_ACTIVE && !$show_inactive) $whereBrands[] = "is_active=1";
$brands = db_query(
  "SELECT * FROM brands".($whereBrands ? " WHERE ".implode(" AND ", $whereBrands) : "")." ORDER BY name"
)->fetchAll();

/** Contadores por marca (por tipo) */
$countsByBrand = [];
if ($HAS_ARTICLE_TYPE && $HAS_ARTICLE_BRANDID) {
  $rows = db_query("
    SELECT b.id,
           COUNT(a.id) AS cnt_total,
           COALESCE(SUM(a.article_type='placa'),0)      AS cnt_placa,
           COALESCE(SUM(a.article_type='soporte'),0)    AS cnt_soporte,
           COALESCE(SUM(a.article_type='fruto'),0)      AS cnt_fruto
    FROM brands b
    LEFT JOIN articles a ON a.brand_id=b.id
    GROUP BY b.id
  ")->fetchAll();
  foreach ($rows as $r) $countsByBrand[(int)$r['id']] = $r;
}

/** Familias (series padre) por marca */
$familiesByBrand = [];
if ($HAS_SER_PARENT) {
  $whereFam = ["parent_series_id IS NULL"];
  if ($HAS_SER_ACTIVE && !$show_inactive) $whereFam[]="is_active=1";
  $fams = db_query("SELECT id, brand_id, name FROM series WHERE ".implode(" AND ", $whereFam)." ORDER BY name")->fetchAll();
  foreach ($fams as $f) {
    $bid = (int)$f['brand_id'];
    if (!isset($familiesByBrand[$bid])) $familiesByBrand[$bid] = [];
    $familiesByBrand[$bid][] = ['id'=>(int)$f['id'], 'name'=>$f['name']];
  }
}

/** Series listado */
$whereSeries = [];
$paramsSeries = [];
if ($brand_filter > 0) { $whereSeries[]="s.brand_id=?"; $paramsSeries[]=$brand_filter; }
if ($HAS_SER_ACTIVE && !$show_inactive) { $whereSeries[]="s.is_active=1"; }

$series = db_query("
  SELECT s.*, b.name AS brand_name, p.name AS parent_name
  FROM series s
  JOIN brands b ON b.id=s.brand_id
  LEFT JOIN series p ON p.id=s.parent_series_id
  ".($whereSeries ? "WHERE ".implode(" AND ", $whereSeries) : "")."
  ORDER BY b.name, (s.parent_series_id IS NULL) DESC, s.parent_series_id, s.is_base DESC, s.name
", $paramsSeries)->fetchAll();

include __DIR__ . '/../layout/header.php';
?>

<h1 class="h3 mb-3"><i class="bi bi-collection"></i> Marcas y Series</h1>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="index.php?page=brands<?= $show_inactive? '' : '&show_inactive=1' ?>">
      <?= $show_inactive ? 'Ocultar inactivos' : 'Ver inactivos' ?>
    </a>
  </div>

  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary btn-sm" type="button" id="btnEditMode">
      <i class="bi bi-pencil-square"></i> Modo edición
    </button>
  </div>
</div>

<div class="row g-3">

  <!-- ============ COLUMNA IZQ: MARCAS ============ -->
  <div class="col-lg-5">

    <!-- Nueva marca -->
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

          <?php if ($HAS_BRAND_HAS_SERIES): ?>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="has_series" id="has_series" checked>
              <label class="form-check-label" for="has_series">Esta marca maneja series</label>
            </div>
          </div>
          <?php endif; ?>

          <div class="col-12 d-flex justify-content-end mt-2">
            <button class="btn btn-success"><i class="bi bi-plus-circle"></i> Crear</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Listado marcas (bulk) -->
    <form method="post" id="bulkFormBrands">
      <input type="hidden" name="action" value="bulk_save">
      <input type="hidden" name="brand_filter" value="<?= h($brand_filter) ?>">

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Marcas</span>
          <button class="btn btn-success btn-sm d-none bulk-save-btn" type="submit">
            <i class="bi bi-save"></i> Guardar cambios
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead>
              <tr>
                <th style="width:60px;"></th>
                <th>Marca</th>
                <?php if ($HAS_BRAND_HAS_SERIES): ?><th class="text-center">Series</th><?php endif; ?>
                <?php if ($HAS_BRAND_ACTIVE): ?><th class="text-center">Activa</th><?php endif; ?>
                <?php if ($HAS_ARTICLE_TYPE && $HAS_ARTICLE_BRANDID): ?>
                  <th class="text-end">Total</th>
                  <th class="text-end">Placas</th>
                  <th class="text-end">Soportes</th>
                  <th class="text-end">Frutos</th>
                <?php endif; ?>
                <th class="text-end" style="width:160px;">Acciones</th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($brands as $b): ?>
                <?php
                  $id = (int)$b['id'];
                  $logo = $HAS_BRAND_LOGO ? ($b['logo_path'] ?? '') : '';
                  $c = $countsByBrand[$id] ?? ['cnt_total'=>0,'cnt_placa'=>0,'cnt_soporte'=>0,'cnt_fruto'=>0];
                  $usage = brand_usage_counts($id);
                  $isActive = $HAS_BRAND_ACTIVE ? (int)($b['is_active'] ?? 1) : 1;
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
                    <input class="form-control form-control-sm bulk-input" disabled
                           name="brand_name[<?= h($id) ?>]" value="<?= h($b['name']) ?>" required>
                  </td>

                  <?php if ($HAS_BRAND_HAS_SERIES): ?>
                    <td class="text-center">
                      <input class="form-check-input bulk-input" type="checkbox" disabled
                             name="brand_has_series[<?= h($id) ?>]" <?= ((int)($b['has_series'] ?? 1)===1?'checked':'') ?>>
                    </td>
                  <?php endif; ?>

                  <?php if ($HAS_BRAND_ACTIVE): ?>
                    <td class="text-center">
                      <input class="form-check-input bulk-input" type="checkbox" disabled
                             name="brand_is_active[<?= h($id) ?>]" <?= ($isActive===1?'checked':'') ?>>
                    </td>
                  <?php endif; ?>

                  <?php if ($HAS_ARTICLE_TYPE && $HAS_ARTICLE_BRANDID): ?>
                    <td class="text-end"><?= h($c['cnt_total']) ?></td>
                    <td class="text-end"><?= h($c['cnt_placa']) ?></td>
                    <td class="text-end"><?= h($c['cnt_soporte']) ?></td>
                    <td class="text-end"><?= h($c['cnt_fruto']) ?></td>
                  <?php endif; ?>

                  <td class="text-end">
                    <?php if ($HAS_BRAND_LOGO): ?>
                      <form method="post" enctype="multipart/form-data" class="d-inline-block">
                        <input type="hidden" name="action" value="brand_logo">
                        <input type="hidden" name="id" value="<?= h($id) ?>">
                        <label class="btn btn-sm btn-outline-secondary mb-0" title="Cambiar logo">
                          <i class="bi bi-image"></i>
                          <input type="file" name="logo" accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                                 style="display:none" onchange="this.form.submit()">
                        </label>
                      </form>
                    <?php endif; ?>

                    <form method="post" class="d-inline-block"
                          onsubmit="return confirm('¿Eliminar marca <?= h(addslashes($b['name'])) ?>?\\n\\nSi está en uso, se desactivará (si existe is_active).');">
                      <input type="hidden" name="action" value="delete_brand">
                      <input type="hidden" name="id" value="<?= h($id) ?>">
                      <button class="btn btn-sm btn-outline-danger" title="Eliminar / Desactivar">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>

              <?php if (!$brands): ?>
                <tr><td colspan="10" class="text-muted text-center py-2">No hay marcas.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="card-footer text-muted small">
          <strong>Nota:</strong> si una marca está “en uso”, no se elimina: se <strong>desactiva</strong> (si existe <code>is_active</code>).
        </div>
      </div>
    </form>
  </div>

  <!-- ============ COLUMNA DER: SERIES ============ -->
  <div class="col-lg-7">

    <!-- Nueva serie -->
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
                <?php if ($HAS_BRAND_HAS_SERIES && (int)($b['has_series'] ?? 1) === 0) continue; ?>
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
              <label class="form-check-label" for="add_is_family">Esta serie es PADRE / FAMILIA</label>
            </div>
            <div class="form-text">
              Las familias (padre) agrupan colores/hermanas. La “BASE” se marca en una hija.
            </div>
          </div>

          <div class="col-md-6" id="add_parent_wrap">
            <label class="form-label">Serie padre (familia)</label>
            <select name="parent_series_id" class="form-select" id="add_parent_series_id">
              <option value="">Seleccione...</option>
              <?php
                if ($brand_filter > 0 && isset($familiesByBrand[$brand_filter])) {
                  foreach ($familiesByBrand[$brand_filter] as $f) {
                    echo '<option value="'.h($f['id']).'">'.h($f['name']).'</option>';
                  }
                }
              ?>
            </select>
          </div>

          <div class="col-md-6 d-flex align-items-end" id="add_base_wrap">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_base" id="add_is_base">
              <label class="form-check-label" for="add_is_base">Es BASE de su familia</label>
            </div>
          </div>

          <div class="col-12 d-flex justify-content-end mt-2">
            <button class="btn btn-success"><i class="bi bi-plus-circle"></i> Crear</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Series (bulk) -->
    <form method="post" id="bulkFormSeries">
      <input type="hidden" name="action" value="bulk_save">
      <input type="hidden" name="brand_filter" value="<?= h($brand_filter) ?>">

      <div class="card mb-2">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Series</span>

          <div class="d-flex gap-2 align-items-center">
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
              <?php if ($show_inactive): ?><input type="hidden" name="show_inactive" value="1"><?php endif; ?>
            </form>

            <button class="btn btn-success btn-sm d-none bulk-save-btn" type="submit">
              <i class="bi bi-save"></i> Guardar cambios
            </button>
          </div>
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
                <?php if ($HAS_SER_ACTIVE): ?><th class="text-center" style="width:80px;">Activa</th><?php endif; ?>
                <th style="width:180px;">ID fabricante</th>
                <th class="text-end" style="width:110px;">Acciones</th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($series as $s): ?>
                <?php
                  $sid = (int)$s['id'];
                  $bid = (int)$s['brand_id'];
                  $isFamily = $HAS_SER_PARENT ? (empty($s['parent_series_id']) ? 1 : 0) : 0;
                  $parentId = $HAS_SER_PARENT ? (int)($s['parent_series_id'] ?? 0) : 0;
                  $isActive = $HAS_SER_ACTIVE ? (int)($s['is_active'] ?? 1) : 1;
                ?>
                <tr>
                  <td><?= h($s['brand_name']) ?></td>

                  <td>
                    <input class="form-control form-control-sm bulk-input" disabled
                           name="series_name[<?= h($sid) ?>]" value="<?= h($s['name']) ?>" required>
                  </td>

                  <td>
                    <?php if (!$HAS_SER_PARENT): ?>
                      <span class="text-muted">—</span>
                    <?php else: ?>
                      <select class="form-select form-select-sm bulk-input parent-select"
                              name="series_parent[<?= h($sid) ?>]" data-row="<?= h($sid) ?>" disabled>
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
                    <input class="form-check-input bulk-input is-family" type="checkbox" disabled
                           name="series_is_family[<?= h($sid) ?>]" data-row="<?= h($sid) ?>" <?= $isFamily ? 'checked' : '' ?>>
                  </td>

                  <td class="text-center">
                    <input class="form-check-input bulk-input is-base" type="checkbox" disabled
                           name="series_is_base[<?= h($sid) ?>]" data-row="<?= h($sid) ?>"
                           <?= (!$isFamily && $HAS_SER_IS_BASE && (int)($s['is_base'] ?? 0) === 1) ? 'checked' : '' ?>
                           <?= $isFamily ? 'disabled' : '' ?>>
                  </td>

                  <?php if ($HAS_SER_ACTIVE): ?>
                    <td class="text-center">
                      <input class="form-check-input bulk-input" type="checkbox" disabled
                             name="series_is_active[<?= h($sid) ?>]" <?= ($isActive===1?'checked':'') ?>>
                    </td>
                  <?php endif; ?>

                  <td>
                    <input class="form-control form-control-sm bulk-input" disabled
                           name="series_manufacturer_id[<?= h($sid) ?>]"
                           value="<?= h($HAS_SER_MAN_ID ? ($s['manufacturer_series_id'] ?? '') : '') ?>">
                  </td>

                  <td class="text-end">
                    <form method="post" class="d-inline-block"
                          onsubmit="return confirm('¿Eliminar serie <?= h(addslashes($s['name'])) ?>?\\n\\nSi está en uso, se desactivará (si existe is_active).');">
                      <input type="hidden" name="action" value="delete_series">
                      <input type="hidden" name="id" value="<?= h($sid) ?>">
                      <input type="hidden" name="brand_filter" value="<?= h($brand_filter) ?>">
                      <button class="btn btn-sm btn-outline-danger" title="Eliminar / Desactivar">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>

              <?php if (!$series): ?>
                <tr><td colspan="10" class="text-muted text-center py-2">No hay series.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="card-footer text-muted small">
          <strong>Modo edición:</strong> activa para hacer múltiples cambios y luego <strong>Guardar cambios</strong> una sola vez. <br>
          <strong>Serie base:</strong> solo aplica a hijas; al marcar base se desmarca cualquier otra base en la misma familia.
        </div>
      </div>
    </form>

  </div>
</div>

<script>
(function(){
  const btn = document.getElementById('btnEditMode');
  const inputs = document.querySelectorAll('.bulk-input');
  const saveBtns = document.querySelectorAll('.bulk-save-btn');
  let edit = false;

  function refresh(){
    inputs.forEach(el => el.disabled = !edit);
    saveBtns.forEach(b => b.classList.toggle('d-none', !edit));
    btn.innerHTML = edit ? '<i class="bi bi-x-circle"></i> Salir de edición' : '<i class="bi bi-pencil-square"></i> Modo edición';

    if (edit) {
      // Ajusta por filas: si es padre -> deshabilita parent/base
      document.querySelectorAll('.is-family').forEach(chk=>{
        const row = chk.getAttribute('data-row');
        const sel = document.querySelector('.parent-select[data-row="'+row+'"]');
        const base = document.querySelector('.is-base[data-row="'+row+'"]');
        if (chk.checked) {
          if (sel) { sel.value=''; sel.disabled = true; }
          if (base) { base.checked=false; base.disabled=true; }
        } else {
          if (sel) sel.disabled = false;
          if (base) base.disabled = false;
        }
      });
    }
  }

  btn.addEventListener('click', function(){
    edit = !edit;
    refresh();
  });

  document.querySelectorAll('.is-family').forEach(chk=>{
    chk.addEventListener('change', ()=>{
      if (!edit) return;
      const row = chk.getAttribute('data-row');
      const sel = document.querySelector('.parent-select[data-row="'+row+'"]');
      const base = document.querySelector('.is-base[data-row="'+row+'"]');
      if (chk.checked) {
        if (sel) { sel.value=''; sel.disabled=true; }
        if (base) { base.checked=false; base.disabled=true; }
      } else {
        if (sel) sel.disabled=false;
        if (base) base.disabled=false;
      }
    });
  });

  // Nueva serie: si es familia, ocultar padre/base
  const addIsFamily = document.getElementById('add_is_family');
  const addParentWrap = document.getElementById('add_parent_wrap');
  const addBaseWrap = document.getElementById('add_base_wrap');
  const addIsBase = document.getElementById('add_is_base');

  function toggleAdd(){
    if (!addIsFamily) return;
    const isFam = addIsFamily.checked;
    addParentWrap.style.display = isFam ? 'none' : '';
    addBaseWrap.style.display   = isFam ? 'none' : '';
    if (isFam) addIsBase.checked = false;
  }
  if (addIsFamily) {
    addIsFamily.addEventListener('change', toggleAdd);
    toggleAdd();
  }

  refresh();
})();
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
