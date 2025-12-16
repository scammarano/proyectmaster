<?php
/**
 * Escritura segura de /config/rules.php
 * - Genera un archivo PHP que retorna un array.
 * - Hace backup automático en /config/rules.backup.YYYYmmdd-HHMMSS.php
 */
function rules_config_path(): string {
  return __DIR__ . '/../config/rules.php';
}
function rules_backup_path(): string {
  return __DIR__ . '/../config/rules.backup.'.date('Ymd-His').'.php';
}

function export_php_array(array $arr): string {
  // Exporta array como PHP válido y legible
  $export = var_export($arr, true);
  // var_export usa 'array (...)' en algunas versiones; lo dejamos tal cual para compatibilidad.
  return "<?php\nreturn ".$export.";\n";
}

function write_rules_file(array $rules): void {
  $path = rules_config_path();
  $dir = dirname($path);
  if (!is_dir($dir)) throw new Exception("No existe el directorio config: $dir");
  if (file_exists($path)) {
    $bak = rules_backup_path();
    if (!@copy($path, $bak)) {
      // no fatal, pero avisamos
      throw new Exception("No se pudo crear backup de rules.php (permisos).");
    }
  }
  $tmp = $path.'.tmp';
  $data = export_php_array($rules);
  if (@file_put_contents($tmp, $data) === false) throw new Exception("No se pudo escribir $tmp (permisos).");
  if (!@rename($tmp, $path)) {
    @unlink($tmp);
    throw new Exception("No se pudo reemplazar rules.php (permisos).");
  }
}
