<?php

$pageTitle = 'Quản lý đơn hàng';

require_once '/var/www/src/config/database.php';

$sql = "
    SELECT
        o.OrderID,
        o.OrderDate,
        o.TotalAmount,
        o.Status,
        c.CustomerName,
        c.Phone
    FROM orders AS o
    INNER JOIN customers AS c
        ON o.CustomerID = c.CustomerID
    ORDER BY o.OrderID DESC
";

$result = $conn->query($sql);

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <div class="mb-3">
        <h2>Quản lý đơn hàng</h2>
        <p class="text-muted mb-0">
            Danh sách các đơn hàng được khách hàng đặt từ hệ thống.
        </p>
    </div>

    <div class="table-responsive">

        <table class="table table-bordered table-striped align-middle">

            <thead class="table-dark">
                <tr>
                    <th>Mã đơn</th>
                    <th>Ngày đặt</th>
                    <th>Khách hàng</th>
                    <th>Số điện thoại</th>
                    <th class="text-end">Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($order = $result->fetch_assoc()): ?>

                    <?php
                    $statusClass = match ($order['Status']) {
                        'Pending' => 'bg-warning text-dark',
                        'Confirmed' => 'bg-primary',
                        'Shipping' => 'bg-info text-dark',
                        'Completed' => 'bg-success',
                        'Cancelled' => 'bg-secondary',
                        default => 'bg-secondary'
                    };

                    $statusLabels = [
                        'Pending' => 'Chờ xác nhận',
                        'Confirmed' => 'Đã xác nhận',
                        'Shipping' => 'Đang giao hàng',
                        'Completed' => 'Hoàn thành',
                        'Cancelled' => 'Đã hủy'
                    ];

                    $statusLabel = $statusLabels[$order['Status']]
                        ?? $order['Status'];

                    $orderDate = date(
                        'd/m/Y H:i',
                        strtotime($order['OrderDate'])
                    );
                    ?>

                    <tr>

                        <td>
                            #<?= (int) $order['OrderID'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($orderDate) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($order['CustomerName']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($order['Phone'] ?? '') ?>
                        </td>

                        <td class="text-end">
                            <?= number_format(
                                (float) $order['TotalAmount'],
                                0,
                                ',',
                                '.'
                            ) ?> đ
                        </td>

                        <td>
                            <span class="badge <?= $statusClass ?>">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                        </td>

                        <td>
                            <a
                                href="/admin/orders/detail.php?id=<?= (int) $order['OrderID'] ?>"
                                class="btn btn-sm btn-primary"
                            >
                                Xem
                            </a>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        Chưa có đơn hàng nào.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();

?>
