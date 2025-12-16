<?php
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_permission('homologations','view');
?>
<div class="container py-3">
  <h3>Homologaciones</h3>
  <p class="text-muted">Accesos rápidos:</p>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can_perm('homologations','report')): ?>
      <a class="btn btn-outline-primary" href="index.php?page=homologations_report"><i class="bi bi-bar-chart"></i> Reporte</a>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
