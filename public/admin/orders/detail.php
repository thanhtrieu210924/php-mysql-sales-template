<?php

$pageTitle = 'Chi tiết đơn hàng';

require_once '/var/www/src/config/database.php';

$orderID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($orderID <= 0) {
    header('Location: /admin/orders/');
    exit;
}

/*
|--------------------------------------------------------------------------
| Quy tắc chuyển trạng thái đơn hàng
|--------------------------------------------------------------------------
*/

$allowedTransitions = [
    'Pending' => [
        'Confirmed',
        'Cancelled'
    ],

    'Confirmed' => [
        'Shipping',
        'Cancelled'
    ],

    'Shipping' => [
        'Completed',
        'Cancelled'
    ],

    'Completed' => [],

    'Cancelled' => []
];

$statusLabels = [
    'Pending' => 'Chờ xác nhận',
    'Confirmed' => 'Đã xác nhận',
    'Shipping' => 'Đang giao hàng',
    'Completed' => 'Hoàn thành',
    'Cancelled' => 'Đã hủy'
];

$statusClasses = [
    'Pending' => 'bg-warning text-dark',
    'Confirmed' => 'bg-primary',
    'Shipping' => 'bg-info text-dark',
    'Completed' => 'bg-success',
    'Cancelled' => 'bg-secondary'
];

$errorMessage = '';

/*
|--------------------------------------------------------------------------
| Xử lý cập nhật trạng thái
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $newStatus = trim($_POST['new_status'] ?? '');

    $transactionStarted = false;

    try {

        // Bắt đầu transaction.
        $conn->begin_transaction();
        $transactionStarted = true;

        /*
         * Khóa bản ghi đơn hàng để tránh hai yêu cầu
         * hủy cùng một đơn và hoàn kho hai lần.
         */
        $stmt = $conn->prepare("
            SELECT Status
            FROM orders
            WHERE OrderID = ?
            FOR UPDATE
        ");

        $stmt->bind_param('i', $orderID);
        $stmt->execute();

        $result = $stmt->get_result();
        $lockedOrder = $result->fetch_assoc();

        $stmt->close();

        if (!$lockedOrder) {
            throw new RuntimeException('Không tìm thấy đơn hàng.');
        }

        // Lấy trạng thái mới nhất bên trong transaction.
        $currentStatus = $lockedOrder['Status'];

        $allowedStatuses = $allowedTransitions[$currentStatus] ?? [];

        // Kiểm tra trạng thái được gửi lên có hợp lệ không.
        if (!in_array($newStatus, $allowedStatuses, true)) {
            throw new RuntimeException(
                'Không thể chuyển sang trạng thái được chọn.'
            );
        }

        /*
         * Nếu hủy đơn, hoàn lại số lượng sản phẩm
         * đã bị trừ khi khách hàng đặt hàng.
         */
        if ($newStatus === 'Cancelled') {

            $stmt = $conn->prepare("
                SELECT
                    ProductID,
                    Quantity
                FROM orderdetail
                WHERE OrderID = ?
            ");

            $stmt->bind_param('i', $orderID);
            $stmt->execute();

            $result = $stmt->get_result();

            $stockUpdateStmt = $conn->prepare("
                UPDATE products
                SET StockQuantity = StockQuantity + ?
                WHERE ProductID = ?
            ");

            while ($item = $result->fetch_assoc()) {

                $productID = (int) $item['ProductID'];
                $quantity = (int) $item['Quantity'];

                if ($productID <= 0 || $quantity <= 0) {
                    throw new RuntimeException(
                        'Dữ liệu sản phẩm trong đơn hàng không hợp lệ.'
                    );
                }

                $stockUpdateStmt->bind_param(
                    'ii',
                    $quantity,
                    $productID
                );

                $stockUpdateStmt->execute();

                if ($stockUpdateStmt->affected_rows !== 1) {
                    throw new RuntimeException(
                        'Không thể hoàn tồn kho cho sản phẩm.'
                    );
                }
            }

            $stockUpdateStmt->close();
            $stmt->close();
        }

        /*
         * Cập nhật trạng thái đơn hàng.
         * Nếu cập nhật thất bại, transaction sẽ rollback
         * cả phần hoàn tồn kho phía trên.
         */
        $stmt = $conn->prepare("
            UPDATE orders
            SET Status = ?
            WHERE OrderID = ?
        ");

        $stmt->bind_param(
            'si',
            $newStatus,
            $orderID
        );

        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException(
                'Không thể cập nhật trạng thái đơn hàng.'
            );
        }

        $stmt->close();

        // Hoàn tất transaction.
        $conn->commit();
        $transactionStarted = false;

        header(
            'Location: /admin/orders/detail.php?id='
            . $orderID
            . '&updated=1'
        );

        exit;

    } catch (Throwable $e) {

        if ($transactionStarted) {
            $conn->rollback();
        }

        error_log(
            'Order status update error: ' . $e->getMessage()
        );

        $errorMessage = 'Không thể cập nhật đơn hàng. Vui lòng thử lại.';
    }
}

/*
|--------------------------------------------------------------------------
| Lấy thông tin đơn hàng và khách hàng
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        o.OrderID,
        o.OrderDate,
        o.TotalAmount,
        o.Status,
        c.CustomerName,
        c.ContactName,
        c.Phone,
        c.Address,
        c.City,
        c.PostalCode,
        c.Country
    FROM orders AS o
    INNER JOIN customers AS c
        ON o.CustomerID = c.CustomerID
    WHERE o.OrderID = ?
");

$stmt->bind_param('i', $orderID);
$stmt->execute();

$result = $stmt->get_result();
$order = $result->fetch_assoc();

$stmt->close();

if (!$order) {
    $conn->close();

    header('Location: /admin/orders/');
    exit;
}

/*
|--------------------------------------------------------------------------
| Lấy danh sách sản phẩm trong đơn hàng
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        od.ProductID,
        p.ProductCode,
        p.ProductName,
        od.Quantity,
        od.UnitPrice
    FROM orderdetail AS od
    INNER JOIN products AS p
        ON od.ProductID = p.ProductID
    WHERE od.OrderID = ?
    ORDER BY od.OrderDetailID
");

$stmt->bind_param('i', $orderID);
$stmt->execute();

$result = $stmt->get_result();

$orderItems = [];

while ($item = $result->fetch_assoc()) {
    $orderItems[] = $item;
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| Chuẩn bị dữ liệu hiển thị
|--------------------------------------------------------------------------
*/

$currentStatus = $order['Status'];

$currentStatusLabel = $statusLabels[$currentStatus]
    ?? $currentStatus;

$currentStatusClass = $statusClasses[$currentStatus]
    ?? 'bg-secondary';

$nextStatuses = $allowedTransitions[$currentStatus] ?? [];

$orderDate = date(
    'd/m/Y H:i',
    strtotime($order['OrderDate'])
);

$totalAmount = 0;

foreach ($orderItems as $item) {
    $totalAmount +=
        (float) $item['UnitPrice']
        * (int) $item['Quantity'];
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h2>
            Chi tiết đơn hàng #<?= (int) $order['OrderID'] ?>
        </h2>

        <a
            href="/admin/orders/"
            class="btn btn-secondary"
        >
            Quay lại
        </a>

    </div>

    <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>

        <div class="alert alert-success">
            Đã cập nhật trạng thái đơn hàng thành công.
        </div>

    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($errorMessage) ?>
        </div>

    <?php endif; ?>

    <!-- Thông tin đơn hàng -->

    <div class="card mb-4">

        <div class="card-header bg-dark text-white">
            Thông tin đơn hàng
        </div>

        <div class="card-body">

            <div class="row mb-3">

                <div class="col-md-6">
                    <strong>Mã đơn hàng:</strong>
                    #<?= (int) $order['OrderID'] ?>
                </div>

                <div class="col-md-6">
                    <strong>Ngày đặt:</strong>
                    <?= htmlspecialchars($orderDate) ?>
                </div>

            </div>

            <div class="row mb-3">

                <div class="col-md-6">

                    <strong>Trạng thái:</strong>

                    <span class="badge <?= $currentStatusClass ?>">
                        <?= htmlspecialchars($currentStatusLabel) ?>
                    </span>

                </div>

                <div class="col-md-6">

                    <strong>Tổng tiền:</strong>

                    <span class="text-danger fw-bold">
                        <?= number_format(
                            (float) $order['TotalAmount'],
                            0,
                            ',',
                            '.'
                        ) ?> đ
                    </span>

                </div>

            </div>

        </div>

    </div>

    <!-- Thông tin khách hàng -->

    <div class="card mb-4">

        <div class="card-header bg-dark text-white">
            Thông tin khách hàng
        </div>

        <div class="card-body">

            <div class="row mb-3">

                <div class="col-md-6">
                    <strong>Họ tên:</strong>
                    <?= htmlspecialchars($order['CustomerName']) ?>
                </div>

                <div class="col-md-6">
                    <strong>Người liên hệ:</strong>
                    <?= htmlspecialchars($order['ContactName'] ?? '') ?>
                </div>

            </div>

            <div class="row mb-3">

                <div class="col-md-6">
                    <strong>Số điện thoại:</strong>
                    <?= htmlspecialchars($order['Phone'] ?? '') ?>
                </div>

                <div class="col-md-6">
                    <strong>Địa chỉ:</strong>
                    <?= htmlspecialchars($order['Address'] ?? '') ?>
                </div>

            </div>

            <div class="row mb-3">

                <div class="col-md-4">
                    <strong>Thành phố:</strong>
                    <?= htmlspecialchars($order['City'] ?? '') ?>
                </div>

                <div class="col-md-4">
                    <strong>Mã bưu điện:</strong>
                    <?= htmlspecialchars($order['PostalCode'] ?? '') ?>
                </div>

                <div class="col-md-4">
                    <strong>Quốc gia:</strong>
                    <?= htmlspecialchars($order['Country'] ?? '') ?>
                </div>

            </div>

        </div>

    </div>

    <!-- Cập nhật trạng thái -->

    <div class="card mb-4">

        <div class="card-header bg-dark text-white">
            Cập nhật trạng thái đơn hàng
        </div>

        <div class="card-body">

            <?php if (count($nextStatuses) > 0): ?>

                <form
                    method="post"
                    action="/admin/orders/detail.php?id=<?= (int) $orderID ?>"
                >

                    <div class="row align-items-end">

                        <div class="col-md-6">

                            <label
                                for="new_status"
                                class="form-label"
                            >
                                Chọn trạng thái mới
                            </label>

                            <select
                                name="new_status"
                                id="new_status"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Chọn trạng thái --
                                </option>

                                <?php foreach ($nextStatuses as $status): ?>

                                    <option value="<?= htmlspecialchars($status) ?>">
                                        <?= htmlspecialchars(
                                            $statusLabels[$status] ?? $status
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="col-md-6 mt-3 mt-md-0">

                            <button
                                type="submit"
                                name="update_status"
                                value="1"
                                class="btn btn-primary"
                                onclick="return confirm('Bạn có chắc muốn cập nhật trạng thái đơn hàng?');"
                            >
                                Cập nhật trạng thái
                            </button>

                        </div>

                    </div>

                </form>

                <div class="form-text mt-2">
                    Chỉ những trạng thái hợp lệ tiếp theo mới được hiển thị.
                </div>

            <?php else: ?>

                <div class="alert alert-secondary mb-0">
                    Đơn hàng đã ở trạng thái kết thúc.
                    Không thể thay đổi trạng thái này.
                </div>

            <?php endif; ?>

        </div>

    </div>

    <!-- Danh sách sản phẩm -->

    <div class="card mb-4">

        <div class="card-header bg-dark text-white">
            Sản phẩm trong đơn hàng
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped align-middle">

                    <thead class="table-dark">

                        <tr>
                            <th>Mã SP</th>
                            <th>Tên sản phẩm</th>
                            <th class="text-end">Đơn giá</th>
                            <th class="text-end">Số lượng</th>
                            <th class="text-end">Thành tiền</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($orderItems) > 0): ?>

                        <?php foreach ($orderItems as $item): ?>

                            <?php
                            $unitPrice = (float) $item['UnitPrice'];
                            $quantity = (int) $item['Quantity'];
                            $subtotal = $unitPrice * $quantity;
                            ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($item['ProductCode']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($item['ProductName']) ?>
                                </td>

                                <td class="text-end">
                                    <?= number_format(
                                        $unitPrice,
                                        0,
                                        ',',
                                        '.'
                                    ) ?> đ
                                </td>

                                <td class="text-end">
                                    <?= $quantity ?>
                                </td>

                                <td class="text-end">
                                    <?= number_format(
                                        $subtotal,
                                        0,
                                        ',',
                                        '.'
                                    ) ?> đ
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                Đơn hàng chưa có sản phẩm.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                    <tfoot>

                        <tr>
                            <th colspan="4" class="text-end">
                                Tổng tiền sản phẩm:
                            </th>

                            <th class="text-end text-danger">
                                <?= number_format(
                                    $totalAmount,
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ
                            </th>
                        </tr>

                        <tr>
                            <th colspan="4" class="text-end">
                                Tổng tiền đơn hàng:
                            </th>

                            <th class="text-end text-danger">
                                <?= number_format(
                                    (float) $order['TotalAmount'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ
                            </th>
                        </tr>

                    </tfoot>

                </table>

            </div>

        </div>

    </div>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();

?>
