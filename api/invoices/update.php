<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../../pages/invoices.php?error=' . urlencode('Invalid request'));
  exit;
}

$id = $_POST['id'] ?? '';
$invoice_no = trim($_POST['invoice_no'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);
$vat = floatval($_POST['vat'] ?? 0);
$total = floatval($_POST['total'] ?? 0);
$status = $_POST['status'] ?? 'issued';
$issued_date = $_POST['issued_date'] ?? null;
$due_date = $_POST['due_date'] ?? null;

if (empty($id) || empty($invoice_no) || $amount <= 0) {
  header('Location: ../../pages/invoice_edit.php?id=' . $id . '&error=' . urlencode('Please fill all required fields'));
  exit;
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

file_get_contents(
  SUPABASE_URL . "/rest/v1/invoices?id=eq.$id",
  false,
  $ctx
);

header('Location: ../../pages/invoices.php?success=' . urlencode('Invoice updated successfully'));
exit;
