<?php

require_once '/var/www/src/config/database.php';

// Chỉ cho phép xóa bằng phương thức POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/shippers/');
    exit;
}

// Lấy mã người giao hàng
$shipperID = filter_input(
    INPUT_POST,
    'shipper_id',
    FILTER_VALIDATE_INT
);

// Kiểm tra mã hợp lệ
if (!$shipperID || $shipperID <= 0) {
    header('Location: /admin/shippers/?error=invalid_id');
    exit;
}

// Kiểm tra người giao hàng có tồn tại không
$sql = "SELECT ShipperID
        FROM shippers
        WHERE ShipperID = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    header('Location: /admin/shippers/?error=delete_failed');
    exit;
}

$stmt->bind_param('i', $shipperID);
$stmt->execute();

$result = $stmt->get_result();
$shipper = $result->fetch_assoc();

$stmt->close();

// Không tìm thấy người giao hàng
if (!$shipper) {
    header('Location: /admin/shippers/?error=not_found');
    exit;
}

// Thực hiện xóa người giao hàng
$sql = "DELETE FROM shippers
        WHERE ShipperID = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    header('Location: /admin/shippers/?error=delete_failed');
    exit;
}

$stmt->bind_param('i', $shipperID);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $stmt->close();

    header('Location: /admin/shippers/?success=3');
    exit;
}

// Xử lý nếu xóa thất bại
$stmt->close();

header('Location: /admin/shippers/?error=delete_failed');
exit;