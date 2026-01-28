<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/certificate_create.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
