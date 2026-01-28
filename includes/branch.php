<?php
/**
 * Branch Awareness Helper
 * Handles branch-based data filtering and association
 */

require __DIR__ . '/config.php';
require __DIR__ . '/auth_check.php';

/**
 * Get user's branch ID (if applicable)
 * @return string|null
 */
function getUserBranchId() {
  // If user profile has branch_id, use it
  // Otherwise, return null (no branch restriction)
  return $_SESSION['user']['branch_id'] ?? null;
}

/**
 * Filter data by branch if user is branch-restricted
 * @param string $baseUrl Supabase URL
 * @param string $table Table name
 * @return string Modified URL with branch filter
 */
function applyBranchFilter($baseUrl, $table) {
  $branchId = getUserBranchId();
  
  if ($branchId === null) {
    return $baseUrl; // No branch restriction
  }
  
  // Add branch filter to URL
  $separator = strpos($baseUrl, '?') !== false ? '&' : '?';
  return $baseUrl . $separator . "branch_id=eq.$branchId";
}

/**
 * Associate record with branch
 * @param array $data Data array
 * @return array Data with branch_id added
 */
function addBranchToData($data) {
  $branchId = getUserBranchId();
  
  if ($branchId !== null) {
    $data['branch_id'] = $branchId;
  }
  
  return $data;
}
