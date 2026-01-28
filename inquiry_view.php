<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/inquiry_view.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
