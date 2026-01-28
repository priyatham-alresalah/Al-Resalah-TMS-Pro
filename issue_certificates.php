<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/issue_certificates.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
