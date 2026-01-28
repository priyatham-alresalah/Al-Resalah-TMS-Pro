<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/certificate_edit.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
