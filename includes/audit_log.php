<?php
/**
 * Audit Logging Helper
 * Logs all critical actions for compliance and security
 */

require __DIR__ . '/config.php';

/**
 * Log an action to audit_logs table
 * @param string $module Module name (e.g., 'inquiries', 'quotations')
 * @param string $action Action name (e.g., 'create', 'update', 'delete')
 * @param string|null $recordId Record ID (UUID)
 * @param array|null $additionalData Additional data to log
 */
function auditLog($module, $action, $recordId = null, $additionalData = null) {
  if (!isset($_SESSION['user']['id'])) {
    return; // Cannot log without user context
  }

  $userId = $_SESSION['user']['id'];
  
  $logData = [
    'user_id' => $userId,
    'module' => $module,
    'action' => $action,
    'record_id' => $recordId,
    'created_at' => date('Y-m-d H:i:s')
  ];

  // Add additional data as JSON if provided
  if ($additionalData !== null) {
    $logData['additional_data'] = json_encode($additionalData);
  }

  $ctx = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode($logData)
    ]
  ]);

  @file_get_contents(
    SUPABASE_URL . "/rest/v1/audit_logs",
    false,
    $ctx
  );
}
