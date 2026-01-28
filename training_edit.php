<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/training_edit.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
