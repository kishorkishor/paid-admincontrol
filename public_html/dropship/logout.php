<?php
require_once 'config.php';

// Destroy session
session_destroy();

// Redirect to login
header('Location: auth.php');
exit;
?>
