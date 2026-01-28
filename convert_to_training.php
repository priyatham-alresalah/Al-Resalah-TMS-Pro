<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/convert_to_training.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
