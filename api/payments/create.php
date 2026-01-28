<?php
/**
 * Create Payment and Allocate to Invoice(s)
 * Updates invoice status based on payment allocation
 */
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/audit_log.php';

/* CSRF Protection */
requireCSRF();

/* Permission Check */
requirePermission('payments', 'create');

$invoiceIds = $_POST['invoice_ids'] ?? [];
$amounts = $_POST['amounts'] ?? [];
$paymentMode = trim($_POST['payment_mode'] ?? '');
$referenceNo = trim($_POST['reference_no'] ?? '');
$paidOn = trim($_POST['paid_on'] ?? date('Y-m-d'));
$totalAmount = floatval($_POST['total_amount'] ?? 0);

if (empty($invoiceIds) || empty($paymentMode) || $totalAmount <= 0) {
  header('Location: ' . BASE_PATH . '/pages/payments.php?error=' . urlencode('Invalid payment data'));
  exit;
}

// Validate allocation amounts match total
$allocatedTotal = 0;
foreach ($amounts as $amount) {
  $allocatedTotal += floatval($amount);
}

if (abs($allocatedTotal - $totalAmount) > 0.01) {
  header('Location: ' . BASE_PATH . '/pages/payments.php?error=' . urlencode('Payment allocation amounts do not match total'));
  exit;
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

// Create payment record
$paymentData = [
  'payment_mode' => $paymentMode,
  'reference_no' => $referenceNo,
  'amount' => $totalAmount,
  'paid_on' => $paidOn,
  'invoice_id' => $invoiceIds[0] // Primary invoice
];

$createCtx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($paymentData)
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/payments",
  false,
  $createCtx
);

if ($response === false) {
  error_log("Failed to create payment");
  header('Location: ' . BASE_PATH . '/pages/payments.php?error=' . urlencode('Failed to create payment. Please try again.'));
  exit;
}

$payment = json_decode($response, true);
$paymentId = $payment['id'] ?? null;

if (!$paymentId) {
  header('Location: ' . BASE_PATH . '/pages/payments.php?error=' . urlencode('Failed to create payment'));
  exit;
}

// Create payment allocations
$headers = "Content-Type: application/json\r\n" .
  "apikey: " . SUPABASE_SERVICE . "\r\n" .
  "Authorization: Bearer " . SUPABASE_SERVICE;

foreach ($invoiceIds as $index => $invoiceId) {
  $allocatedAmount = floatval($amounts[$index] ?? 0);
  
  if ($allocatedAmount <= 0) {
    continue;
  }

  $allocationData = [
    'payment_id' => $paymentId,
    'invoice_id' => $invoiceId,
    'allocated_amount' => $allocatedAmount
  ];

  $allocationCtx = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => $headers,
      'content' => json_encode($allocationData)
    ]
  ]);

  @file_get_contents(
    SUPABASE_URL . "/rest/v1/payment_allocations",
    false,
    $allocationCtx
  );

  // Check if invoice is fully paid
  $invoice = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/invoices?id=eq.$invoiceId&select=id,total",
      false,
      $ctx
    ),
    true
  );

  if (!empty($invoice)) {
    $invoiceTotal = floatval($invoice[0]['total']);
    
    // Get total allocated for this invoice
    $totalAllocated = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/payment_allocations?invoice_id=eq.$invoiceId&select=allocated_amount",
        false,
        $ctx
      ),
      true
    );

    $allocatedSum = 0;
    foreach ($totalAllocated as $alloc) {
      $allocatedSum += floatval($alloc['allocated_amount']);
    }

    // Update invoice status
    $newStatus = ($allocatedSum >= $invoiceTotal) ? 'paid' : 'unpaid';
    
    $updateInvoiceCtx = stream_context_create([
      'http' => [
        'method' => 'PATCH',
        'header' => $headers,
        'content' => json_encode(['status' => $newStatus])
      ]
    ]);

    @file_get_contents(
      SUPABASE_URL . "/rest/v1/invoices?id=eq.$invoiceId",
      false,
      $updateInvoiceCtx
    );
  }
}

// Audit log
auditLog('payments', 'create', $paymentId, [
  'total_amount' => $totalAmount,
  'payment_mode' => $paymentMode,
  'invoice_count' => count($invoiceIds)
]);

header('Location: ' . BASE_PATH . '/pages/payments.php?success=' . urlencode('Payment recorded successfully'));
exit;
