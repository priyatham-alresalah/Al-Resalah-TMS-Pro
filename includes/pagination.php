<?php
/**
 * Pagination Helper
 * Provides pagination utilities for list pages
 */

/**
 * Get pagination parameters from request
 * @return array ['page' => int, 'limit' => int, 'offset' => int]
 */
function getPaginationParams() {
  $page = max(1, intval($_GET['page'] ?? 1));
  $limit = max(1, min(200, intval($_GET['limit'] ?? 50))); // Default 50, max 200
  $offset = ($page - 1) * $limit;
  
  return [
    'page' => $page,
    'limit' => $limit,
    'offset' => $offset
  ];
}

/**
 * Build pagination URL with query parameters
 * @param int $page Page number
 * @param array $preserveParams Parameters to preserve in URL
 * @return string URL with pagination params
 */
function buildPaginationUrl($page, $preserveParams = []) {
  $params = array_merge($_GET, ['page' => $page], $preserveParams);
  return '?' . http_build_query($params);
}

/**
 * Get total count from Supabase response headers
 * @param array $headers Response headers
 * @return int Total count
 */
function getTotalCountFromHeaders($headers) {
  // Supabase returns count in Content-Range header: "0-49/1000"
  foreach ($headers as $header) {
    if (preg_match('/Content-Range:\s*\d+-\d+\/(\d+)/i', $header, $matches)) {
      return intval($matches[1]);
    }
  }
  return 0;
}

/**
 * Render pagination controls
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param array $preserveParams Parameters to preserve in URL
 */
function renderPagination($currentPage, $totalPages, $preserveParams = []) {
  if ($totalPages <= 1) {
    return; // No pagination needed
  }
  
  $prevPage = max(1, $currentPage - 1);
  $nextPage = min($totalPages, $currentPage + 1);
  
  echo '<div class="pagination" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 6px;">';
  echo '<div style="color: #6b7280;">';
  echo 'Page ' . $currentPage . ' of ' . $totalPages;
  echo '</div>';
  echo '<div style="display: flex; gap: 8px;">';
  
  // Previous button
  if ($currentPage > 1) {
    echo '<a href="' . htmlspecialchars(buildPaginationUrl($prevPage, $preserveParams)) . '" class="btn" style="padding: 8px 16px;">&laquo; Previous</a>';
  } else {
    echo '<span class="btn" style="padding: 8px 16px; opacity: 0.5; cursor: not-allowed;">&laquo; Previous</span>';
  }
  
  // Page numbers (show up to 5 pages around current)
  $startPage = max(1, $currentPage - 2);
  $endPage = min($totalPages, $currentPage + 2);
  
  if ($startPage > 1) {
    echo '<a href="' . htmlspecialchars(buildPaginationUrl(1, $preserveParams)) . '" class="btn" style="padding: 8px 12px;">1</a>';
    if ($startPage > 2) {
      echo '<span style="padding: 8px 4px;">...</span>';
    }
  }
  
  for ($i = $startPage; $i <= $endPage; $i++) {
    if ($i == $currentPage) {
      echo '<span class="btn" style="padding: 8px 12px; background: #3b82f6; color: white;">' . $i . '</span>';
    } else {
      echo '<a href="' . htmlspecialchars(buildPaginationUrl($i, $preserveParams)) . '" class="btn" style="padding: 8px 12px;">' . $i . '</a>';
    }
  }
  
  if ($endPage < $totalPages) {
    if ($endPage < $totalPages - 1) {
      echo '<span style="padding: 8px 4px;">...</span>';
    }
    echo '<a href="' . htmlspecialchars(buildPaginationUrl($totalPages, $preserveParams)) . '" class="btn" style="padding: 8px 12px;">' . $totalPages . '</a>';
  }
  
  // Next button
  if ($currentPage < $totalPages) {
    echo '<a href="' . htmlspecialchars(buildPaginationUrl($nextPage, $preserveParams)) . '" class="btn" style="padding: 8px 16px;">Next &raquo;</a>';
  } else {
    echo '<span class="btn" style="padding: 8px 16px; opacity: 0.5; cursor: not-allowed;">Next &raquo;</span>';
  }
  
  echo '</div>';
  echo '</div>';
}
