<?php

require_once __DIR__ . '/../src/config/session.php';

// Xóa thông tin đăng nhập
unset($_SESSION['customer_id']);
unset($_SESSION['customer_name']);

// Tạo session ID mới
session_regenerate_id(true);

// Quay về trang chủ
header('Location: /');
exit;
