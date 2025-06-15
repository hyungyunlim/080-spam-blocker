<?php
require_once __DIR__ . '/auth.php';

// 이미 로그인되어 있고 어드민이면 admin.php로 리다이렉션
if (is_logged_in() && is_admin()) {
    header('Location: admin.php');
    exit;
}

// 이미 로그인되어 있지만 어드민이 아니면 메인으로
if (is_logged_in() && !is_admin()) {
    header('Location: index.php');
    exit;
}

// 로그인되어 있지 않으면 admin_login.php 내용을 직접 포함
require_once __DIR__ . '/admin_login.php';
?>