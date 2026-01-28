<?php
// Preserve query parameters
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/user_edit.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
