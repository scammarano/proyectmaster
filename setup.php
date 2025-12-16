
<?php
require_once __DIR__ . '/includes/db.php';

$sql = file_get_contents(__DIR__ . '/schema_full.sql');
try {
    db_connect()->exec($sql);
    echo "<h1>Instalación completada</h1>";
    echo "<p>La base de datos ha sido creada/actualizada. Usuario inicial: <strong>admin / admin123</strong> (cámbialo luego).</p>";
} catch (Throwable $e) {
    echo "<h1>Error al instalar</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}
