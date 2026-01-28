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
$branchId = getUserBranchId();
if ($branchId !== null) {
  foreach ($invoiceIds as $invoiceId) {
    $invoice = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/invoices?id=eq.$invoiceId&select=branch_id",
        false,
        $ctx
      ),
      true
    );

    if (!empty($invoice) && isset($invoice[0]['branch_id']) && $invoice[0]['branch_id'] !== $branchId) {
      header('Location: ' . BASE_PATH . '/pages/payments.php?error=' . urlencode("Cannot process payment: Invoice $invoiceId belongs to another branch"));
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

foreach ($invoiceIds as $index => $invoiceId) {
  $allocatedAmount = floatval($amounts[$index] ?? 0);
  
  if ($allocatedAmount <= 0) {
    continue;
  }

  // Validate invoice exists and get total
  $invoice = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/invoices?id=eq.$invoiceId&select=id,total,status",
      false,
      $ctx
    ),
    true
  );

  if (empty($invoice)) {
    header('Location: ' . BASE_PATH . '/pages/payments.php?error=' . urlencode("Invoice $invoiceId not found"));
    exit;
  }

  $invoiceTotal = floatval($invoice[0]['total']);
  $invoiceStatus = $invoice[0]['status'] ?? 'unpaid';

  // Get existing allocations BEFORE adding new one
  $existingAllocations = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/payment_allocations?invoice_id=eq.$invoiceId&select=allocated_amount",
      false,
      $ctx
    ),
    true
  );

  $existingAllocatedSum = 0;
  foreach ($existingAllocations as $alloc) {
    $existingAllocatedSum += floatval($alloc['allocated_amount']);
  }

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
    continue; // Continue with other allocations
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
