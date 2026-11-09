<?php

require_once '/var/www/src/config/session.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once '/var/www/src/config/database.php';


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
// NẠP GIAO DIỆN
// ============================================

$pageTitle = 'Đặt hàng thành công';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-5">

    <?php if (!$order): ?>

        <div class="alert alert-warning">

            Không tìm thấy đơn hàng.
            Vui lòng kiểm tra lại mã đơn hàng.

        </div>

        <a
            href="/products.php"
            class="btn btn-primary"
        >
            Tiếp tục mua hàng
        </a>

    <?php else: ?>

        <div class="text-center mb-5">

            <div class="mb-3">

                <span
                    class="d-inline-flex
                           align-items-center
                           justify-content-center
                           rounded-circle
                           bg-success-subtle
                           text-success"
                    style="
                        width: 80px;
                        height: 80px;
                        font-size: 40px;
                    "
                >
                    &#10003;
                </span>

            </div>

            <h1 class="text-success">
                Đặt hàng thành công!
            </h1>

            <p class="text-muted">
                Cảm ơn bạn đã mua hàng.
                Đơn hàng của bạn đã được ghi nhận.
            </p>

            <h5>
                Mã đơn hàng:
                #<?= (int) $order['OrderID'] ?>
            </h5>

        </div>


        <div class="row g-4">

            <div class="col-lg-5">

                <div class="card h-100">

                    <div class="card-header">

                        <h5 class="mb-0">
                            Thông tin đơn hàng
                        </h5>

                    </div>

                    <div class="card-body">

                        <p>
                            <strong>Mã đơn hàng:</strong>
                            #<?= (int) $order['OrderID'] ?>
                        </p>

                        <p>
                            <strong>Ngày đặt hàng:</strong>

                            <?= htmlspecialchars(
                                $order['OrderDate'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </p>

                        <p>
                            <strong>Trạng thái:</strong>

                            <span class="badge bg-warning text-dark">

                                <?= htmlspecialchars(
                                    $order['Status'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </p>

                        <hr>

                        <h6>Thông tin khách hàng</h6>

                        <p class="mb-2">

                            <strong>Họ và tên:</strong>

                            <?= htmlspecialchars(
                                $order['CustomerName'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </p>

                        <p class="mb-2">

                            <strong>Số điện thoại:</strong>

                            <?= htmlspecialchars(
                                $order['Phone'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </p>

                        <p class="mb-0">

                            <strong>Địa chỉ:</strong>

                            <?= htmlspecialchars(
                                $order['Address'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </p>

                    </div>

                </div>

            </div>


            <div class="col-lg-7">

                <div class="card h-100">

                    <div class="card-header">

                        <h5 class="mb-0">
                            Chi tiết sản phẩm
                        </h5>

                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table class="table align-middle">

                                <thead>

                                    <tr>
                                        <th>Sản phẩm</th>
                                        <th class="text-center">SL</th>
                                        <th class="text-end">Đơn giá</th>
                                        <th class="text-end">Thành tiền</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($orderItems as $item): ?>

                                        <?php

                                        $quantity = (int) $item['Quantity'];

                                        $unitPrice = (float) $item['UnitPrice'];

                                        $subtotal = $quantity * $unitPrice;

                                        ?>

                                        <tr>

                                            <td>

                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $item['ProductName'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </strong>

                                                <div class="text-muted small">

                                                    <?= htmlspecialchars(
                                                        $item['ProductCode'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </div>

                                            </td>

                                            <td class="text-center">

                                                <?= $quantity ?>

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

                                                <?= number_format(
                                                    $subtotal,
                                                    0,
                                                    ',',
                                                    '.'
                                                ) ?> đ

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>


                        <hr>


                        <div class="d-flex justify-content-between">

                            <strong>Tổng thanh toán</strong>

                            <strong class="text-danger fs-5">

                                <?= number_format(
                                    (float) $order['TotalAmount'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ

                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="text-center mt-4">

            <a
                href="/products.php"
                class="btn btn-primary"
            >
                Tiếp tục mua hàng
            </a>

            <a
                href="/"
                class="btn btn-outline-secondary"
            >
                Về trang chủ
            </a>

        </div>

    <?php endif; ?>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

?>
