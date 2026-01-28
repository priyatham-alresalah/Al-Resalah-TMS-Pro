<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/client_edit.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
