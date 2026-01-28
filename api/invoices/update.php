<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';
require '../../includes/rbac.php';
require '../../includes/branch.php';

/* CSRF Protection */
requireCSRF();

/* Permission Check */
requirePermission('invoices', 'update');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../../pages/invoices.php?error=' . urlencode('Invalid request'));
  exit;
}

$id = trim($_POST['id'] ?? '');
$invoice_no = trim($_POST['invoice_no'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);
$vat = floatval($_POST['vat'] ?? 0);
$total = floatval($_POST['total'] ?? 0);
$status = trim($_POST['status'] ?? 'issued');
$issued_date = $_POST['issued_date'] ?? null;
$due_date = $_POST['due_date'] ?? null;

if (empty($id) || empty($invoice_no) || $amount <= 0) {
  header('Location: ../../pages/invoice_edit.php?id=' . $id . '&error=' . urlencode('Please fill all required fields'));
  exit;
}

// Branch isolation: Check if invoice belongs to user's branch
$branchId = getUserBranchId();
if ($branchId !== null) {
  $invoiceBranch = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/invoices?id=eq.$id&select=branch_id",
      false,
      stream_context_create([
        'http' => [
          'method' => 'GET',
          'header' =>
            "apikey: " . SUPABASE_SERVICE . "\r\n" .
            "Authorization: Bearer " . SUPABASE_SERVICE
        ]
      ])
    ),
    true
  );

  if (!empty($invoiceBranch) && isset($invoiceBranch[0]['branch_id']) && $invoiceBranch[0]['branch_id'] !== $branchId) {
    http_response_code(403);
    die('Access denied: Cannot update invoice from another branch');
  }
}

// Prevent manual status change to 'paid' without payment record
if ($status === 'paid') {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);

  // Check if payment allocations exist
  $allocations = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/payment_allocations?invoice_id=eq.$id&select=allocated_amount",
      false,
      $ctx
    ),
    true
  );

  if (empty($allocations)) {
    header('Location: ../../pages/invoice_edit.php?id=' . $id . '&error=' . urlencode('Cannot set invoice to paid: No payment record exists. Please record payment first.'));
    exit;
  }

  // Verify total allocated >= invoice total
  $allocatedSum = 0;
  foreach ($allocations as $alloc) {
    $allocatedSum += floatval($alloc['allocated_amount']);
  }

  $currentInvoice = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/invoices?id=eq.$id&select=total",
      false,
      $ctx
    ),
    true
  );

  $invoiceTotal = !empty($currentInvoice) ? floatval($currentInvoice[0]['total']) : $total;

  if ($allocatedSum < $invoiceTotal) {
    header('Location: ../../pages/invoice_edit.php?id=' . $id . '&error=' . urlencode('Cannot set invoice to paid: Payment allocation (' . number_format($allocatedSum, 2) . ') is less than invoice total (' . number_format($invoiceTotal, 2) . ')'));
    exit;
  }
}

$data = [
  'invoice_no' => $invoice_no,
  'amount' => $amount,
  'vat' => $vat,
  'total' => $total,
  'status' => $status
];

if ($issued_date) {
  $data['issued_date'] = $issued_date;
}

if ($due_date) {
  $data['due_date'] = $due_date;
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($data)
  ]
]);

$updateResponse = @file_get_contents(
  SUPABASE_URL . "/rest/v1/invoices?id=eq.$id",
  false,
  $ctx
);

if ($updateResponse === false) {
  error_log("Failed to update invoice: $id");
  header('Location: ../../pages/invoice_edit.php?id=' . $id . '&error=' . urlencode('Failed to update invoice. Please try again.'));
  exit;
}

// Audit log
require '../../includes/audit_log.php';
auditLog('invoices', 'update', $id, [
  'status' => $status,
  'total' => $total
]);

header('Location: ../../pages/invoices.php?success=' . urlencode('Invoice updated successfully'));
exit;
