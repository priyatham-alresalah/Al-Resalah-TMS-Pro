<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/verify.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
