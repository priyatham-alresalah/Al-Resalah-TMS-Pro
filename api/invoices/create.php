<?php
/**
 * Create Invoice
 * Enforces: Certificate issued → Invoice
 */
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/workflow.php';
require '../../includes/audit_log.php';

/* CSRF Protection */
requireCSRF();

/* Permission Check */
requirePermission('invoices', 'create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit;
}

$trainingId = trim($_POST['training_id'] ?? '');
$clientId = trim($_POST['client_id'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);
$vat = floatval($_POST['vat'] ?? 0);
$total = floatval($_POST['total'] ?? $amount + $vat);

if (empty($trainingId) || empty($clientId) || $amount <= 0) {
  header('Location: ' . BASE_PATH . '/pages/invoices.php?error=' . urlencode('Invalid invoice data'));
  exit;
}

// Enforce workflow - check prerequisites
$workflowCheck = canCreateInvoice($trainingId);
if (!$workflowCheck['allowed']) {
  header('Location: ' . BASE_PATH . '/pages/invoices.php?error=' . urlencode($workflowCheck['reason']));
  exit;
}

// Generate invoice number
$invoiceNo = 'INV-' . date('Ymd-His');

$invoiceData = [
  'invoice_no' => $invoiceNo,
  'client_id' => $clientId,
  'training_id' => $trainingId,
  'amount' => $amount,
  'vat' => $vat,
  'total' => $total,
  'status' => 'unpaid',
  'issued_date' => date('Y-m-d'),
  'due_date' => date('Y-m-d', strtotime('+14 days'))
];

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($invoiceData)
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/invoices",
  false,
  $ctx
);

if ($response === false) {
  error_log("Failed to create invoice for training: $trainingId");
  header('Location: ' . BASE_PATH . '/pages/invoices.php?error=' . urlencode('Failed to create invoice. Please try again.'));
  exit;
}

$invoice = json_decode($response, true);
$invoiceId = $invoice['id'] ?? null;

if ($invoiceId) {
  // Update training checkpoint
  $userId = $_SESSION['user']['id'];
  $checkpointCtx = stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode([
        'completed' => true,
        'completed_by' => $userId,
        'completed_at' => date('Y-m-d H:i:s')
      ])
    ]
  ]);
  
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/training_checkpoints?training_id=eq.$trainingId&checkpoint=eq.invoice_ready",
    false,
    $checkpointCtx
  );

  // Audit log
  auditLog('invoices', 'create', $invoiceId, [
    'invoice_no' => $invoiceNo,
    'training_id' => $trainingId,
    'total' => $total
  ]);
}

header('Location: ' . BASE_PATH . '/pages/invoices.php?success=' . urlencode("Invoice created successfully: $invoiceNo"));
exit;
