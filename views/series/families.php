<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

function col_exists($table, $col) {
  return (bool) db_query("SHOW COLUMNS FROM `$table` LIKE ?", [$col])->fetch();
}

$HAS_SER_PARENT   = col_exists('series','parent_series_id');
$HAS_SER_IS_BASE  = col_exists('series','is_base');
$HAS_SER_MAN_ID   = col_exists('series','manufacturer_series_id');
$HAS_SER_ACTIVE   = col_exists('series','is_active');
$HAS_BRAND_ACTIVE = col_exists('brands','is_active');

if (!$HAS_SER_PARENT) {
  include __DIR__ . '/../layout/header.php';
  echo '<div class="alert alert-danger">Tu BD no tiene <code>series.parent_series_id</code>. Aplica los alters de series.</div>';
  include __DIR__ . '/../layout/footer.php';
  exit;
}

$brand_filter  = (int)($_GET['brand_filter'] ?? 0);
$show_inactive = isset($_GET['show_inactive']) ? 1 : 0;

/** Brands (para filtro) */
$whereBrands = [];
$paramsBrands = [];
if ($HAS_BRAND_ACTIVE && !$show_inactive) $whereBrands[] = "is_active=1";
$sqlBrands = "SELECT id,name".($HAS_BRAND_ACTIVE?",is_active":"")." FROM brands".
            ($whereBrands ? " WHERE ".implode(" AND ", $whereBrands) : "").
            " ORDER BY name";
$brands = db_query($sqlBrands, $paramsBrands)->fetchAll();

/** Families */
$whereFam = ["s.parent_series_id IS NULL"];
$paramsFam = [];
if ($brand_filter > 0) { $whereFam[]="s.brand_id=?"; $paramsFam[]=$brand_filter; }
if ($HAS_SER_ACTIVE && !$show_inactive) $whereFam[]="s.is_active=1";

$families = db_query("
  SELECT s.id,s.brand_id,s.name
  ".($HAS_SER_MAN_ID?",s.manufacturer_series_id":"")."
  ".($HAS_SER_ACTIVE?",s.is_active":"")."
  ,b.name AS brand_name
  FROM series s
  JOIN brands b ON b.id=s.brand_id
  WHERE ".implode(" AND ", $whereFam)."
  ORDER BY b.name, s.name
", $paramsFam)->fetchAll();

/** Children for shown families */
$childrenByParent = [];
$baseByParent = [];

if ($families) {
  $parentIds = array_map(fn($r)=> (int)$r['id'], $families);
  $in = implode(',', array_fill(0, count($parentIds), '?'));

  $whereCh = ["c.parent_series_id IN ($in)"];
  $paramsCh = $parentIds;
  if ($HAS_SER_ACTIVE && !$show_inactive) $whereCh[]="c.is_active=1";

  $children = db_query("
    SELECT c.id,c.brand_id,c.parent_series_id,c.name
    ".($HAS_SER_IS_BASE?",c.is_base":"")."
    ".($HAS_SER_MAN_ID?",c.manufacturer_series_id":"")."
    ".($HAS_SER_ACTIVE?",c.is_active":"")."
    FROM series c
    WHERE ".implode(" AND ", $whereCh)."
    ORDER BY c.parent_series_id, (".($HAS_SER_IS_BASE?"c.is_base":"0").") DESC, c.name
  ", $paramsCh)->fetchAll();

  foreach ($children as $ch) {
    $pid = (int)$ch['parent_series_id'];
    if (!isset($childrenByParent[$pid])) $childrenByParent[$pid] = [];
    $childrenByParent[$pid][] = $ch;

    if ($HAS_SER_IS_BASE && (int)($ch['is_base'] ?? 0) === 1) {
      $baseByParent[$pid] = (int)$ch['id'];
    }
  }
}

include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h3 mb-1"><i class="bi bi-diagram-3"></i> Series por Familias</h1>
    <div class="text-muted small">Vista “pro” para revisar Padres → Hermanas y cuál es la BASE en cada familia.</div>
  </div>

  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm"
       href="index.php?page=series_families<?= $show_inactive ? '' : '&show_inactive=1' ?><?= $brand_filter?('&brand_filter='.h($brand_filter)) : '' ?>">
      <?= $show_inactive ? 'Ocultar inactivos' : 'Ver inactivos' ?>
    </a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="page" value="series_families">
      <?php if ($show_inactive): ?><input type="hidden" name="show_inactive" value="1"><?php endif; ?>

      <div class="col-md-4">
        <label class="form-label">Marca</label>
        <select name="brand_filter" class="form-select" onchange="this.form.submit()">
          <option value="0">(todas)</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?= h($b['id']) ?>" <?= ($brand_filter === (int)$b['id'] ? 'selected' : '') ?>>
              <?= h($b['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Buscar (familia o hija)</label>
        <input type="text" class="form-control" id="q" placeholder="Ej: ARKÉ, PLANA, GRIS, BLANCO...">
      </div>

      <div class="col-md-4 d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('q').value=''; filterCards();">
          Limpiar
        </button>
        <button type="button" class="btn btn-primary" onclick="filterCards();">
          Buscar
        </button>
      </div>
    </form>
  </div>
</div>

<?php if (!$families): ?>
  <div class="alert alert-info">No hay familias (series padre) para mostrar con ese filtro.</div>
<?php else: ?>
  <div class="accordion" id="accFamilies">
    <?php
      $i=0;
      foreach ($families as $f):
        $i++;
        $pid = (int)$f['id'];
        $kids = $childrenByParent[$pid] ?? [];
        $baseId = $baseByParent[$pid] ?? 0;

        $txt = strtolower($f['brand_name'].' '.$f['name']);
        foreach ($kids as $k) { $txt .= ' '.strtolower($k['name']); }
    ?>
      <div class="accordion-item family-card" data-text="<?= h($txt) ?>">
        <h2 class="accordion-header" id="h<?= $pid ?>">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c<?= $pid ?>">
            <div class="d-flex flex-column">
              <div class="fw-semibold">
                <?= h($f['brand_name']) ?> — <?= h($f['name']) ?>
                <span class="badge text-bg-secondary ms-2">Familia</span>
              </div>
              <div class="text-muted small">
                <?= count($kids) ?> hermanas
                <?php if ($HAS_SER_MAN_ID && !empty($f['manufacturer_series_id'])): ?>
                  · ID fabricante: <code><?= h($f['manufacturer_series_id']) ?></code>
                <?php endif; ?>
              </div>
            </div>
          </button>
        </h2>

        <div id="c<?= $pid ?>" class="accordion-collapse collapse" data-bs-parent="#accFamilies">
          <div class="accordion-body">

            <?php if (!$kids): ?>
              <div class="alert alert-warning mb-0">
                Esta familia no tiene hijas (colores/hermanas) aún.
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th style="width:110px;">BASE</th>
                      <th>Serie (hija)</th>
                      <th style="width:200px;">ID fabricante</th>
                      <?php if ($HAS_SER_ACTIVE): ?><th style="width:110px;" class="text-center">Activa</th><?php endif; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($kids as $k): ?>
                      <?php
                        $isBase = $HAS_SER_IS_BASE ? ((int)($k['is_base'] ?? 0)===1) : false;
                        $isActive = $HAS_SER_ACTIVE ? ((int)($k['is_active'] ?? 1)===1) : true;
                      ?>
                      <tr>
                        <td>
                          <?php if ($isBase): ?>
                            <span class="badge text-bg-success"><i class="bi bi-check2-circle"></i> BASE</span>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                        <td><?= h($k['name']) ?></td>
                        <td>
                          <?php if ($HAS_SER_MAN_ID && !empty($k['manufacturer_series_id'])): ?>
                            <code><?= h($k['manufacturer_series_id']) ?></code>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                        <?php if ($HAS_SER_ACTIVE): ?>
                          <td class="text-center">
                            <?= $isActive ? '<span class="badge text-bg-primary">Sí</span>' : '<span class="badge text-bg-secondary">No</span>' ?>
                          </td>
                        <?php endif; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="small text-muted">
                Si ves 2 “BASE” en la misma familia, es un error de data (lo corregimos al guardar desde Marcas/Series).
              </div>
            <?php endif; ?>

          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
function filterCards(){
  const q = (document.getElementById('q').value || '').toLowerCase().trim();
  document.querySelectorAll('.family-card').forEach(card=>{
    const t = (card.getAttribute('data-text')||'').toLowerCase();
    card.style.display = (!q || t.includes(q)) ? '' : 'none';
  });
}
document.getElementById('q')?.addEventListener('input', ()=>filterCards());
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
