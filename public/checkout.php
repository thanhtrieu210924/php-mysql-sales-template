<?php

require_once '/var/www/src/config/session.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once '/var/www/src/config/database.php';


// ============================================
// KIỂM TRA GIỎ HÀNG
// ============================================

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart = [];

foreach ($_SESSION['cart'] as $productID => $quantity) {

    $productID = (int) $productID;
    $quantity = (int) $quantity;

    if ($productID > 0 && $quantity > 0) {
        $cart[$productID] = $quantity;
    }
}

$_SESSION['cart'] = $cart;

if (empty($cart)) {
    header('Location: /cart.php');
    exit;
}


// ============================================
// KHỞI TẠO DỮ LIỆU
// ============================================

$pageTitle = 'Thanh toán';

$checkoutError = '';

$customerName = trim($_POST['customer_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

$checkoutItems = [];
$totalAmountCents = 0;
$checkoutReady = true;


// ============================================
// XỬ LÝ ĐẶT HÀNG
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($customerName === '' || $phone === '' || $address === '') {

        $checkoutError = 'Vui lòng nhập đầy đủ thông tin khách hàng.';

    } elseif (
        strlen($customerName) > 200
        || strlen($phone) > 20
        || strlen($address) > 200
    ) {

        $checkoutError = 'Thông tin khách hàng vượt quá độ dài cho phép.';

    } else {

        $transactionStarted = false;

        try {

            // Bắt đầu transaction.

            $conn->begin_transaction();
            $transactionStarted = true;

            // Sắp xếp ProductID để khóa sản phẩm theo thứ tự nhất quán.

            $cartForCheckout = $cart;
            ksort($cartForCheckout, SORT_NUMERIC);

            $lockedItems = [];
            $totalAmountCents = 0;

            // ============================================
            // KIỂM TRA VÀ KHÓA TỒN KHO
            // ============================================

            foreach ($cartForCheckout as $productID => $quantity) {

                $sqlProduct = "
                    SELECT
                        ProductID,
                        ProductCode,
                        ProductName,
                        Price,
                        StockQuantity
                    FROM products
                    WHERE ProductID = ?
                      AND IsActive = 1
                    FOR UPDATE
                ";

                $stmtProduct = $conn->prepare($sqlProduct);
                $stmtProduct->bind_param('i', $productID);
                $stmtProduct->execute();

                $resultProduct = $stmtProduct->get_result();
                $product = $resultProduct->fetch_assoc();

                $resultProduct->free();
                $stmtProduct->close();

                if (!$product) {
                    throw new RuntimeException(
                        'Một sản phẩm trong giỏ hàng không còn khả dụng. '
                        . 'Vui lòng kiểm tra lại giỏ hàng.'
                    );
                }

                $stockQuantity = (int) $product['StockQuantity'];

                if ($stockQuantity < $quantity) {
                    throw new RuntimeException(
                        'Sản phẩm "' . $product['ProductName']
                        . '" không đủ số lượng tồn kho. '
                        . 'Số lượng còn lại: ' . $stockQuantity . '.'
                    );
                }

                // Tính tiền bằng đơn vị xu để hạn chế sai số số thực.

                $unitPriceCents = (int) round(
                    (float) $product['Price'] * 100
                );

                $subtotalCents = $unitPriceCents * $quantity;

                $totalAmountCents += $subtotalCents;

                $product['Quantity'] = $quantity;
                $product['SubtotalCents'] = $subtotalCents;

                $lockedItems[] = $product;
            }


            // ============================================
            // TẠO KHÁCH HÀNG
            // ============================================

            $sqlCustomer = "
                INSERT INTO customers (
                    CustomerName,
                    Phone,
                    Address
                )
                VALUES (?, ?, ?)
            ";

            $stmtCustomer = $conn->prepare($sqlCustomer);

            $stmtCustomer->bind_param(
                'sss',
                $customerName,
                $phone,
                $address
            );

            $stmtCustomer->execute();

            $customerID = $conn->insert_id;

            $stmtCustomer->close();


            // ============================================
            // TẠO ĐƠN HÀNG
            // ============================================

            $totalAmount = number_format(
                $totalAmountCents / 100,
                2,
                '.',
                ''
            );

            $orderStatus = 'Pending';

            $sqlOrder = "
                INSERT INTO orders (
                    TotalAmount,
                    Status,
                    CustomerID
                )
                VALUES (?, ?, ?)
            ";

            $stmtOrder = $conn->prepare($sqlOrder);

            $stmtOrder->bind_param(
                'ssi',
                $totalAmount,
                $orderStatus,
                $customerID
            );

            $stmtOrder->execute();

            $orderID = $conn->insert_id;

            $stmtOrder->close();


            // ============================================
            // TẠO CHI TIẾT ĐƠN HÀNG
            // VÀ CẬP NHẬT TỒN KHO
            // ============================================

            $sqlDetail = "
                INSERT INTO orderdetail (
                    Quantity,
                    UnitPrice,
                    OrderID,
                    ProductID
                )
                VALUES (?, ?, ?, ?)
            ";

            $stmtDetail = $conn->prepare($sqlDetail);


            $sqlUpdateStock = "
                UPDATE products
                SET StockQuantity = StockQuantity - ?
                WHERE ProductID = ?
                  AND StockQuantity >= ?
                  AND IsActive = 1
            ";

            $stmtUpdateStock = $conn->prepare($sqlUpdateStock);


            foreach ($lockedItems as $item) {

                $quantity = (int) $item['Quantity'];
                $unitPrice = (string) $item['Price'];
                $productID = (int) $item['ProductID'];

                // Lưu đơn giá tại thời điểm đặt hàng.

                $stmtDetail->bind_param(
                    'isii',
                    $quantity,
                    $unitPrice,
                    $orderID,
                    $productID
                );

                $stmtDetail->execute();


                // Trừ tồn kho.

                $stmtUpdateStock->bind_param(
                    'iii',
                    $quantity,
                    $productID,
                    $quantity
                );

                $stmtUpdateStock->execute();

                if ($stmtUpdateStock->affected_rows !== 1) {
                    throw new RuntimeException(
                        'Không thể cập nhật tồn kho sản phẩm. '
                        . 'Vui lòng thử lại.'
                    );
                }
            }

            $stmtDetail->close();
            $stmtUpdateStock->close();


            // ============================================
            // HOÀN TẤT TRANSACTION
            // ============================================

            $conn->commit();
            $transactionStarted = false;

            // Chỉ xóa giỏ hàng sau khi COMMIT thành công.

            $_SESSION['cart'] = [];

            header(
                'Location: /order-success.php?id=' . $orderID
            );

            exit;

        } catch (Throwable $e) {

            if ($transactionStarted) {
                try {
                    $conn->rollback();
                } catch (Throwable $rollbackError) {
                    error_log(
                        'Checkout rollback error: '
                        . $rollbackError->getMessage()
                    );
                }
            }

            error_log(
                'Checkout error: ' . $e->getMessage()
            );

            if ($e instanceof RuntimeException) {
                $checkoutError = $e->getMessage();
            } else {
                $checkoutError =
                    'Không thể hoàn tất đơn hàng. '
                    . 'Vui lòng thử lại sau.';
            }
        }
    }
}


// ============================================
// LẤY LẠI SẢN PHẨM ĐỂ HIỂN THỊ
// ============================================

$totalAmountCents = 0;

foreach ($cart as $productID => $quantity) {

    $sql = "
        SELECT
            ProductID,
            ProductCode,
            ProductName,
            Price,
            StockQuantity
        FROM products
        WHERE ProductID = ?
          AND IsActive = 1
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param('i', $productID);
    $stmt->execute();

    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    $result->free();
    $stmt->close();

    if (!$product) {

        $checkoutReady = false;

        if ($checkoutError === '') {
            $checkoutError =
                'Có sản phẩm không còn khả dụng. '
                . 'Vui lòng quay lại giỏ hàng.';
        }

        continue;
    }

    $stockQuantity = (int) $product['StockQuantity'];

    if ($stockQuantity < $quantity) {

        $checkoutReady = false;

        if ($checkoutError === '') {
            $checkoutError =
                'Sản phẩm "' . $product['ProductName']
                . '" không đủ số lượng tồn kho. '
                . 'Số lượng còn lại: ' . $stockQuantity . '.';
        }
    }

    $unitPriceCents = (int) round(
        (float) $product['Price'] * 100
    );

    $subtotalCents = $unitPriceCents * $quantity;

    $product['Quantity'] = $quantity;
    $product['SubtotalCents'] = $subtotalCents;

    $checkoutItems[] = $product;

    $totalAmountCents += $subtotalCents;
}


// ============================================
// NẠP GIAO DIỆN
// ============================================

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-5">

    <div class="mb-4">

        <h1>Thanh toán</h1>

        <p class="text-muted">
            Vui lòng kiểm tra thông tin và xác nhận đơn hàng.
        </p>

    </div>


    <?php if ($checkoutError !== ''): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars(
                $checkoutError,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($checkoutItems)): ?>

        <div class="row g-4">

            <div class="col-lg-7">

                <div class="card">

                    <div class="card-header">
                        <h5 class="mb-0">
                            Thông tin khách hàng
                        </h5>
                    </div>

                    <div class="card-body">

                        <form
                            method="post"
                            action="/checkout.php"
                        >

                            <div class="mb-3">

                                <label
                                    for="customer_name"
                                    class="form-label"
                                >
                                    Họ và tên
                                </label>

                                <input
                                    type="text"
                                    id="customer_name"
                                    name="customer_name"
                                    class="form-control"
                                    maxlength="200"
                                    value="<?= htmlspecialchars(
                                        $customerName,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    required
                                >

                            </div>


                            <div class="mb-3">

                                <label
                                    for="phone"
                                    class="form-label"
                                >
                                    Số điện thoại
                                </label>

                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    class="form-control"
                                    maxlength="20"
                                    value="<?= htmlspecialchars(
                                        $phone,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    required
                                >

                            </div>


                            <div class="mb-3">

                                <label
                                    for="address"
                                    class="form-label"
                                >
                                    Địa chỉ
                                </label>

                                <textarea
                                    id="address"
                                    name="address"
                                    class="form-control"
                                    rows="3"
                                    maxlength="200"
                                    required
                                ><?= htmlspecialchars(
                                    $address,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                            </div>


                            <div class="d-flex flex-wrap gap-2">

                                <a
                                    href="/cart.php"
                                    class="btn btn-outline-secondary"
                                >
                                    Quay lại giỏ hàng
                                </a>

                                <button
                                    type="submit"
                                    class="btn btn-success"
                                    <?= !$checkoutReady ? 'disabled' : '' ?>
                                >
                                    Xác nhận đặt hàng
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>


            <div class="col-lg-5">

                <div class="card">

                    <div class="card-header">

                        <h5 class="mb-0">
                            Đơn hàng của bạn
                        </h5>

                    </div>

                    <div class="card-body">

                        <?php foreach ($checkoutItems as $item): ?>

                            <div class="d-flex justify-content-between mb-3">

                                <div>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $item['ProductName'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                    <div class="text-muted small">

                                        <?= (int) $item['Quantity'] ?>
                                        x
                                        <?= number_format(
                                            (float) $item['Price'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?> đ

                                    </div>

                                </div>

                                <div class="text-end">

                                    <?= number_format(
                                        $item['SubtotalCents'] / 100,
                                        0,
                                        ',',
                                        '.'
                                    ) ?> đ

                                </div>

                            </div>

                        <?php endforeach; ?>


                        <hr>


                        <div class="d-flex justify-content-between">

                            <strong>Tổng thanh toán</strong>

                            <strong class="text-danger fs-5">

                                <?= number_format(
                                    $totalAmountCents / 100,
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

    <?php else: ?>

        <div class="alert alert-warning">
            Không có sản phẩm hợp lệ để thanh toán.
        </div>

        <a
            href="/cart.php"
            class="btn btn-outline-secondary"
        >
            Quay lại giỏ hàng
        </a>

    <?php endif; ?>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

?>