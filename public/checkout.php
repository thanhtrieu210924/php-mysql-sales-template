<?php

require_once '/var/www/src/config/session.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once '/var/www/src/config/database.php';

function checkout_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

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

$isLoggedIn = isset($_SESSION['customer_id']);

$customerID = null;
$customerName = '';
$email = '';
$phone = '';
$address = '';

$checkoutItems = [];
$totalAmountCents = 0;
$checkoutReady = true;

// ============================================
// LẤY THÔNG TIN KHÁCH HÀNG ĐĂNG NHẬP
// ============================================

if ($isLoggedIn) {
    $customerID = (int) $_SESSION['customer_id'];

    $sqlCustomer = "
        SELECT
            CustomerID,
            CustomerName,
            Email,
            Phone,
            Address
        FROM customers
        WHERE CustomerID = ?
          AND Email IS NOT NULL
        LIMIT 1
    ";

    $stmtCustomer = $conn->prepare($sqlCustomer);
    $stmtCustomer->bind_param('i', $customerID);
    $stmtCustomer->execute();

    $resultCustomer = $stmtCustomer->get_result();
    $customer = $resultCustomer->fetch_assoc();

    $resultCustomer->free();
    $stmtCustomer->close();

    if (!$customer) {
        unset($_SESSION['customer_id']);
        unset($_SESSION['customer_name']);

        header('Location: /login.php');
        exit;
    }

    $customerName = $customer['CustomerName'];
    $email = $customer['Email'];
    $phone = $customer['Phone'] ?? '';
    $address = $customer['Address'] ?? '';

    $_SESSION['customer_name'] = $customerName;
}

// ============================================
// XỬ LÝ ĐẶT HÀNG
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isLoggedIn) {
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
    } else {
        $customerName = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
    }

    if (
        $customerName === ''
        || $phone === ''
        || $address === ''
    ) {
        $checkoutError = 'Vui lòng nhập đầy đủ thông tin khách hàng.';
    } elseif (
        strlen($customerName) > 100
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
            // XỬ LÝ KHÁCH HÀNG
            // ============================================

            if ($isLoggedIn) {
                // Khách đăng nhập: khóa bản ghi khách hàng hiện tại.
                $sqlCustomer = "
                    SELECT CustomerID
                    FROM customers
                    WHERE CustomerID = ?
                      AND Email IS NOT NULL
                    FOR UPDATE
                ";

                $stmtCustomer = $conn->prepare($sqlCustomer);
                $stmtCustomer->bind_param('i', $customerID);
                $stmtCustomer->execute();

                $resultCustomer = $stmtCustomer->get_result();
                $existingCustomer = $resultCustomer->fetch_assoc();

                $resultCustomer->free();
                $stmtCustomer->close();

                if (!$existingCustomer) {
                    throw new RuntimeException(
                        'Tài khoản khách hàng không còn hợp lệ. '
                        . 'Vui lòng đăng nhập lại.'
                    );
                }

                // Cập nhật số điện thoại và địa chỉ.
                $sqlUpdateCustomer = "
                    UPDATE customers
                    SET Phone = ?,
                        Address = ?
                    WHERE CustomerID = ?
                ";

                $stmtUpdateCustomer = $conn->prepare(
                    $sqlUpdateCustomer
                );

                $stmtUpdateCustomer->bind_param(
                    'ssi',
                    $phone,
                    $address,
                    $customerID
                );

                $stmtUpdateCustomer->execute();
                $stmtUpdateCustomer->close();
            } else {
                // Khách vãng lai: tạo customer mới như HO12.
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
            }

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

<main class="checkout-page">
    <div class="checkout-container">

        <div class="checkout-heading">
            <div>
                <span class="checkout-kicker">SECURE CHECKOUT</span>
                <h1>Thanh toán đơn hàng</h1>
                <p>
                    Kiểm tra thông tin giao hàng và xác nhận đơn hàng
                    của bạn.
                </p>
            </div>

            <a href="/cart.php" class="checkout-back-link">
                <span aria-hidden="true">&larr;</span>
                Quay lại giỏ hàng
            </a>
        </div>

        <div class="checkout-progress">
            <div class="checkout-step checkout-step-completed">
                <span class="checkout-step-number">&#10003;</span>
                <span>Giỏ hàng</span>
            </div>

            <div class="checkout-progress-line checkout-progress-active"></div>

            <div class="checkout-step checkout-step-current">
                <span class="checkout-step-number">2</span>
                <span>Thanh toán</span>
            </div>

            <div class="checkout-progress-line"></div>

            <div class="checkout-step">
                <span class="checkout-step-number">3</span>
                <span>Hoàn tất</span>
            </div>
        </div>

        <?php if ($checkoutError !== ''): ?>
            <div class="checkout-alert" role="alert">
                <span class="checkout-alert-icon">!</span>
                <div>
                    <strong>Không thể tiếp tục thanh toán</strong>
                    <p><?= checkout_e($checkoutError) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($checkoutItems)): ?>

            <form
                method="post"
                action="/checkout.php"
                class="checkout-layout"
            >

                <section class="checkout-form-column">

                    <div class="checkout-card">
                        <div class="checkout-card-heading">
                            <div class="checkout-card-icon">
                                <span aria-hidden="true">&#128100;</span>
                            </div>

                            <div>
                                <h2>Thông tin khách hàng</h2>
                                <p>
                                    Thông tin cần thiết để xử lý đơn hàng
                                </p>
                            </div>
                        </div>

                        <?php if ($isLoggedIn): ?>

                            <div class="checkout-account-note">
                                <span aria-hidden="true">&#10003;</span>
                                Bạn đang đặt hàng bằng tài khoản đã đăng nhập.
                            </div>

                            <div class="checkout-field">
                                <label for="customer_name">
                                    Họ và tên
                                </label>

                                <input
                                    type="text"
                                    id="customer_name"
                                    class="checkout-input checkout-input-readonly"
                                    value="<?= checkout_e($customerName) ?>"
                                    readonly
                                >
                            </div>

                            <div class="checkout-field">
                                <label for="email">
                                    Email
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    class="checkout-input checkout-input-readonly"
                                    value="<?= checkout_e($email) ?>"
                                    readonly
                                >
                            </div>

                        <?php else: ?>

                            <div class="checkout-guest-note">
                                Bạn đang đặt hàng với tư cách khách vãng lai.
                            </div>

                            <div class="checkout-field">
                                <label for="customer_name">
                                    Họ và tên <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    id="customer_name"
                                    name="customer_name"
                                    class="checkout-input"
                                    maxlength="100"
                                    placeholder="Nhập họ và tên của bạn"
                                    value="<?= checkout_e($customerName) ?>"
                                    autocomplete="name"
                                    required
                                >
                            </div>

                        <?php endif; ?>

                        <div class="checkout-field">
                            <label for="phone">
                                Số điện thoại <span>*</span>
                            </label>

                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                class="checkout-input"
                                maxlength="20"
                                placeholder="Nhập số điện thoại"
                                value="<?= checkout_e($phone) ?>"
                                autocomplete="tel"
                                required
                            >
                        </div>

                        <div class="checkout-field">
                            <label for="address">
                                Địa chỉ nhận hàng <span>*</span>
                            </label>

                            <textarea
                                id="address"
                                name="address"
                                class="checkout-input checkout-textarea"
                                rows="4"
                                maxlength="200"
                                placeholder="Nhập địa chỉ nhận hàng đầy đủ"
                                autocomplete="street-address"
                                required
                            ><?= checkout_e($address) ?></textarea>
                        </div>
                    </div>

                    <div class="checkout-information-card">
                        <div class="checkout-information-icon">
                            <span aria-hidden="true">&#128274;</span>
                        </div>

                        <div>
                            <strong>Thông tin đặt hàng an toàn</strong>
                            <p>
                                Vui lòng kiểm tra kỹ thông tin trước khi
                                xác nhận. Đơn hàng sẽ được ghi nhận sau khi
                                hệ thống xử lý thành công.
                            </p>
                        </div>
                    </div>

                </section>

                <aside class="checkout-summary-column">

                    <div class="checkout-card checkout-summary-card">
                        <div class="checkout-summary-heading">
                            <div>
                                <span class="checkout-summary-kicker">
                                    ORDER SUMMARY
                                </span>
                                <h2>Đơn hàng của bạn</h2>
                            </div>

                            <span class="checkout-item-count">
                                <?= count($checkoutItems) ?> sản phẩm
                            </span>
                        </div>

                        <div class="checkout-order-items">

                            <?php foreach ($checkoutItems as $item): ?>

                                <div class="checkout-order-item">
                                    <div class="checkout-order-product">
                                        <strong>
                                            <?= checkout_e($item['ProductName']) ?>
                                        </strong>

                                        <span>
                                            <?= (int) $item['Quantity'] ?> x
                                            <?= number_format(
                                                (float) $item['Price'],
                                                0,
                                                ',',
                                                '.'
                                            ) ?> đ
                                        </span>
                                    </div>

                                    <strong class="checkout-order-price">
                                        <?= number_format(
                                            $item['SubtotalCents'] / 100,
                                            0,
                                            ',',
                                            '.'
                                        ) ?> đ
                                    </strong>
                                </div>

                            <?php endforeach; ?>

                        </div>

                        <div class="checkout-summary-divider"></div>

                        <div class="checkout-total-row">
                            <span>Tạm tính</span>
                            <strong>
                                <?= number_format(
                                    $totalAmountCents / 100,
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ
                            </strong>
                        </div>

                        <div class="checkout-total-row">
                            <span>Phí vận chuyển</span>
                            <strong>Chưa áp dụng</strong>
                        </div>

                        <div class="checkout-summary-divider"></div>

                        <div class="checkout-grand-total">
                            <span>Tổng thanh toán</span>

                            <strong>
                                <?= number_format(
                                    $totalAmountCents / 100,
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ
                            </strong>
                        </div>

                        <button
                            type="submit"
                            class="checkout-submit-button"
                            <?= !$checkoutReady ? 'disabled' : '' ?>
                        >
                            Xác nhận đặt hàng
                            <span aria-hidden="true">&rarr;</span>
                        </button>

                        <a href="/cart.php" class="checkout-edit-cart">
                            Chỉnh sửa giỏ hàng
                        </a>

                        <div class="checkout-summary-note">
                            <span aria-hidden="true">&#128274;</span>
                            Kiểm tra thông tin trước khi xác nhận đơn hàng.
                        </div>
                    </div>

                </aside>

            </form>

        <?php else: ?>

            <div class="checkout-empty-state">
                <div class="checkout-empty-icon">
                    <span aria-hidden="true">&#128722;</span>
                </div>

                <h2>Chưa có sản phẩm để thanh toán</h2>

                <p>
                    Giỏ hàng hiện không có sản phẩm hợp lệ.
                    Vui lòng quay lại giỏ hàng để kiểm tra.
                </p>

                <a href="/cart.php" class="checkout-submit-button">
                    Quay lại giỏ hàng
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>

        <?php endif; ?>

    </div>
</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

?>
