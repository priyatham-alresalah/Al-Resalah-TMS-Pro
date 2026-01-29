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
require '../../includes/branch.php';

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

// Branch isolation: Verify all invoices belong to user's branch
// BATCH FETCH all invoices at once (optimization)
$branchId = getUserBranchId();
// Supabase uses in.(value1,value2) format
$invoiceIdsList = implode(',', $invoiceIds);

if ($branchId !== null) {
  $branchCheckInvoices = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/invoices?id=in.($invoiceIdsList)&select=id,branch_id",
      false,
      $ctx
    ),
    true
  ) ?: [];
  
  foreach ($branchCheckInvoices as $inv) {
    if (isset($inv['branch_id']) && $inv['branch_id'] !== $branchId) {
      header('Location: ' . BASE_PATH . '/pages/payments.php?error=' . urlencode("Cannot process payment: Invoice {$inv['id']} belongs to another branch"));
      exit;
    }
  }
}

// Create payment record
$paymentData = [
  'payment_mode' => $paymentMode,
  'reference_no' => $referenceNo,
  'amount' => $totalAmount,
  'paid_on' => $paidOn,
  'invoice_id' => $invoiceIds[0] // Primary invoice
];

// Add branch_id if user is branch-restricted
if ($branchId !== null) {
  $paymentData['branch_id'] = $branchId;
}

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

// BATCH FETCH: Get all invoices and allocations in single queries (optimization)
$invoiceIdsList = implode(',', $invoiceIds);
$allInvoices = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/invoices?id=in.($invoiceIdsList)&select=id,total,status",
    false,
    $ctx
  ),
  true
) ?: [];

// Create invoice map for quick lookup
$invoiceMap = [];
foreach ($allInvoices as $inv) {
  $invoiceMap[$inv['id']] = $inv;
}

// BATCH FETCH: Get all existing allocations for all invoices at once
$allExistingAllocations = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/payment_allocations?invoice_id=in.($invoiceIdsList)&select=invoice_id,allocated_amount",
    false,
    $ctx
  ),
  true
) ?: [];

// Group allocations by invoice_id
$allocationsByInvoice = [];
foreach ($allExistingAllocations as $alloc) {
  $invId = $alloc['invoice_id'];
  if (!isset($allocationsByInvoice[$invId])) {
    $allocationsByInvoice[$invId] = 0;
  }
  $allocationsByInvoice[$invId] += floatval($alloc['allocated_amount']);
}

foreach ($invoiceIds as $index => $invoiceId) {
  $allocatedAmount = floatval($amounts[$index] ?? 0);
  
  if ($allocatedAmount <= 0) {
    continue;
  }

  // Validate invoice exists (from batch fetch)
  if (!isset($invoiceMap[$invoiceId])) {
    header('Location: ' . BASE_PATH . '/pages/payments.php?error=' . urlencode("Invoice $invoiceId not found"));
    exit;
  }

  $invoice = $invoiceMap[$invoiceId];
  $invoiceTotal = floatval($invoice['total']);
  $invoiceStatus = $invoice['status'] ?? 'unpaid';

  // Get existing allocations sum (from batch fetch)
  $existingAllocatedSum = $allocationsByInvoice[$invoiceId] ?? 0;

  // Prevent overpayment: new allocation + existing allocations must not exceed invoice total
  $newTotalAllocated = $existingAllocatedSum + $allocatedAmount;
  if ($newTotalAllocated > $invoiceTotal) {
    $overpayment = $newTotalAllocated - $invoiceTotal;
    header('Location: ' . BASE_PATH . '/pages/payments.php?error=' . urlencode("Overpayment detected: Allocation would exceed invoice total by " . number_format($overpayment, 2) . ". Maximum allocation allowed: " . number_format($invoiceTotal - $existingAllocatedSum, 2)));
    exit;
  }

  // Prevent allocation to already paid invoices
  if ($invoiceStatus === 'paid' && $existingAllocatedSum >= $invoiceTotal) {
    header('Location: ' . BASE_PATH . '/pages/payments.php?error=' . urlencode("Invoice $invoiceId is already fully paid"));
    exit;
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

  $allocationResponse = @file_get_contents(
    SUPABASE_URL . "/rest/v1/payment_allocations",
    false,
    $allocationCtx
  );

  if ($allocationResponse === false) {
    error_log("Failed to create payment allocation for invoice: $invoiceId");
    // Rollback: Delete payment record if allocation fails
    $deleteCtx = stream_context_create([
      'http' => [
        'method' => 'DELETE',
        'header' => $headers
      ]
    ]);
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/payments?id=eq.$paymentId",
      false,
      $deleteCtx
    );
    header('Location: ' . BASE_PATH . '/pages/payments.php?error=' . urlencode("Failed to allocate payment to invoice $invoiceId. Payment was cancelled."));
    exit;
  }

  // Update invoice status based on new total allocated
  $newStatus = ($newTotalAllocated >= $invoiceTotal) ? 'paid' : 'unpaid';
  
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

// Audit log
auditLog('payments', 'create', $paymentId, [
  'total_amount' => $totalAmount,
  'payment_mode' => $paymentMode,
  'invoice_count' => count($invoiceIds)
]);

header('Location: ' . BASE_PATH . '/pages/payments.php?success=' . urlencode('Payment recorded successfully'));
exit;
