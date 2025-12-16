<?php
// includes/helpers_safe.php
function safe_table_exists(string $table): bool {
  try {
    $st = db_query("SHOW TABLES LIKE ?", [$table]);
    return (bool)$st->fetch();
  } catch (Throwable $e) { return false; }
}
