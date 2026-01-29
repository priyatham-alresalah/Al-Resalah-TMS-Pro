<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';
require '../../includes/csrf.php';

/* CSRF Protection */
requireCSRF();

/* Input Validation */
$fullName = trim($_POST['full_name'] ?? '');
$clientId = isset($_POST['client_id']) && $_POST['client_id'] !== '' ? trim($_POST['client_id']) : null;
$email = isset($_POST['email']) && $_POST['email'] !== '' ? trim($_POST['email']) : null;
$phone = isset($_POST['phone']) && $_POST['phone'] !== '' ? trim($_POST['phone']) : null;

// Validate session user
if (empty($_SESSION['user']['id'])) {
  error_log("Candidate create: Missing user session ID");
  header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Session expired. Please login again.'));
  exit;
}

if (empty($fullName)) {
  header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Full name is required'));
  exit;
}

/* Validate email if provided */
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Invalid email address'));
  exit;
}

/* Validate client_id if provided */
if (!empty($clientId)) {
  $checkCtx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);
  
  $clientCheck = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/clients?id=eq.$clientId&select=id",
      false,
      $checkCtx
    ),
    true
  );
  
  if (empty($clientCheck)) {
    header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Invalid client selected'));
    exit;
  }
}

// Prepare data - validate user ID exists in database
$userId = $_SESSION['user']['id'] ?? '';
if (empty($userId)) {
  error_log("Missing user ID in session");
  header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Session expired. Please login again.'));
  exit;
}

// Verify user exists in profiles table
$userCheckCtx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$userExists = json_decode(
  @file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?id=eq.$userId&select=id",
    false,
    $userCheckCtx
  ),
  true
);

if (empty($userExists)) {
  error_log("User ID $userId not found in profiles table");
  header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Invalid user account. Please contact administrator.'));
  exit;
}

// Build data array - only include fields that have values (omit null/empty fields)
$data = [
  'full_name' => $fullName,
  'created_by' => $userId
];

// Only add optional fields if they have valid values
if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $data['email'] = $email;
}
if (!empty($phone)) {
  $data['phone'] = $phone;
}
// Only include client_id if it's provided and not empty
// Note: If client_id column doesn't exist in schema, omit it entirely
if (!empty($clientId)) {
  $data['client_id'] = $clientId;
}

// Log data being sent for debugging
error_log("Creating candidate with data: " . json_encode($data, JSON_PRETTY_PRINT));

$jsonData = json_encode($data);
if ($jsonData === false) {
  error_log("JSON encode error: " . json_last_error_msg());
  header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Invalid data format. Please check your input.'));
  exit;
}

error_log("JSON being sent to Supabase: " . $jsonData);

$ctx = stream_context_create([
  'http' => [
    'method' => 'POST',
    'header' =>
      "Content-Type: application/json\r\n" .
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE . "\r\n" .
      "Prefer: return=representation",
    'content' => $jsonData,
    'ignore_errors' => true  // Don't treat HTTP errors as PHP errors
  ]
]);

$response = @file_get_contents(
  SUPABASE_URL . "/rest/v1/candidates",
  false,
  $ctx
);

// Check for HTTP errors
$httpCode = 0;
$responseHeaders = [];
if (isset($http_response_header)) {
  $responseHeaders = $http_response_header;
  foreach ($http_response_header as $header) {
    if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
      $httpCode = intval($matches[1]);
      break;
    }
  }
}

if ($response === false || $httpCode >= 400) {
  $errorMsg = 'Failed to create candidate. Please try again.';
  
  // If response is empty but we have error headers, check for PostgREST error codes
  if (($response === false || strlen($response) === 0) && $httpCode >= 400) {
    foreach ($responseHeaders as $header) {
      if (stripos($header, 'proxy-status') !== false && stripos($header, 'error=') !== false) {
        if (preg_match('/error=([^;,\s]+)/i', $header, $matches)) {
          $errorCode = $matches[1];
          if ($errorCode === 'PGRST204') {
            $errorMsg = "Invalid data: The request does not match the database schema. Please ensure all required fields are provided and valid.";
          } else {
            $errorMsg = "Database error: $errorCode";
          }
          error_log("PostgREST error code from headers: $errorCode");
        }
      }
    }
    // If still no specific error, create a default error response for parsing
    if ($errorMsg === 'Failed to create candidate. Please try again.' && $httpCode === 400) {
      $response = '{"message": "Bad request: Invalid data format or missing required fields", "code": "PGRST204", "hint": "Check that all required fields are provided and match the expected format"}';
    }
  }
  
  // Try to get error details from response
  if ($response !== false && strlen($response) > 0) {
    // Log raw response first
    error_log("Raw Supabase response: " . $response);
    
    $errorData = json_decode($response, true);
    
    // Supabase error format: {"message": "...", "hint": "...", "code": "...", "details": "..."}
    if (is_array($errorData)) {
      if (isset($errorData['message'])) {
        $errorMsg = $errorData['message'];
        if (isset($errorData['hint']) && !empty($errorData['hint'])) {
          $errorMsg .= ' Hint: ' . $errorData['hint'];
        }
        if (isset($errorData['details']) && !empty($errorData['details'])) {
          $errorMsg .= ' Details: ' . $errorData['details'];
        }
        if (isset($errorData['code']) && !empty($errorData['code'])) {
          $errorMsg .= ' Code: ' . $errorData['code'];
        }
      } elseif (isset($errorData['error'])) {
        $errorMsg = is_string($errorData['error']) ? $errorData['error'] : json_encode($errorData['error']);
      } elseif (isset($errorData['hint'])) {
        $errorMsg = $errorData['hint'];
      } else {
        // If it's an array but no standard error fields, show the whole thing
        $errorMsg = json_encode($errorData);
      }
    } elseif (is_string($response) && strlen($response) > 0) {
      // If response is a plain string, use it (limit length)
      $errorMsg = substr($response, 0, 500);
    }
  }
  
  // Log detailed error for debugging
  error_log("=== CANDIDATE CREATE ERROR ===");
  error_log("HTTP Code: $httpCode");
  error_log("User ID: " . ($_SESSION['user']['id'] ?? 'NOT SET'));
  error_log("Request data: " . json_encode($data, JSON_PRETTY_PRINT));
  error_log("JSON being sent: " . $jsonData);
  error_log("Response: " . ($response !== false && strlen($response) > 0 ? $response : ($response === false ? 'file_get_contents returned false' : 'Empty response')));
  if (!empty($responseHeaders)) {
    error_log("Response headers: " . implode("\n", $responseHeaders));
    // Log proxy-status header specifically for PostgREST errors
    foreach ($responseHeaders as $header) {
      if (stripos($header, 'proxy-status') !== false) {
        error_log("PostgREST proxy-status: " . $header);
      }
    }
  }
  error_log("=============================");
  
  // Show more detailed error message - prioritize actual error from Supabase
  $displayError = $errorMsg;
  
  // If we got a specific error message from Supabase, use it
  if ($errorMsg !== 'Failed to create candidate. Please try again.' && strlen($errorMsg) > 10) {
    $displayError = $errorMsg;
  } else {
    // Fallback to generic messages based on HTTP code
    if ($httpCode === 0 && $response === false) {
      $displayError = 'Unable to connect to database. Please check your connection and try again.';
    } elseif ($httpCode === 409) {
      $displayError = 'A candidate with this email already exists.';
    } elseif ($httpCode === 422) {
      $displayError = 'Invalid data provided. Please check all fields are valid.';
    } elseif ($httpCode === 400) {
      $displayError = 'Bad request: Invalid data format or missing required fields. ' . ($response !== false ? substr($response, 0, 100) : '');
    } elseif ($httpCode === 500) {
      $displayError = 'Server error occurred. Please contact administrator.';
    } else {
      $displayError = 'Failed to create candidate. ' . ($response !== false ? substr($response, 0, 150) : 'Please try again.');
    }
  }
  
  // Include error code in message for debugging
  if ($httpCode > 0) {
    $displayError .= " (Error Code: $httpCode)";
  }
  
  header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode($displayError));
  exit;
}

// Verify response contains valid data
$result = json_decode($response, true);
if (empty($result)) {
  error_log("Invalid response from Supabase: " . $response);
  header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Failed to create candidate. Invalid response from server.'));
  exit;
}

// Supabase returns array with created object(s) when using Prefer: return=representation
$createdCandidate = is_array($result) && isset($result[0]) ? $result[0] : $result;
if (empty($createdCandidate) || !isset($createdCandidate['id'])) {
  error_log("Response missing candidate ID: " . $response);
  header('Location: ' . BASE_PATH . '/pages/candidate_create.php?error=' . urlencode('Failed to create candidate. Server did not return candidate ID.'));
  exit;
}

header("Location: " . BASE_PATH . "/pages/candidates.php?success=" . urlencode("Candidate created successfully"));
exit;
