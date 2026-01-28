<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/inquiry_edit.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
