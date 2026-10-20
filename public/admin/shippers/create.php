<?php
$pageTitle = 'Thêm người giao hàng';

require_once '/var/www/src/config/database.php';

$errors = [];
$shipperName = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipperName = trim($_POST['ShipperName'] ?? '');
    $phone = trim($_POST['Phone'] ?? '');

    if ($shipperName === '') {
        $errors[] = 'Vui lòng nhập tên người giao hàng.';
    }

    if (strlen($shipperName) > 100) {
        $errors[] = 'Tên người giao hàng không được vượt quá 100 ký tự.';
    }

    if (strlen($phone) > 20) {
        $errors[] = 'Số điện thoại không được vượt quá 20 ký tự.';
    }

    if (empty($errors)) {
        $sql = "INSERT INTO shippers (ShipperName, Phone)
                VALUES (?, ?)";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param('ss', $shipperName, $phone);

            if ($stmt->execute()) {
                $stmt->close();

                header('Location: /admin/shippers/?success=1');
                exit;
            }

            $errors[] = 'Không thể thêm người giao hàng. Vui lòng thử lại.';
            $stmt->close();
        } else {
            $errors[] = 'Không thể chuẩn bị truy vấn cơ sở dữ liệu.';
        }
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container py-5">

    <h1 class="mb-4">Thêm người giao hàng</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="/admin/shippers/create.php" method="post">

        <div class="mb-3">
            <label for="ShipperName" class="form-label">
                Tên người giao hàng <span class="text-danger">*</span>
            </label>

            <input
                type="text"
                class="form-control"
                id="ShipperName"
                name="ShipperName"
                maxlength="100"
                value="<?= htmlspecialchars($shipperName, ENT_QUOTES, 'UTF-8') ?>"
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
                value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
            >
        </div>

        <button type="submit" class="btn btn-primary">
            Lưu
        </button>

        <a href="/admin/shippers/" class="btn btn-secondary">
            Quay lại
        </a>

    </form>

</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
?>
