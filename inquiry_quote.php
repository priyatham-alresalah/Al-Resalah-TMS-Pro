<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/inquiry_quote.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
