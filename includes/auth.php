<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function app_url($path = '') {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = dirname($script);
    if (preg_match('#/(admin|restaurant|public)$#', $base)) {
        $base = dirname($base);
    }
    $base = ($base === '/' || $base === '.') ? '' : rtrim($base, '/');
    return $base . '/' . ltrim($path, '/');
}

function require_admin() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . app_url('admin/login.php'));
        exit;
    }
}

function require_restaurant() {
    if (empty($_SESSION['restaurant_id'])) {
        header('Location: ' . app_url('restaurant/login.php'));
        exit;
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
