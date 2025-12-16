<?php
// /includes/rbac.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * RBAC helper:
 * - If RBAC tables exist (roles, permissions, role_permissions, user_roles), uses them
 * - Else falls back to legacy users.role (admin/editor/viewer)
 *
 * Permissions are referenced by code = "<module>.<action>"
 */

function rbac_ready(): bool {
  return table_exists('roles') && table_exists('permissions') && table_exists('role_permissions') && table_exists('user_roles');
}

function legacy_can_perm(string $module, string $action): bool {
  $role = current_user_role();
  if ($role === 'admin') return true;
  if ($role === 'editor') {
    // Editor: allow everything except admin management (ajustable)
    $deny = [
      'users' => ['create','edit','disable','roles','manage','delete'],
      'roles' => ['view','create','edit','delete','permissions','manage'],
      'logs'  => ['view'],
    ];
    if (isset($deny[$module]) && in_array($action, $deny[$module], true)) return false;
    return true;
  }
  // Viewer
  return ($action === 'view');
}

function can_perm(string $module, string $action): bool {
  $uid = current_user_id();
  if (!$uid) return false;

  if (!rbac_ready()) return legacy_can_perm($module, $action);

  // Admin shortcut by legacy role for compatibility
  if (current_user_role() === 'admin') return true;

  $code = $module . '.' . $action;

  // If user has no RBAC roles assigned, fallback to legacy
  $cnt = (int)db_query("SELECT COUNT(*) AS c FROM user_roles WHERE user_id=?", [$uid])->fetchColumn();
  if ($cnt <= 0) return legacy_can_perm($module, $action);

  $row = db_query(
    "SELECT 1
     FROM user_roles ur
     JOIN roles r ON r.id = ur.role_id AND r.is_active = 1
     JOIN role_permissions rp ON rp.role_id = r.id AND rp.allowed = 1
     JOIN permissions p ON p.id = rp.permission_id
     WHERE ur.user_id = ? AND p.code = ?
     LIMIT 1",
    [$uid, $code]
  )->fetchColumn();

  return (bool)$row;
}

function require_permission(string $module, string $action): void {
  if (!can_perm($module, $action)) {
    redirect('index.php?page=projects&err=forbidden');
    exit;
  }
}
