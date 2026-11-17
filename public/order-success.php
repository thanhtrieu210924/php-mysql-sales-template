<?php

require_once '/var/www/src/config/session.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once '/var/www/src/config/database.php';

function order_success_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// ============================================
// LẤY MÃ ĐƠN HÀNG
// ============================================

$orderID = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$order = null;
$orderItems = [];

// ============================================
// LẤY THÔNG TIN ĐƠN HÀNG
// ============================================

if ($orderID !== false && $orderID !== null && $orderID > 0) {
    $sqlOrder = "
        SELECT
            o.OrderID,
            o.OrderDate,
            o.TotalAmount,
            o.Status,
            c.CustomerName,
            c.Phone,
            c.Address
        FROM orders o
        INNER JOIN customers c
            ON o.CustomerID = c.CustomerID
        WHERE o.OrderID = ?
    ";

    $stmtOrder = $conn->prepare($sqlOrder);

    $stmtOrder->bind_param('i', $orderID);
    $stmtOrder->execute();

    $resultOrder = $stmtOrder->get_result();

    $order = $resultOrder->fetch_assoc();

    $resultOrder->free();
    $stmtOrder->close();

    // ============================================
    // LẤY CHI TIẾT ĐƠN HÀNG
    // ============================================

    if ($order) {
        $sqlDetails = "
            SELECT
                od.OrderDetailID,
                od.Quantity,
                od.UnitPrice,
                p.ProductCode,
                p.ProductName
            FROM orderdetail od
            INNER JOIN products p
                ON od.ProductID = p.ProductID
            WHERE od.OrderID = ?
            ORDER BY od.OrderDetailID ASC
        ";

        $stmtDetails = $conn->prepare($sqlDetails);

        $stmtDetails->bind_param('i', $orderID);
        $stmtDetails->execute();

        $resultDetails = $stmtDetails->get_result();

        while ($item = $resultDetails->fetch_assoc()) {
            $orderItems[] = $item;
        }

        $resultDetails->free();
        $stmtDetails->close();
    }
}

// ============================================
// XÁC ĐỊNH NHÃN TRẠNG THÁI
// ============================================

$statusLabels = [
    'Pending' => 'Chờ xác nhận',
    'Confirmed' => 'Đã xác nhận',
    'Shipping' => 'Đang giao hàng',
    'Completed' => 'Hoàn thành',
    'Cancelled' => 'Đã hủy'
];

$statusLabel = $statusLabels[$order['Status'] ?? '']
    ?? ($order['Status'] ?? 'Không xác định');

$statusClass = 'order-status-default';

switch ($order['Status'] ?? '') {
    case 'Pending':
        $statusClass = 'order-status-pending';
        break;

    case 'Confirmed':
        $statusClass = 'order-status-confirmed';
        break;

    case 'Shipping':
        $statusClass = 'order-status-shipping';
        break;

    case 'Completed':
        $statusClass = 'order-status-completed';
        break;

    case 'Cancelled':
        $statusClass = 'order-status-cancelled';
        break;
}

// ============================================
// NẠP GIAO DIỆN
// ============================================

$pageTitle = 'Đặt hàng thành công';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="order-success-page">
    <div class="order-success-container">

        <?php if (!$order): ?>

            <section class="order-not-found">
                <div class="order-not-found-icon">
                    <span aria-hidden="true">!</span>
                </div>

                <h1>Không tìm thấy đơn hàng</h1>

                <p>
                    Mã đơn hàng không hợp lệ hoặc đơn hàng không tồn tại.
                    Vui lòng kiểm tra lại thông tin.
                </p>

                <a href="/products.php" class="order-primary-button">
                    Tiếp tục mua hàng
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </section>

        <?php else: ?>

            <section class="order-success-banner">

                <div class="order-success-icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        aria-hidden="true"
                    >
                        <path
                            d="M5 12.5L10 17L19 7"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>

                <span class="order-success-kicker">
                    ORDER COMPLETED
                </span>

                <h1>Đặt hàng thành công!</h1>

                <p>
                    Cảm ơn bạn đã mua sắm. Đơn hàng của bạn đã được
                    hệ thống ghi nhận.
                </p>

                <div class="order-code-box">
                    <span>MÃ ĐƠN HÀNG</span>

                    <strong>
                        #<?= (int) $order['OrderID'] ?>
                    </strong>
                </div>

                <div class="order-success-status">
                    <span>Trạng thái đơn hàng:</span>

                    <span class="order-status-badge <?= order_success_e($statusClass) ?>">
                        <?= order_success_e($statusLabel) ?>
                    </span>
                </div>

            </section>

            <div class="order-success-grid">

                <section class="order-info-card">

                    <div class="order-card-heading">
                        <div class="order-card-icon">
                            <span aria-hidden="true">&#128203;</span>
                        </div>

                        <div>
                            <h2>Thông tin đơn hàng</h2>
                            <p>Thông tin giao dịch của bạn</p>
                        </div>
                    </div>

                    <div class="order-info-list">

                        <div class="order-info-row">
                            <span>Mã đơn hàng</span>
                            <strong>
                                #<?= (int) $order['OrderID'] ?>
                            </strong>
                        </div>

                        <div class="order-info-row">
                            <span>Ngày đặt hàng</span>
                            <strong>
                                <?= order_success_e($order['OrderDate']) ?>
                            </strong>
                        </div>

                        <div class="order-info-row">
                            <span>Trạng thái</span>
                            <strong>
                                <?= order_success_e($statusLabel) ?>
                            </strong>
                        </div>

                    </div>

                    <div class="order-card-divider"></div>

                    <div class="order-customer-heading">
                        <span aria-hidden="true">&#128100;</span>
                        Thông tin khách hàng
                    </div>

                    <div class="order-customer-info">

                        <div class="order-customer-row">
                            <span>Họ và tên</span>
                            <strong>
                                <?= order_success_e($order['CustomerName']) ?>
                            </strong>
                        </div>

                        <div class="order-customer-row">
                            <span>Số điện thoại</span>
                            <strong>
                                <?= order_success_e($order['Phone'] ?? '') ?>
                            </strong>
                        </div>

                        <div class="order-customer-row">
                            <span>Địa chỉ nhận hàng</span>
                            <strong>
                                <?= order_success_e($order['Address'] ?? '') ?>
                            </strong>
                        </div>

                    </div>

                </section>

                <section class="order-info-card order-products-card">

                    <div class="order-card-heading">
                        <div class="order-card-icon">
                            <span aria-hidden="true">&#128722;</span>
                        </div>

                        <div>
                            <h2>Chi tiết sản phẩm</h2>
                            <p>
                                <?= count($orderItems) ?> sản phẩm trong đơn hàng
                            </p>
                        </div>
                    </div>

                    <?php if (!empty($orderItems)): ?>

                        <div class="order-product-list">

                            <?php foreach ($orderItems as $item): ?>

                                <?php
                                $quantity = (int) $item['Quantity'];
                                $unitPrice = (float) $item['UnitPrice'];
                                $subtotal = $quantity * $unitPrice;
                                ?>

                                <div class="order-product-item">

                                    <div class="order-product-details">
                                        <strong>
                                            <?= order_success_e($item['ProductName']) ?>
                                        </strong>

                                        <span>
                                            Mã sản phẩm:
                                            <?= order_success_e($item['ProductCode']) ?>
                                        </span>

                                        <span>
                                            <?= $quantity ?> x
                                            <?= number_format(
                                                $unitPrice,
                                                0,
                                                ',',
                                                '.'
                                            ) ?> đ
                                        </span>
                                    </div>

                                    <strong class="order-product-subtotal">
                                        <?= number_format(
                                            $subtotal,
                                            0,
                                            ',',
                                            '.'
                                        ) ?> đ
                                    </strong>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <p class="order-no-products">
                            Không có thông tin chi tiết sản phẩm.
                        </p>

                    <?php endif; ?>

                    <div class="order-card-divider"></div>

                    <div class="order-total-row">
                        <span>Tổng thanh toán</span>

                        <strong>
                            <?= number_format(
                                (float) $order['TotalAmount'],
                                0,
                                ',',
                                '.'
                            ) ?> đ
                        </strong>
                    </div>

                </section>

            </div>

            <section class="order-next-step">
                <div class="order-next-step-icon">
                    <span aria-hidden="true">&#128161;</span>
                </div>

                <div>
                    <strong>Thông tin dành cho bạn</strong>
                    <p>
                        Hãy lưu lại mã đơn hàng để tiện tra cứu
                        và đối chiếu thông tin khi cần thiết.
                    </p>
                </div>
            </section>

            <div class="order-success-actions">

                <a href="/products.php" class="order-primary-button">
                    Tiếp tục mua hàng
                    <span aria-hidden="true">&rarr;</span>
                </a>

                <a href="/" class="order-secondary-button">
                    Về trang chủ
                </a>

            </div>

        <?php endif; ?>

    </div>
</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

?>
