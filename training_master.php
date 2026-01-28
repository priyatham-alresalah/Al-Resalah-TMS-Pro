<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/training_master.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
