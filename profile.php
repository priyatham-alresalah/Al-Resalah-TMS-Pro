<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/profile.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
