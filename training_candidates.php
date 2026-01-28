<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/training_candidates.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
