
<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$template_id = (int) get_param('id', 0);
$tpl = db_query("SELECT * FROM point_templates WHERE id = ?", [$template_id])->fetch();
if (!$tpl) die('Plantilla no encontrada');

$brands = db_query("SELECT * FROM brands ORDER BY name")->fetchAll();
$series = $tpl['brand_id'] ? db_query("SELECT * FROM series WHERE brand_id = ? ORDER BY name", [$tpl['brand_id']])->fetchAll() : [];

$error = null;

if (is_post()) {
    $action = post_param('action');
    if ($action === 'update_tpl') {
        $name = trim(post_param('name'));
        $system_type = post_param('system_type', 'electrico');
        $orientation = post_param('orientation', 'H');
        $brand_id = post_param('brand_id') ? (int) post_param('brand_id') : null;
        $series_id = post_param('series_id') ? (int) post_param('series_id') : null;
        $support_article_id = post_param('support_article_id') ? (int) post_param('support_article_id') : null;
        $plate_article_id = post_param('plate_article_id') ? (int) post_param('plate_article_id') : null;

        $modules = $tpl['modules'];
        if ($support_article_id) {
            $support = db_query("SELECT modules FROM articles WHERE id = ?", [$support_article_id])->fetch();
            if ($support) {
                $modules = (int)$support['modules'];
            }
        }

        db_query("UPDATE point_templates 
                  SET name=?, system_type=?, orientation=?, brand_id=?, series_id=?, support_article_id=?, plate_article_id=?, modules=?
                  WHERE id=?",
                 [$name, $system_type, $orientation, $brand_id, $series_id, $support_article_id, $plate_article_id, $modules, $template_id]);
        $tpl = db_query("SELECT * FROM point_templates WHERE id = ?", [$template_id])->fetch();
        $series = $tpl['brand_id'] ? db_query("SELECT * FROM series WHERE brand_id = ? ORDER BY name", [$tpl['brand_id']])->fetchAll() : [];
    } elseif ($action === 'add_component') {
        $article_id = (int) post_param('article_id');
        if ($article_id) {
            $support = null;
            if ($tpl['support_article_id']) {
                $support = db_query("SELECT * FROM articles WHERE id = ?", [$tpl['support_article_id']])->fetch();
            }
            if ($support) {
                $used = db_query("SELECT SUM(a.modules) AS used_modules
                                  FROM point_template_components pc
                                  JOIN articles a ON a.id = pc.article_id
                                  WHERE pc.template_id = ?", [$template_id])->fetch();
                $used_modules = (int)($used['used_modules'] ?? 0);

                $art = db_query("SELECT modules FROM articles WHERE id = ?", [$article_id])->fetch();
                $new_mod = (int)($art['modules'] ?? 1);

                if ($used_modules + $new_mod > (int)$support['modules']) {
                    $error = "La suma de módulos de los frutos supera los módulos del soporte.";
                } else {
                    $max_pos = db_query("SELECT MAX(position) AS m FROM point_template_components WHERE template_id = ?", [$template_id])->fetch();
                    $next_pos = (int)($max_pos['m'] ?? 0) + 1;
                    db_query("INSERT INTO point_template_components (template_id, article_id, position) VALUES (?,?,?)",
                             [$template_id, $article_id, $next_pos]);
                }
            } else {
                $error = "Debes seleccionar un soporte antes de agregar frutos a la plantilla.";
            }
        }
    } elseif ($action === 'autofill_blinds') {
        if ($tpl['support_article_id'] && $tpl['brand_id'] && $tpl['series_id']) {
            $support = db_query("SELECT * FROM articles WHERE id = ?", [$tpl['support_article_id']])->fetch();
            $used = db_query("SELECT SUM(a.modules) AS used_modules
                              FROM point_template_components pc
                              JOIN articles a ON a.id = pc.article_id
                              WHERE pc.template_id = ?", [$template_id])->fetch();
            $used_modules = (int)($used['used_modules'] ?? 0);
            $total_modules = (int)$support['modules'];

            if ($used_modules < $total_modules) {
                $missing = $total_modules - $used_modules;
                $blind = db_query("SELECT * FROM articles
                                   WHERE brand_id = ? AND series_id = ?
                                     AND article_type = 'fruto'
                                     AND (fruit_subtype = 'ciego' OR name LIKE '%ciego%')
                                   ORDER BY id LIMIT 1",
                                   [$tpl['brand_id'], $tpl['series_id']])->fetch();
                if ($blind) {
                    $max_pos = db_query("SELECT MAX(position) AS m FROM point_template_components WHERE template_id = ?", [$template_id])->fetch();
                    $pos = (int)($max_pos['m'] ?? 0);
                    for ($i = 0; $i < $missing; $i++) {
                        $pos++;
                        db_query("INSERT INTO point_template_components (template_id, article_id, position) VALUES (?,?,?)",
                                 [$template_id, $blind['id'], $pos]);
                    }
                }
            }
        }
    }
}

$del_id = (int) get_param('del_id', 0);
if ($del_id) {
    db_query("DELETE FROM point_template_components WHERE id = ? AND template_id = ?", [$del_id, $template_id]);
    redirect('index.php?page=template_edit&id=' . $template_id);
}

$tpl = db_query("SELECT * FROM point_templates WHERE id = ?", [$template_id])->fetch();
$series = $tpl['brand_id'] ? db_query("SELECT * FROM series WHERE brand_id = ? ORDER BY name", [$tpl['brand_id']])->fetchAll() : [];

$components = db_query("SELECT pc.*, a.code, a.name, a.modules, a.fruit_subtype
                        FROM point_template_components pc
                        JOIN articles a ON a.id = pc.article_id
                        WHERE pc.template_id = ?
                        ORDER BY pc.position", [$template_id])->fetchAll();

$article_where = "WHERE a.article_type = 'fruto'";
$params = [];
if ($tpl['brand_id']) {
    $article_where .= " AND a.brand_id = ?";
    $params[] = $tpl['brand_id'];
}
if ($tpl['series_id']) {
    $article_where .= " AND (a.series_id = ? OR a.series_id IS NULL)";
    $params[] = $tpl['series_id'];
}
$fruits = db_query("SELECT a.* FROM articles a $article_where ORDER BY a.code LIMIT 200", $params)->fetchAll();

$supports = [];
$plates = [];
if ($tpl['brand_id']) {
    $aw = "WHERE a.brand_id = ?";
    $p = [$tpl['brand_id']];
    if ($tpl['series_id']) {
        $aw .= " AND (a.series_id = ? OR a.series_id IS NULL)";
        $p[] = $tpl['series_id'];
    }
    $supports = db_query("SELECT a.* FROM articles a $aw AND a.article_type='soporte' ORDER BY a.modules, a.code", $p)->fetchAll();
    $plates = db_query("SELECT a.* FROM articles a $aw AND a.article_type='placa' ORDER BY a.modules, a.code", $p)->fetchAll();
}

include __DIR__ . '/../layout/header.php';
?>
<h1><i class="bi bi-pencil-square"></i> Editar plantilla de punto</h1>
<p class="text-muted">Define soporte (obligatorio), placa (opcional) y frutos. El sistema validará los módulos del soporte.</p>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-md-6">
    <form method="post" class="card card-body mb-4">
      <input type="hidden" name="action" value="update_tpl">
      <div class="mb-3">
        <label class="form-label">Nombre</label>
        <input type="text" name="name" class="form-control" value="<?= h($tpl['name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Tipo de sistema</label>
        <select name="system_type" class="form-select">
          <?php foreach (['electrico'=>'Eléctrico','datos'=>'Datos','wifi'=>'WiFi','persianas'=>'Persianas','otros'=>'Otros'] as $k=>$lbl): ?>
            <option value="<?= $k ?>" <?= $tpl['system_type']===$k?'selected':'' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="row mb-3">
        <div class="col">
          <label class="form-label">Orientación</label>
          <select name="orientation" class="form-select">
            <option value="H" <?= $tpl['orientation']==='H'?'selected':'' ?>>Horizontal</option>
            <option value="V" <?= $tpl['orientation']==='V'?'selected':'' ?>>Vertical</option>
            <option value="N" <?= $tpl['orientation']==='N'?'selected':'' ?>>N/A</option>
          </select>
        </div>
        <div class="col">
          <label class="form-label">Módulos (del soporte)</label>
          <input type="text" class="form-control" value="<?= h($tpl['modules']) ?>" disabled>
        </div>
      </div>
      <div class="row mb-3">
        <div class="col">
          <label class="form-label">Marca</label>
          <select name="brand_id" class="form-select">
            <option value="">(ninguna)</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= h($b['id']) ?>" <?= $tpl['brand_id']==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col">
          <label class="form-label">Serie</label>
          <select name="series_id" class="form-select">
            <option value="">(ninguna)</option>
            <?php foreach ($series as $s): ?>
              <option value="<?= h($s['id']) ?>" <?= $tpl['series_id']==$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Soporte (obligatorio)</label>
        <select name="support_article_id" class="form-select">
          <option value="">(ninguno)</option>
          <?php foreach ($supports as $a): ?>
            <option value="<?= h($a['id']) ?>" <?= $tpl['support_article_id']==$a['id']?'selected':'' ?>>
              <?= h($a['code']) ?> — <?= h($a['name']) ?> (<?= h($a['modules']) ?>M)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Placa (opcional)</label>
        <select name="plate_article_id" class="form-select">
          <option value="">(ninguna)</option>
          <?php foreach ($plates as $a): ?>
            <option value="<?= h($a['id']) ?>" <?= $tpl['plate_article_id']==$a['id']?'selected':'' ?>>
              <?= h($a['code']) ?> — <?= h($a['name']) ?> (<?= h($a['modules']) ?>M)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-success"><i class="bi bi-check-lg"></i> Guardar plantilla</button>
    </form>

    <form method="post" class="mb-4">
      <input type="hidden" name="action" value="autofill_blinds">
      <button class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-magic"></i> Completar módulos libres con ciegos
      </button>
    </form>
  </div>

  <div class="col-md-6">
    <h2 class="h5"><i class="bi bi-puzzle"></i> Frutos de la plantilla</h2>
    <table class="table table-sm table-striped align-middle">
      <thead>
        <tr><th>#</th><th>Código</th><th>Nombre</th><th>Mód.</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($components as $c): ?>
        <tr>
          <td><?= h($c['position']) ?></td>
          <td><?= h($c['code']) ?></td>
          <td><?= h($c['name']) ?></td>
          <td><?= h($c['modules']) ?></td>
          <td>
            <a href="index.php?page=template_edit&id=<?= h($template_id) ?>&del_id=<?= h($c['id']) ?>" class="btn btn-sm btn-danger">
              <i class="bi bi-x-lg"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$components): ?>
        <tr><td colspan="5" class="text-muted">Sin frutos aún.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <div class="card card-body">
      <h5 class="card-title">Agregar fruto</h5>
      <form method="post">
        <input type="hidden" name="action" value="add_component">
        <div class="mb-3">
          <label class="form-label">Artículo</label>
          <select name="article_id" class="form-select" required>
            <option value="">Seleccione...</option>
            <?php foreach ($fruits as $a): ?>
              <option value="<?= h($a['id']) ?>">
                <?= h($a['code']) ?> — <?= h($a['name']) ?> (<?= h($a['modules']) ?>M <?= h($a['fruit_subtype']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Agregar fruto</button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
