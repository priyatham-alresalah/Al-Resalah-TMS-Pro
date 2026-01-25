<?php
require 'includes/config.php';

session_destroy();

header("Location: /training-management-system/");
exit;
