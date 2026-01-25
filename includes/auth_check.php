<?php
if (!isset($_SESSION['user'])) {
  header("Location: /training-management-system/");
  exit;
}
