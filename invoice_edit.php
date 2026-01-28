<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/invoice_edit.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
