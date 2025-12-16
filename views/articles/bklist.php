<?php
require_once __DIR__ . '/../../includes/functions.php';
require_login();
if (!is_admin()) die('Solo admin');

if (!function_exists('table_exists')) {
  function table_exists(string $table): bool {
    try { $stmt = db_query("SHOW TABLES LIKE ?", [$table]); return (bool)$stmt->fetch(); }
    catch (Throwable $e) { return false; }
  }
}

$HAS_BRANDS  = table_exists('brands');
$HAS_SERIES  = table_exists('series');
$HAS_TYPES   = table_exists('article_types');
$HAS_DIVS    = table_exists('divisions');
$HAS_MAP     = table_exists('article_divisions');

$HAS_MODULES = (bool)db_query("SHOW COLUMNS FROM articles LIKE 'modules'")->fetch();
$HAS_REQ     = (bool)db_query("SHOW COLUMNS FROM articles LIKE 'requires_cover'")->fetch();
$HAS_ATID    = (bool)db_query("SHOW COLUMNS FROM articles LIKE 'article_type_id'")->fetch();

$q = trim(get_param('q',''));
$where = "1=1";
$params = [];
if ($q !== '') {
  $where = "(a.code LIKE ? OR a.name LIKE ?)";
  $params = ["%$q%","%$q%"];
}

$selectModules = $HAS_MODULES ? "a.modules" : "NULL AS modules";
$selectReq     = $HAS_REQ ? "a.requires_cover" : "NULL AS requires_cover";

$sql = "
  SELECT a.*,
         $selectModules,
         $selectReq
";
$sql .= $HAS_BRANDS ? ", b.name AS brand_name" : ", NULL AS brand_name";
$sql .= $HAS_SERIES ? ", s.name AS series_name" : ", NULL AS series_name";
$sql .= ($HAS_TYPES && $HAS_ATID) ? ", t.name AS type_name, t.code AS type_code" : ", NULL AS type_name, NULL AS type_code";
$sql .= " FROM articles a";
if ($HAS_BRANDS) $sql .= " LEFT JOIN brands b ON b.id=a.brand_id";
if ($HAS_SERIES) $sql .= " LEFT JOIN series s ON s.id=a.series_id";
if ($HAS_TYPES && $HAS_ATID) $sql .= " LEFT JOIN article_types t ON t.id=a.article_type_id";
$sql .= " WHERE $where ORDER BY a.id DESC LIMIT 500";

$rows = db_query($sql, $params)->fetchAll();

include __DIR__ . '/../layout/header.php';
?>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-2">
      <span><i class="bi bi-box-seam"></i> Artículos</span>
      <a class="btn btn-sm btn-primary" href="index.php?page=article_detail">
        <i class="bi bi-plus-circle"></i> Nuevo artículo
      </a>
    </div>
    <form class="d-flex gap-2" method="get">
      <input type="hidden" name="page" value="articles">
      <input class="form-control form-control-sm" name="q" value="<?=h($q)?>" placeholder="Buscar por código o nombre">
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
  </div>

  <div class="card-body">
    <?php if(!$rows): ?>
      <div class="text-muted">Sin artículos.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
          <thead>
          <tr>
            <th>ID</th>
            <th>Código</th>
            <th>Nombre</th>
            <th>Tipo</th>
            <th>Marca/Serie</th>
            <th class="text-end">M</th>
            <th class="text-center">Cubre</th>
            <th>Divisiones</th>
            <th class="text-end" style="width:120px">Acciones</th>
          </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <?php
              $divNames = [];
              if ($HAS_MAP && $HAS_DIVS) {
                $divNames = db_query("
                  SELECT d.name
                  FROM article_divisions ad
                  JOIN divisions d ON d.id=ad.division_id
                  WHERE ad.article_id=?
                  ORDER BY d.name
                ", [(int)$r['id']])->fetchAll();
                $divNames = array_map(fn($x)=>$x['name'], $divNames);
              }
            ?>
            <tr>
              <td><?=h($r['id'])?></td>
              <td><code><?=h($r['code'])?></code></td>
              <td><?=h($r['name'])?></td>
              <td><?=h($r['type_name'] ?? '—')?></td>
              <td class="text-muted small"><?=h($r['brand_name'] ?? '—')?> / <?=h($r['series_name'] ?? '—')?></td>
              <td class="text-end"><?=h($r['modules'] ?? '')?></td>
              <td class="text-center">
                <?php if($HAS_REQ): ?>
                  <?= ((int)($r['requires_cover'] ?? 0)===1) ? '<span title="Requiere cubretecla">✅</span>' : '—' ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td class="small"><?=h($divNames ? implode(', ', $divNames) : '—')?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="index.php?page=article_detail&id=<?=h($r['id'])?>">
                  <i class="bi bi-pencil"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="text-muted small mt-2">Para eliminar, entra a editar el artículo y usa el botón “Eliminar”.</div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
