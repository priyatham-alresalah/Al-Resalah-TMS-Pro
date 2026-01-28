<?php
/**
 * Role-Based Access Control (RBAC) Helper
 * Checks user permissions based on roles and permissions tables
 */

require __DIR__ . '/config.php';
require __DIR__ . '/auth_check.php';

/**
 * Check if user has permission for a module and action
 * @param string $module Module name (e.g., 'inquiries', 'quotations')
 * @param string $action Action name (e.g., 'create', 'update', 'delete', 'view')
 * @return bool
 */
function hasPermission($module, $action) {
  if (!isset($_SESSION['user']['id'], $_SESSION['user']['role'])) {
    return false;
  }

  $role = $_SESSION['user']['role'];
  $userId = $_SESSION['user']['id'];

  // Admin has all permissions
  if ($role === 'admin') {
    return true;
  }

  // Build Supabase query to check permissions
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);

  // First, get permission ID for this module/action
  $permissionUrl = SUPABASE_URL . "/rest/v1/permissions?module=eq.$module&action=eq.$action&select=id";
  $permissionResponse = @file_get_contents($permissionUrl, false, $ctx);
  
  if ($permissionResponse === false) {
    error_log("Failed to check permission: $module/$action");
    return false;
  }

  $permissions = json_decode($permissionResponse, true);
  if (empty($permissions)) {
    // Permission doesn't exist - deny by default
    return false;
  }

  $permissionId = $permissions[0]['id'];

  // Check if role has this permission
  $rolePermissionUrl = SUPABASE_URL . "/rest/v1/role_permissions?role_name=eq.$role&permission_id=eq.$permissionId&select=role_name";
  $rolePermissionResponse = @file_get_contents($rolePermissionUrl, false, $ctx);
  
  if ($rolePermissionResponse === false) {
    return false;
  }

  $rolePermissions = json_decode($rolePermissionResponse, true);
  return !empty($rolePermissions);
}

/**
 * Require permission or die with access denied
 * @param string $module
 * @param string $action
 */
function requirePermission($module, $action) {
  if (!hasPermission($module, $action)) {
    http_response_code(403);
    die('Access denied: You do not have permission to ' . $action . ' ' . $module);
  }
}

/**
 * Check if user can access a module (any action)
 * @param string $module
 * @return bool
 */
function canAccessModule($module) {
  $actions = ['view', 'create', 'update', 'delete'];
  foreach ($actions as $action) {
    if (hasPermission($module, $action)) {
      return true;
    }
  }
  return false;
}

/**
 * Get user's role
 * @return string|null
 */
function getUserRole() {
  return $_SESSION['user']['role'] ?? null;
}

/**
 * Check if user is admin
 * @return bool
 */
function isAdmin() {
  return getUserRole() === 'admin';
}
