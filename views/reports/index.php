<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login();

/**
 * Reportes por Proyecto
 * - Lista proyectos con conteos de áreas/puntos
 * - Botones: Informe (HTML), Excel (CSV), PDF (HTML imprimible)
 */

$HAS_CLOSED = (bool)db_query("SHOW COLUMNS FROM projects LIKE 'is_closed'")->fetch();

$rows = db_query("
  SELECT p.*,
    (SELECT COUNT(*) FROM areas a WHERE a.project_id=p.id) AS areas_count,
    (SELECT COUNT(*) FROM points x WHERE x.project_id=p.id) AS points_count
  FROM projects p
  ORDER BY p.id DESC
")->fetchAll();

include __DIR__ . '/../layout/header.php';
?>
<script>
setCtx('Reportes', `<a class="btn btn-sm btn-outline-secondary" href="index.php?page=projects"><i class="bi bi-arrow-left"></i> Volver</a>`);
</script>

<h1 class="h3 mb-3"><i class="bi bi-bar-chart"></i> Reportes</h1>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
      <thead>
        <tr>
          <th>Proyecto</th>
          <th class="text-end">Áreas</th>
          <th class="text-end">Puntos</th>
          <th class="text-center">Estado</th>
          <th class="text-end" style="width:260px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $p): $closed = $HAS_CLOSED ? ((int)($p['is_closed'] ?? 0)===1) : false; ?>
        <tr>
          <td class="fw-semibold"><?=h($p['name'] ?? '')?></td>
          <td class="text-end"><?=h($p['areas_count'] ?? 0)?></td>
          <td class="text-end"><?=h($p['points_count'] ?? 0)?></td>
          <td class="text-center">
            <?php if(!$HAS_CLOSED): ?><span class="badge text-bg-secondary">—</span>
            <?php else: ?>
              <?= $closed ? '<span class="badge text-bg-secondary"><i class="bi bi-lock"></i> Cerrado</span>' :
                           '<span class="badge text-bg-success"><i class="bi bi-unlock"></i> Abierto</span>' ?>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" title="Ver informe" href="index.php?page=report_project&id=<?=h($p['id'])?>">
              <i class="bi bi-file-earmark-text"></i>
            </a>
            <a class="btn btn-sm btn-outline-success" title="Excel (CSV)" href="index.php?page=report_project_excel&id=<?=h($p['id'])?>">
              <i class="bi bi-filetype-csv"></i>
            </a>
            <a class="btn btn-sm btn-outline-danger" title="PDF (imprimir)" href="index.php?page=report_project_pdf&id=<?=h($p['id'])?>" target="_blank">
              <i class="bi bi-filetype-pdf"></i>
            </a>
            <a class="btn btn-sm btn-outline-secondary" title="Abrir proyecto" href="index.php?page=project_detail&id=<?=h($p['id'])?>">
              <i class="bi bi-box-arrow-in-right"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?><tr><td colspan="5" class="text-muted text-center py-4">No hay proyectos.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
