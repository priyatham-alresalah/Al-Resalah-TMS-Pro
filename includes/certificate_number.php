<?php
function getNextCertificateNumber() {

  $year = date('Y');

  $headers =
    "Content-Type: application/json\r\n" .
    "apikey: " . SUPABASE_SERVICE . "\r\n" .
    "Authorization: Bearer " . SUPABASE_SERVICE;

  /* LOCK ROW */
  $ctxGet = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' => $headers
    ]
  ]);

  $row = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/certificate_counters?year=eq.$year&limit=1",
      false,
      $ctxGet
    ),
    true
  )[0];

  $next = $row['last_number'] + 1;

  /* UPDATE */
  $payload = json_encode(['last_number' => $next]);

  $ctxPatch = stream_context_create([
    'http' => [
      'method' => 'PATCH',
      'header' => $headers,
      'content' => $payload
    ]
  ]);

  file_get_contents(
    SUPABASE_URL . "/rest/v1/certificate_counters?year=eq.$year",
    false,
    $ctxPatch
  );

  return "ARC-$year-" . str_pad($next, 4, '0', STR_PAD_LEFT);
}
