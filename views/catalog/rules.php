<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rules.php';
require_login();
if (!is_admin()) die('Solo admin');

$rules = load_rules();

include __DIR__ . '/../layout/header.php';
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-sliders"></i> Reglas (archivo)</span>
    <span class="text-muted small">Se editan en <code>/config/rules.php</code></span>
  </div>
  <div class="card-body">
    <p class="mb-2">Estas reglas controlan qué campos se muestran/ocultan y (más adelante) validaciones por división para puntos.</p>
    <pre class="bg-light p-2 rounded small" style="white-space:pre-wrap;"><?=h(json_encode($rules, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre>
    <div class="alert alert-info mt-3 mb-0">
      Tip: para cambiar comportamiento, edita <code>config/rules.php</code> en el File Manager de cPanel y recarga.
    </div>
  </div>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>
