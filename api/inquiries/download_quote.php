<?php
require '../../includes/config.php';
require '../../includes/auth_check.php';

$file = $_GET['file'] ?? null;
if (!$file) die("File not found");

$path = __DIR__ . "/../../uploads/quotes/" . basename($file);

if (!file_exists($path)) die("File missing");

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
readfile($path);
exit;
