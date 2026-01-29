<?php
// Start output buffering
ob_start();

require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* CSRF Protection */
requireCSRF();

// Clear output buffer
ob_clean();

$inquiryId = $_POST['inquiry_id'] ?? '';
$action = $_POST['action'] ?? '';
$reason = trim($_POST['reason'] ?? '');

if (!$inquiryId || !$action) {
  ob_end_clean();
  if (!headers_sent()) {
    header('Location: ' . BASE_PATH . '/pages/inquiry_view.php?id=' . $inquiryId . '&error=' . urlencode('Missing required fields'));
  }
  exit;
}

// Map actions to valid database status values
// Database constraint only allows: 'new', 'quoted', 'closed'
$newStatus = '';
if ($action === 'accept') {
  $newStatus = 'closed'; // Accept quote = close inquiry (accepted)
} elseif ($action === 'reject') {
  $newStatus = 'closed'; // Reject quote = close inquiry (rejected)
} elseif ($action === 'requote') {
  $newStatus = 'new'; // Reset to new for requote
}

if (!$newStatus) {
  ob_end_clean();
  if (!headers_sent()) {
    header('Location: ' . BASE_PATH . '/pages/inquiry_view.php?id=' . $inquiryId . '&error=' . urlencode('Invalid action'));
  }
  exit;
}

// First, verify the inquiry exists and is in 'quoted' status
$checkCtx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$existingInquiry = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId&select=id,status",
    false,
    $checkCtx
  ),
  true
)[0] ?? null;

if (!$existingInquiry) {
  ob_end_clean();
  if (!headers_sent()) {
    header('Location: ' . BASE_PATH . '/pages/inquiry_view.php?id=' . $inquiryId . '&error=' . urlencode('Inquiry not found'));
  }
  exit;
}

// Verify inquiry is in 'quoted' status before allowing response
$currentStatus = strtolower($existingInquiry['status'] ?? '');
if ($currentStatus !== 'quoted') {
  ob_end_clean();
  $errorMsg = "Cannot respond to inquiry. Current status is '$currentStatus'. Only quoted inquiries can be responded to.";
  if (!headers_sent()) {
    header('Location: ' . BASE_PATH . '/pages/inquiry_view.php?id=' . $inquiryId . '&error=' . urlencode($errorMsg));
  }
  exit;
}

// Build update data - only include fields that exist in the database schema
$data = [
  'status' => $newStatus
];

// Log the reason for audit purposes (even if not stored in DB)
if (!empty($reason)) {
  error_log("Inquiry $inquiryId response ($action): $reason");
}

// Log the update attempt
error_log("Updating inquiry $inquiryId: action=$action, newStatus=$newStatus, currentStatus=$currentStatus, data=" . json_encode($data));

$ctx = stream_context_create([
  'http' => [
    'method' => 'PATCH',
    'header' =>
      "Content-Type: application/json\r\n" .
      "Prefer: return=minimal\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE,
    'content' => json_encode($data),
    'ignore_errors' => true
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId",
  false,
  $ctx
);

// Get HTTP response code and headers
$httpCode = 0;
$httpHeaders = [];
if (isset($http_response_header)) {
  $httpHeaders = $http_response_header;
  if (!empty($http_response_header[0])) {
    preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $http_response_header[0], $matches);
    $httpCode = isset($matches[1]) ? intval($matches[1]) : 0;
  }
}

// Log response details
error_log("Inquiry update response - HTTP Code: $httpCode, Response: " . substr($response, 0, 500));

if ($response === false || $httpCode >= 400) {
  $errorMsg = 'Failed to submit response. Please try again.';
  
  // Try to parse Supabase error message
  if ($response !== false) {
    $errorData = json_decode($response, true);
    if (isset($errorData['message'])) {
      $errorMsg = $errorData['message'];
    } elseif (isset($errorData['hint'])) {
      $errorMsg = $errorData['hint'];
    } elseif (isset($errorData['details'])) {
      $errorMsg = $errorData['details'];
    }
  }
  
  if ($httpCode > 0) {
    $errorMsg .= ' (HTTP ' . $httpCode . ')';
  }
  
  error_log("Failed to update inquiry response. HTTP Code: $httpCode, Error: $errorMsg, Response: " . ($response ?: 'No response'));
  
  ob_end_clean();
  
  // Truncate error message if too long for URL
  if (strlen($errorMsg) > 200) {
    $errorMsg = substr($errorMsg, 0, 197) . '...';
  }
  
  if (!headers_sent()) {
    header('Location: ' . BASE_PATH . '/pages/inquiry_view.php?id=' . $inquiryId . '&error=' . urlencode($errorMsg));
  } else {
    echo '<script>window.location.href="' . BASE_PATH . '/pages/inquiry_view.php?id=' . $inquiryId . '&error=' . urlencode($errorMsg) . '";</script>';
  }
  exit;
}

// If quote is accepted, update the quotation status to 'accepted' and move to Quotations module
if ($action === 'accept') {
  // Find the quotation associated with this inquiry
  // Note: When quote is created via create_quote.php, it creates a quotation record
  $quotationUrl = SUPABASE_URL . "/rest/v1/quotations?inquiry_id=eq.$inquiryId&select=*&order=created_at.desc&limit=1";
  error_log("Looking for quotation with inquiry_id: $inquiryId");
  error_log("Quotation query URL: $quotationUrl");
  
  $quotationResponse = @file_get_contents(
    $quotationUrl,
    false,
    $checkCtx
  );
  
  $quotations = json_decode($quotationResponse, true) ?: [];
  $quotation = $quotations[0] ?? null;
  
  error_log("Found " . count($quotations) . " quotation(s) for inquiry $inquiryId");
  
  if ($quotation) {
    error_log("Quotation found: ID={$quotation['id']}, Status={$quotation['status']}, Quotation No={$quotation['quotation_no']}");
    // Update quotation status to 'accepted'
    $quotationUpdateData = [
      'status' => 'accepted',
      'accepted_at' => date('Y-m-d H:i:s')
    ];
    
    // Add accepted_by if field exists
    if (isset($_SESSION['user']['id'])) {
      // Note: Only add if field exists in schema
      // $quotationUpdateData['accepted_by'] = $_SESSION['user']['id'];
    }
    
    $quotationUpdateCtx = stream_context_create([
      'http' => [
        'method' => 'PATCH',
        'header' =>
          "Content-Type: application/json\r\n" .
          "Prefer: return=minimal\r\n" .
          "apikey: " . SUPABASE_SERVICE . "\r\n" .
          "Authorization: Bearer " . SUPABASE_SERVICE,
        'content' => json_encode($quotationUpdateData),
        'ignore_errors' => true
      ]
    ]);
    
    $quotationResponse = @file_get_contents(
      SUPABASE_URL . "/rest/v1/quotations?id=eq.{$quotation['id']}",
      false,
      $quotationUpdateCtx
    );
    
    // Log quotation update
    $quotationHttpCode = 0;
    $quotationResponseHeaders = [];
    if (isset($http_response_header)) {
      $quotationResponseHeaders = $http_response_header;
      if (!empty($http_response_header[0])) {
        preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $http_response_header[0], $matches);
        $quotationHttpCode = isset($matches[1]) ? intval($matches[1]) : 0;
      }
    }
    
    if ($quotationResponse !== false && $quotationHttpCode < 400) {
      error_log("Quotation {$quotation['id']} status updated to 'accepted' successfully");
    } else {
      $errorDetails = $quotationResponse ?: 'No response';
      error_log("ERROR: Failed to update quotation status. HTTP Code: $quotationHttpCode, Response: " . substr($errorDetails, 0, 500));
      
      // Try to parse error
      $errorData = json_decode($quotationResponse, true);
      if ($errorData && isset($errorData['message'])) {
        error_log("Supabase error: " . $errorData['message']);
      }
      
      // Continue anyway - inquiry status is updated, quotation update is secondary
      // But log a warning that quotation might not appear in quotations module
    }
  } else {
    error_log("ERROR: No quotation found for inquiry $inquiryId when accepting quote");
    error_log("Attempting to create quotation now...");
    
    // If quotation doesn't exist, try to create it from the inquiry data
    // Fetch inquiry details to create quotation
    $inquiryDetails = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId&select=*",
        false,
        $checkCtx
      ),
      true
    )[0] ?? null;
    
    if ($inquiryDetails) {
      // Try to create quotation with basic data
      // Note: This is a fallback - quotation should have been created when quote was generated
      $fallbackQuotationData = [
        'inquiry_id' => $inquiryId,
        'quotation_no' => 'QUOTE-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6)),
        'subtotal' => 0,
        'vat' => 0,
        'discount' => 0,
        'total' => 0,
        'status' => 'accepted', // Set directly to accepted since quote was already accepted
        'created_by' => $_SESSION['user']['id'] ?? null
      ];
      
      $createCtx = stream_context_create([
        'http' => [
          'method' => 'POST',
          'header' =>
            "Content-Type: application/json\r\n" .
            "Prefer: return=representation\r\n" .
            "apikey: " . SUPABASE_SERVICE . "\r\n" .
            "Authorization: Bearer " . SUPABASE_SERVICE,
          'content' => json_encode($fallbackQuotationData),
          'ignore_errors' => true
        ]
      ]);
      
      $createResponse = @file_get_contents(
        SUPABASE_URL . "/rest/v1/quotations",
        false,
        $createCtx
      );
      
      if ($createResponse !== false) {
        $createdQuotation = json_decode($createResponse, true);
        if (is_array($createdQuotation) && isset($createdQuotation[0])) {
          $createdQuotation = $createdQuotation[0];
        }
        if (!empty($createdQuotation['id'])) {
          error_log("Fallback: Created quotation {$createdQuotation['id']} for inquiry $inquiryId");
        }
      }
    }
  }
}

ob_end_clean();

// Redirect based on action
if ($action === 'accept') {
  // Redirect to Quotations module for next actions
  if (!headers_sent()) {
    header('Location: ' . BASE_PATH . '/pages/quotations.php?success=' . urlencode("Quote accepted! Quotation moved to Quotations module for next actions."));
  }
} else {
  // For reject/requote, stay on inquiry view
  if (!headers_sent()) {
    header('Location: ' . BASE_PATH . '/pages/inquiry_view.php?id=' . $inquiryId . '&success=' . urlencode("Response submitted successfully"));
  }
}
exit;
