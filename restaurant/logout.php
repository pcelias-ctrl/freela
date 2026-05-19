<?php
require_once __DIR__ . '/../includes/auth.php';
session_destroy();
header('Location: ' . app_url('restaurant/login.php'));
exit;
