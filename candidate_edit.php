<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$redirect = 'pages/candidate_edit.php' . ($query ? '?' . $query : '');
header('Location: ' . $redirect);
exit;
?>
