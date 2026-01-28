<?php
/**
 * Workflow Enforcement Helper
 * Ensures business rules are followed in the training flow
 */

require __DIR__ . '/config.php';

/**
 * Check if inquiry can be converted to quotation
 * @param string $inquiryId
 * @return array ['allowed' => bool, 'reason' => string]
 */
function canCreateQuotation($inquiryId) {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);

  $inquiry = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId&select=id,status",
      false,
      $ctx
    ),
    true
  );

  if (empty($inquiry)) {
    return ['allowed' => false, 'reason' => 'Inquiry not found'];
  }

  $inquiry = $inquiry[0];
  
  // Can create quotation only from 'new' status
  if ($inquiry['status'] !== 'new') {
    return ['allowed' => false, 'reason' => 'Quotation can only be created for new inquiries'];
  }

  // Check if quotation already exists
  $existingQuotation = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/quotations?inquiry_id=eq.$inquiryId&select=id",
      false,
      $ctx
    ),
    true
  );

  if (!empty($existingQuotation)) {
    return ['allowed' => false, 'reason' => 'Quotation already exists for this inquiry'];
  }

  return ['allowed' => true, 'reason' => ''];
}

/**
 * Check if quotation can be approved
 * @param string $quotationId
 * @return array ['allowed' => bool, 'reason' => string]
 */
function canApproveQuotation($quotationId) {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);

  $quotation = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/quotations?id=eq.$quotationId&select=id,status",
      false,
      $ctx
    ),
    true
  );

  if (empty($quotation)) {
    return ['allowed' => false, 'reason' => 'Quotation not found'];
  }

  $quotation = $quotation[0];
  
  // Can approve only if status is 'pending_approval'
  if ($quotation['status'] !== 'pending_approval') {
    return ['allowed' => false, 'reason' => 'Only quotations pending approval can be approved'];
  }

  return ['allowed' => true, 'reason' => ''];
}

/**
 * Check if training can be created
 * @param string $inquiryId
 * @return array ['allowed' => bool, 'reason' => string]
 */
function canCreateTraining($inquiryId) {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);

  // Check if quotation exists and is accepted
  $quotation = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/quotations?inquiry_id=eq.$inquiryId&select=id,status",
      false,
      $ctx
    ),
    true
  );

  if (empty($quotation)) {
    return ['allowed' => false, 'reason' => 'Quotation must be created and accepted before training'];
  }

  $quotation = $quotation[0];
  
  if ($quotation['status'] !== 'accepted') {
    return ['allowed' => false, 'reason' => 'Quotation must be accepted before creating training'];
  }

  // Check if LPO is verified
  $lpo = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/client_orders?quotation_id=eq.{$quotation['id']}&select=id,status",
      false,
      $ctx
    ),
    true
  );

  if (empty($lpo)) {
    return ['allowed' => false, 'reason' => 'LPO must be uploaded and verified before training'];
  }

  $lpo = $lpo[0];
  
  if ($lpo['status'] !== 'verified') {
    return ['allowed' => false, 'reason' => 'LPO must be verified before creating training'];
  }

  return ['allowed' => true, 'reason' => ''];
}

/**
 * Check if certificate can be issued for training
 * @param string $trainingId
 * @return array ['allowed' => bool, 'reason' => string]
 */
function canIssueCertificate($trainingId) {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);

  // Check training status
  $training = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/trainings?id=eq.$trainingId&select=id,status,course_name",
      false,
      $ctx
    ),
    true
  );

  if (empty($training)) {
    return ['allowed' => false, 'reason' => 'Training not found'];
  }

  $training = $training[0];
  
  if ($training['status'] !== 'completed') {
    return ['allowed' => false, 'reason' => 'Training must be completed before issuing certificate'];
  }

  // Check if required documents are uploaded
  $courseId = null;
  if (!empty($training['course_name'])) {
    $course = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/training_master?course_name=eq.{$training['course_name']}&select=id",
        false,
        $ctx
      ),
      true
    );
    if (!empty($course)) {
      $courseId = $course[0]['id'];
    }
  }

  if ($courseId) {
    // Get required documents
    $requiredDocs = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/document_requirements?training_master_id=eq.$courseId&is_mandatory=eq.true&select=document_type",
        false,
        $ctx
      ),
      true
    );

    // Get uploaded documents
    $uploadedDocs = json_decode(
      @file_get_contents(
        SUPABASE_URL . "/rest/v1/training_documents?training_id=eq.$trainingId&select=document_type",
        false,
        $ctx
      ),
      true
    );

    $uploadedTypes = array_column($uploadedDocs, 'document_type');
    
    foreach ($requiredDocs as $reqDoc) {
      if (!in_array($reqDoc['document_type'], $uploadedTypes)) {
        return ['allowed' => false, 'reason' => "Required document '{$reqDoc['document_type']}' is missing"];
      }
    }
  }

  return ['allowed' => true, 'reason' => ''];
}

/**
 * Check if invoice can be created for training
 * @param string $trainingId
 * @return array ['allowed' => bool, 'reason' => string]
 */
function canCreateInvoice($trainingId) {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);

  // Check if training exists and is completed
  $training = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/trainings?id=eq.$trainingId&select=id,status",
      false,
      $ctx
    ),
    true
  );

  if (empty($training)) {
    return ['allowed' => false, 'reason' => 'Training not found'];
  }

  // Check if certificate exists and is active
  $certificate = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/certificates?training_id=eq.$trainingId&status=eq.active&select=id",
      false,
      $ctx
    ),
    true
  );

  if (empty($certificate)) {
    return ['allowed' => false, 'reason' => 'Active certificate must be issued before creating invoice'];
  }

  // Check if invoice already exists
  $existingInvoice = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/invoices?training_id=eq.$trainingId&select=id",
      false,
      $ctx
    ),
    true
  );

  if (!empty($existingInvoice)) {
    return ['allowed' => false, 'reason' => 'Invoice already exists for this training'];
  }

  return ['allowed' => true, 'reason' => ''];
}

/**
 * Check if training can be completed (attendance verified)
 * @param string $trainingId
 * @return array ['allowed' => bool, 'reason' => string]
 */
function canCompleteTraining($trainingId) {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' =>
        "apikey: " . SUPABASE_SERVICE . "\r\n" .
        "Authorization: Bearer " . SUPABASE_SERVICE
    ]
  ]);

  // Check if attendance checkpoint is completed
  $attendanceCheckpoint = json_decode(
    @file_get_contents(
      SUPABASE_URL . "/rest/v1/training_checkpoints?training_id=eq.$trainingId&checkpoint=eq.attendance_verified&select=completed",
      false,
      $ctx
    ),
    true
  );

  if (empty($attendanceCheckpoint) || !($attendanceCheckpoint[0]['completed'] ?? false)) {
    return ['allowed' => false, 'reason' => 'Attendance must be verified before completing training'];
  }

  return ['allowed' => true, 'reason' => ''];
}
