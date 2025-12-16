<?php
// Incluir este bloque en tu header principal
$currentProject = $_SESSION['current_project_id'] ?? null;
?>
<li class="nav-item dropdown">
  <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Reportes</a>
  <ul class="dropdown-menu">
    <?php if ($currentProject): ?>
      <li><a class="dropdown-item" href="/reports/index.php?project_id=<?= $currentProject ?>">Proyecto activo</a></li>
    <?php else: ?>
      <li class="dropdown-item text-muted">Seleccione un proyecto</li>
    <?php endif; ?>
  </ul>
</li>
