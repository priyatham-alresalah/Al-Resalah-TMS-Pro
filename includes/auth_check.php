<?php
require __DIR__ . '/config.php';
if (!isset($_SESSION['user'])) {
  header("Location: " . BASE_PATH . "/");
  exit;
}
