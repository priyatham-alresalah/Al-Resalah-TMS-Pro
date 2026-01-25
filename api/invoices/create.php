<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit;
}

$data = json_encode([
  'invoice_no'  => 'INV-' . date('Ymd-His'),
  'client_id'   => $_POST['client_id'],
  'training_id' => $_POST['training_id'],
  'amount'      => $_POST['amount'],
  'vat'         => $_POST['vat'],
  'total'       => $_POST['amount'] + $_POST['vat'],
  'status'      => 'issued',
  'issued_date' => date('Y-m-d'),
  'due_date'    => date('Y-m-d', strtotime('+14 days'))
]);

file_get_contents(
  SUPABASE_URL . "/rest/v1/invoices",
  false,
  stream_context_create([
    'http' => [
      'method'  => 'POST',
      'header'  =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE . "\r\n" .
        "Content-Type: application/json",
      'content' => $data
    ]
  ])
);

header("Location: ../../invoices.php");
exit;
