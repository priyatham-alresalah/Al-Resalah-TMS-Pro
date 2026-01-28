<?php
/**
 * File Upload Validation Helper
 * Ensures secure file uploads with size limits and MIME type validation
 */

require __DIR__ . '/config.php';
require __DIR__ . '/app_log.php';

define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_MIME_TYPES', [
  'application/pdf' => ['pdf'],
  'image/jpeg' => ['jpg', 'jpeg'],
  'image/png' => ['png'],
  'image/gif' => ['gif'],
  'application/msword' => ['doc'],
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
  'application/vnd.ms-excel' => ['xls'],
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx']
]);

/**
 * Validate file upload
 * @param array $file $_FILES array element
 * @param array|null $allowedTypes Allowed MIME types (null = use default)
 * @param int|null $maxSize Maximum file size in bytes (null = use default)
 * @return array ['valid' => bool, 'error' => string|null, 'safe_name' => string|null]
 */
function validateFileUpload($file, $allowedTypes = null, $maxSize = null) {
  if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    return ['valid' => false, 'error' => 'Invalid file upload', 'safe_name' => null];
  }
  
  // Check for upload errors
  if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
      UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
      UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
      UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
      UPLOAD_ERR_NO_FILE => 'No file was uploaded',
      UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
      UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
      UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    $error = $errorMessages[$file['error']] ?? 'Unknown upload error';
    logWarning('file_upload_error', ['error_code' => $file['error'], 'error' => $error]);
    return ['valid' => false, 'error' => $error, 'safe_name' => null];
  }
  
  // Check file size
  $maxSize = $maxSize ?? MAX_UPLOAD_SIZE;
  if ($file['size'] > $maxSize) {
    logWarning('file_upload_size_exceeded', [
      'filename' => $file['name'],
      'size' => $file['size'],
      'max_size' => $maxSize
    ]);
    return ['valid' => false, 'error' => 'File size exceeds maximum allowed size (' . round($maxSize / 1024 / 1024, 2) . 'MB)', 'safe_name' => null];
  }
  
  // Validate MIME type
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);
  
  $allowedTypes = $allowedTypes ?? ALLOWED_MIME_TYPES;
  $allowed = false;
  $allowedExtensions = [];
  
  foreach ($allowedTypes as $mime => $extensions) {
    if ($mime === $mimeType) {
      $allowed = true;
      $allowedExtensions = $extensions;
      break;
    }
  }
  
  if (!$allowed) {
    logSecurity('file_upload_invalid_mime', [
      'filename' => $file['name'],
      'mime_type' => $mimeType,
      'allowed_types' => array_keys($allowedTypes)
    ]);
    return ['valid' => false, 'error' => 'File type not allowed. Allowed types: ' . implode(', ', array_keys($allowedTypes)), 'safe_name' => null];
  }
  
  // Validate file extension matches MIME type
  $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if (!in_array($extension, $allowedExtensions)) {
    logSecurity('file_upload_extension_mismatch', [
      'filename' => $file['name'],
      'extension' => $extension,
      'mime_type' => $mimeType,
      'allowed_extensions' => $allowedExtensions
    ]);
    return ['valid' => false, 'error' => 'File extension does not match file type', 'safe_name' => null];
  }
  
  // Generate safe filename
  $safeName = generateSafeFilename($file['name'], $extension);
  
  return ['valid' => true, 'error' => null, 'safe_name' => $safeName, 'mime_type' => $mimeType];
}

/**
 * Generate safe filename
 * @param string $originalName Original filename
 * @param string $extension File extension
 * @return string Safe filename
 */
function generateSafeFilename($originalName, $extension) {
  $baseName = pathinfo($originalName, PATHINFO_FILENAME);
  $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
  $baseName = substr($baseName, 0, 100); // Limit length
  return $baseName . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
}

/**
 * Move uploaded file to destination
 * @param array $file $_FILES array element
 * @param string $destination Destination path
 * @param string|null $safeName Safe filename (if null, uses original)
 * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
 */
function moveUploadedFileSafe($file, $destination, $safeName = null) {
  if (!is_dir($destination)) {
    @mkdir($destination, 0755, true);
  }
  
  if (!is_writable($destination)) {
    logError('file_upload_destination_not_writable', ['destination' => $destination]);
    return ['success' => false, 'path' => null, 'error' => 'Destination directory is not writable'];
  }
  
  $safeName = $safeName ?? basename($file['name']);
  $targetPath = rtrim($destination, '/') . '/' . $safeName;
  
  if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    return ['success' => true, 'path' => $targetPath, 'error' => null];
  }
  
  logError('file_upload_move_failed', [
    'source' => $file['tmp_name'],
    'destination' => $targetPath
  ]);
  
  return ['success' => false, 'path' => null, 'error' => 'Failed to move uploaded file'];
}

/**
 * Clean up temporary files older than specified hours
 * @param string $directory Directory to clean
 * @param int $maxAgeHours Maximum age in hours
 * @return int Number of files deleted
 */
function cleanupTempFiles($directory, $maxAgeHours = 24) {
  if (!is_dir($directory)) {
    return 0;
  }
  
  $count = 0;
  $maxAge = time() - ($maxAgeHours * 3600);
  
  $files = glob($directory . '/*');
  foreach ($files as $file) {
    if (is_file($file) && filemtime($file) < $maxAge) {
      if (@unlink($file)) {
        $count++;
      }
    }
  }
  
  if ($count > 0) {
    logInfo('temp_files_cleaned', ['directory' => $directory, 'count' => $count]);
  }
  
  return $count;
}
