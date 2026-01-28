<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/certificate_view.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
