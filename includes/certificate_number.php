<?php
/**
 * Generate next certificate number atomically
 * Uses retry logic to handle race conditions
 * @return string Certificate number (e.g., ARC-2026-0001)
 * @throws Exception If generation fails after retries
 */
function getNextCertificateNumber() {
  $year = date('Y');
  $maxRetries = 10;
  $retryCount = 0;

  $headers =
    "Content-Type: application/json\r\n" .
    "apikey: " . SUPABASE_SERVICE . "\r\n" .
    "Authorization: Bearer " . SUPABASE_SERVICE;

  while ($retryCount < $maxRetries) {
    $ctxGet = stream_context_create([
      'http' => [
        'method' => 'GET',
        'header' => $headers
      ]
    ]);

    $response = @file_get_contents(
      SUPABASE_URL . "/rest/v1/certificate_counters?year=eq.$year&limit=1",
      false,
      $ctxGet
    );

    if ($response === false) {
      // Counter doesn't exist, create it
      $createData = [
        'year' => $year,
        'last_number' => 0
      ];
      $ctxCreate = stream_context_create([
        'http' => [
          'method' => 'POST',
          'header' => $headers,
          'content' => json_encode($createData)
        ]
      ]);
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/certificate_counters",
        false,
        $ctxCreate
      );
      $lastNumber = 0;
    } else {
      $row = json_decode($response, true);
      if (empty($row)) {
        // Counter doesn't exist, create it
        $createData = [
          'year' => $year,
          'last_number' => 0
        ];
        $ctxCreate = stream_context_create([
          'http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => json_encode($createData)
          ]
        ]);
        @file_get_contents(
          SUPABASE_URL . "/rest/v1/certificate_counters",
          false,
          $ctxCreate
        );
        $lastNumber = 0;
      } else {
        $lastNumber = intval($row[0]['last_number'] ?? 0);
      }
    }

    $next = $lastNumber + 1;

    // Update counter
    $payload = json_encode(['last_number' => $next]);
    $ctxPatch = stream_context_create([
      'http' => [
        'method' => 'PATCH',
        'header' => $headers,
        'content' => $payload
      ]
    ]);

    $updateResponse = @file_get_contents(
      SUPABASE_URL . "/rest/v1/certificate_counters?year=eq.$year",
      false,
      $ctxPatch
    );

    if ($updateResponse !== false) {
      // Verify the update succeeded by checking the new value
      $verifyResponse = @file_get_contents(
        SUPABASE_URL . "/rest/v1/certificate_counters?year=eq.$year&limit=1",
        false,
        $ctxGet
      );
      
      if ($verifyResponse !== false) {
        $verifyRow = json_decode($verifyResponse, true);
        if (!empty($verifyRow) && intval($verifyRow[0]['last_number']) === $next) {
          // Update successful, return certificate number
          return "ARC-$year-" . str_pad($next, 4, '0', STR_PAD_LEFT);
        }
      }
    }

    // Update failed or verification failed, retry with backoff
    $retryCount++;
    usleep(50000 * $retryCount); // 50ms, 100ms, 150ms...
  }

  // All retries failed
  throw new Exception("Failed to generate certificate number after $maxRetries retries");
}
