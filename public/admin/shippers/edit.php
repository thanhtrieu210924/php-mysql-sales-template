<?php
$pageTitle = 'Sửa người giao hàng';

require_once '/var/www/src/config/database.php';

$errors = [];
$shipperID = 0;
$shipperName = '';
$phone = '';

// Lấy mã người giao hàng từ URL hoặc dữ liệu POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipperID = filter_input(
        INPUT_POST,
        'shipper_id',
        FILTER_VALIDATE_INT
    );
} else {
    $shipperID = filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );
}

// Kiểm tra mã người giao hàng
if (!$shipperID || $shipperID <= 0) {
    header('Location: /admin/shippers/');
    exit;
}

// Lấy thông tin người giao hàng từ database
$sql = "SELECT ShipperID, ShipperName, Phone
        FROM shippers
        WHERE ShipperID = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    exit('Không thể chuẩn bị truy vấn cơ sở dữ liệu.');
}

$stmt->bind_param('i', $shipperID);
$stmt->execute();

$result = $stmt->get_result();
$shipper = $result->fetch_assoc();

$stmt->close();

// Không tìm thấy người giao hàng
if (!$shipper) {
    header('Location: /admin/shippers/');
    exit;
}

// Hiển thị dữ liệu hiện tại trên biểu mẫu
$shipperName = $shipper['ShipperName'];
$phone = $shipper['Phone'] ?? '';

// Xử lý cập nhật khi gửi biểu mẫu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipperName = trim($_POST['ShipperName'] ?? '');
    $phone = trim($_POST['Phone'] ?? '');

    // Kiểm tra dữ liệu
    if ($shipperName === '') {
        $errors[] = 'Vui lòng nhập tên người giao hàng.';
    }

    if (strlen($shipperName) > 100) {
        $errors[] = 'Tên người giao hàng không được vượt quá 100 ký tự.';
    }

    if (strlen($phone) > 20) {
        $errors[] = 'Số điện thoại không được vượt quá 20 ký tự.';
    }

    // Nếu không có lỗi thì cập nhật database
    if (empty($errors)) {
        $sql = "UPDATE shippers
                SET ShipperName = ?, Phone = ?
                WHERE ShipperID = ?";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errors[] = 'Không thể chuẩn bị truy vấn cập nhật.';
        } else {
            $stmt->bind_param(
                'ssi',
                $shipperName,
                $phone,
                $shipperID
            );

            if ($stmt->execute()) {
                $stmt->close();

                header('Location: /admin/shippers/?success=2');
                exit;
            }

            $stmt->close();
            $errors[] = 'Không thể cập nhật người giao hàng. Vui lòng thử lại.';
        }
    }
}

// Chỉ nạp giao diện sau khi xử lý xong các lệnh chuyển hướng
require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container py-5">
    <h1 class="mb-4">Sửa người giao hàng</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form
        action="/admin/shippers/edit.php?id=<?= (int) $shipperID ?>"
        method="post"
    >
        <input
            type="hidden"
            name="shipper_id"
            value="<?= (int) $shipperID ?>"
        >

        <div class="mb-3">
            <label for="ShipperName" class="form-label">
                Tên người giao hàng
                <span class="text-danger">*</span>
            </label>

            <input
                type="text"
                class="form-control"
                id="ShipperName"
                name="ShipperName"
                maxlength="100"
                value="<?= htmlspecialchars(
                    $shipperName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="Phone" class="form-label">
                Số điện thoại
            </label>

            <input
                type="text"
                class="form-control"
                id="Phone"
                name="Phone"
                maxlength="20"
                value="<?= htmlspecialchars(
                    $phone,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
        </div>

        <button type="submit" class="btn btn-primary">
            Cập nhật
        </button>

        <a
            href="/admin/shippers/"
            class="btn btn-secondary"
        >
            Quay lại
        </a>
    </form>
</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
?>
