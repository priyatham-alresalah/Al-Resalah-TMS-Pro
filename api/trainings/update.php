<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$id = $_POST['id'];
$newStatus = $_POST['status'];
$newDate = $_POST['training_date'] ?? null;
$trainer = $_POST['trainer_id'] ?? null;

/* Supabase context */
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Fetch existing training */
$current = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?id=eq.$id&select=status",
    false,
    $ctx
  ),
  true
)[0];

/* Update training */
$payload = [
  'trainer_id'    => $trainer,
  'training_date' => $newDate,
  'status'        => $newStatus
];

file_get_contents(
  SUPABASE_URL . "/rest/v1/trainings?id=eq.$id",
  false,
  stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' =>
        "Content-Type: application/json\r\n" .
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE,
      'content' => json_encode($payload)
    ]
  ])
);

/* ===========================
   AUTO CERTIFICATE CREATION
=========================== */
if ($current['status'] !== 'completed' && $newStatus === 'completed') {

  /* Check if certificate already exists */
  $existing = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/certificates?training_id=eq.$id",
      false,
      $ctx
    ),
    true
  );

  if (empty($existing)) {

    /* Generate certificate number */
    $year = date('Y');

    $count = json_decode(
      file_get_contents(
        SUPABASE_URL . "/rest/v1/certificates?select=id",
        false,
        $ctx
      ),
      true
    );

    $seq = str_pad(count($count) + 1, 4, '0', STR_PAD_LEFT);
    $certNo = "AR-$year-$seq";

    /* Insert certificate */
    $certPayload = [
      'training_id'    => $id,
      'certificate_no' => $certNo,
      'issued_date'    => date('Y-m-d'),
      'issued_by'      => $_SESSION['user']['id']
    ];

    file_get_contents(
      SUPABASE_URL . "/rest/v1/certificates",
      false,
      stream_context_create([
        'http' => [
          'method' => 'POST',
          'header' =>
            "Content-Type: application/json\r\n" .
            "apikey: " . SUPABASE_SERVICE . "\r\n" .
            "Authorization: Bearer " . SUPABASE_SERVICE,
          'content' => json_encode($certPayload)
        ]
      ])
    );
  }
}

header('Location: /trainings.php');
