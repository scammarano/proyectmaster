
<?php
require_once __DIR__ . '/../config.php';
function db_connect(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
        $opts = [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    }
    return $pdo;
}
function db_query(string $sql, array $params=[]): PDOStatement {
    $st = db_connect()->prepare($sql);
    $st->execute($params);
    return $st;
}

if (!function_exists('table_exists')) {
  function table_exists(string $table): bool {
    try {
      $stmt = db_query("SHOW TABLES LIKE ?", [$table]);
      return (bool)$stmt->fetch();
    } catch (Throwable $e) {
      return false;
    }
  }
}
