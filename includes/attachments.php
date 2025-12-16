<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function att_allowed_exts(): array {
  // fallback seguro
  return ['pdf','jpg','jpeg','png','dwg'];
}

function att_storage_dir(string $entity_type): string {
  $root = realpath(__DIR__ . '/..'); // …/vimar
  $sub  = ($entity_type === 'project') ? 'uploads/projects' : 'uploads/areas';
  $dir  = $root . DIRECTORY_SEPARATOR . $sub;
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir;
}

function att_rel_path(string $entity_type, string $filename): string {
  $sub  = ($entity_type === 'project') ? 'uploads/projects' : 'uploads/areas';
  return $sub . '/' . $filename;
}

function att_list(string $entity_type, int $entity_id): array {
  return db_query("
    SELECT a.*, ft.name AS file_type_name
    FROM attachments a
    LEFT JOIN file_types ft ON ft.id=a.file_type_id
    WHERE a.entity_type=? AND a.entity_id=?
    ORDER BY a.created_at DESC, a.id DESC
  ", [$entity_type, $entity_id])->fetchAll();
}

function att_delete(int $id): void {
  $row = db_query("SELECT * FROM attachments WHERE id=?", [$id])->fetch();
  if (!$row) return;
  $root = realpath(__DIR__ . '/..');
  $abs  = $root . '/' . $row['stored_path'];
  if (is_file($abs)) @unlink($abs);
  db_query("DELETE FROM attachments WHERE id=?", [$id]);
}

function att_upload(string $entity_type, int $entity_id, array $file, ?int $file_type_id = null): int {
  if (!isset($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    throw new Exception('No se recibió archivo.');
  }
  if (!is_uploaded_file($file['tmp_name'])) throw new Exception('Upload inválido.');

  $orig = $file['name'] ?? 'archivo';
  $size = (int)($file['size'] ?? 0);
  $mime = $file['type'] ?? null;

  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if (!in_array($ext, att_allowed_exts(), true)) {
    throw new Exception('Extensión no permitida: ' . $ext);
  }

  $dir = att_storage_dir($entity_type);

  $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($orig, PATHINFO_FILENAME));
  $fname = $entity_type . '_' . $entity_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safe . '.' . $ext;

  $abs = $dir . DIRECTORY_SEPARATOR . $fname;
  if (!move_uploaded_file($file['tmp_name'], $abs)) {
    throw new Exception('No se pudo guardar el archivo.');
  }

  $rel = att_rel_path($entity_type, $fname);
  $uid = function_exists('current_user_id') ? (int)current_user_id() : null;

  db_query("
    INSERT INTO attachments(entity_type,entity_id,file_type_id,stored_path,original_name,mime,size_bytes,uploaded_by)
    VALUES (?,?,?,?,?,?,?,?)
  ", [$entity_type, $entity_id, $file_type_id, $rel, $orig, $mime, $size, $uid]);

  return (int)db_connect()->lastInsertId();
}
