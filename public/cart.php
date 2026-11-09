<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}


// ============================================
// XỬ LÝ CẬP NHẬT VÀ XÓA SẢN PHẨM
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CẬP NHẬT GIỎ HÀNG

    if (isset($_POST['update_cart'])) {

        $quantities = $_POST['quantities'] ?? [];

        if (is_array($quantities)) {

            foreach ($quantities as $productID => $quantity) {

                $productID = (int) $productID;
                $quantity = (int) $quantity;

                if (
                    $productID <= 0
                    || !array_key_exists(
                        $productID,
                        $_SESSION['cart']
                    )
                ) {
                    continue;
                }

                if ($quantity <= 0) {
                    unset($_SESSION['cart'][$productID]);
                    continue;
                }

                $sqlStock = "
                    SELECT StockQuantity
                    FROM products
                    WHERE ProductID = ?
                      AND IsActive = 1
                ";

                $stmtStock = $conn->prepare($sqlStock);

                if (!$stmtStock) {
                    die('Lỗi chuẩn bị truy vấn tồn kho: ' . $conn->error);
                }

                $stmtStock->bind_param('i', $productID);
                $stmtStock->execute();

                $stockResult = $stmtStock->get_result();
                $stockRow = $stockResult->fetch_assoc();

                $stockResult->free();
                $stmtStock->close();

                if (!$stockRow) {
                    unset($_SESSION['cart'][$productID]);
                    continue;
                }

                $stockQuantity = (int) $stockRow['StockQuantity'];

                if ($stockQuantity <= 0) {
                    unset($_SESSION['cart'][$productID]);
                    continue;
                }

                $_SESSION['cart'][$productID] =
                    min($quantity, $stockQuantity);
            }
        }

        header('Location: /cart.php');
        exit;
    }


    // XÓA SẢN PHẨM KHỎI GIỎ HÀNG

    if (isset($_POST['remove_product'])) {

        $productID = (int) $_POST['remove_product'];

        if ($productID > 0) {
            unset($_SESSION['cart'][$productID]);
        }

        header('Location: /cart.php');
        exit;
    }
}


// ============================================
// LẤY DỮ LIỆU GIỎ HÀNG
// ============================================

$cart = $_SESSION['cart'];

$cartItems = [];
$totalAmount = 0;

if (!empty($cart)) {

    foreach ($cart as $productID => $quantity) {

        $productID = (int) $productID;
        $quantity = (int) $quantity;

        if ($productID <= 0 || $quantity <= 0) {
            unset($_SESSION['cart'][$productID]);
            continue;
        }

        $sql = "
            SELECT
                p.ProductID,
                p.ProductCode,
                p.ProductName,
                p.Price,
                p.StockQuantity,

                (
                    SELECT pi.ImageFile
                    FROM product_images pi
                    WHERE pi.ProductID = p.ProductID
                      AND pi.IsPrimary = 1
                    LIMIT 1
                ) AS ImageFile

            FROM products p

            WHERE
                p.ProductID = ?
                AND p.IsActive = 1
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die('Lỗi chuẩn bị truy vấn sản phẩm: ' . $conn->error);
        }

        $stmt->bind_param('i', $productID);
        $stmt->execute();

        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        $result->free();
        $stmt->close();

        if (!$product) {
            unset($_SESSION['cart'][$productID]);
            continue;
        }

        $stockQuantity = (int) $product['StockQuantity'];

        if ($stockQuantity <= 0) {
            unset($_SESSION['cart'][$productID]);
            continue;
        }

        // Đồng bộ số lượng với tồn kho hiện tại.

        if ($quantity > $stockQuantity) {
            $quantity = $stockQuantity;

            $_SESSION['cart'][$productID] = $quantity;
        }

        $subtotal = (float) $product['Price'] * $quantity;

        $product['Quantity'] = $quantity;
        $product['Subtotal'] = $subtotal;

        $cartItems[] = $product;

        $totalAmount += $subtotal;
    }
}


// ============================================
// NẠP GIAO DIỆN FRONTEND
// ============================================

$pageTitle = 'Giỏ hàng';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-5">

    <div class="mb-4">

        <h1>Giỏ hàng</h1>

        <p class="text-muted">
            Các sản phẩm bạn đã chọn.
        </p>

    </div>

    <?php if (empty($cartItems)): ?>

        <div class="alert alert-info">
            Giỏ hàng của bạn đang trống.
        </div>

        <a
            href="/products.php"
            class="btn btn-primary"
        >
            Tiếp tục mua hàng
        </a>

    <?php else: ?>

        <form method="post" action="/cart.php">

            <div class="table-responsive">

                <table class="table align-middle">

                    <thead>

                        <tr>
                            <th>Sản phẩm</th>
                            <th class="text-end">Đơn giá</th>
                            <th class="text-center">Số lượng</th>
                            <th class="text-end">Thành tiền</th>
                            <th class="text-center">Thao tác</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($cartItems as $item): ?>

                            <tr>

                                <td>

                                    <div
                                        class="d-flex
                                               align-items-center
                                               gap-3"
                                    >

                                        <?php if (!empty($item['ImageFile'])): ?>

                                            <img
                                                src="/uploads/products/<?= htmlspecialchars(
                                                    basename($item['ImageFile']),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                alt="<?= htmlspecialchars(
                                                    $item['ProductName'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                style="
                                                    width: 80px;
                                                    height: 80px;
                                                    object-fit: contain;
                                                "
                                            >

                                        <?php endif; ?>

                                        <div>

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

                                        </div>

                                    </div>

                                </td>

                                <td class="text-end">

                                    <?= number_format(
                                        (float) $item['Price'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?> đ

                                </td>

                                <td class="text-center">

                                    <input
                                        type="number"
                                        name="quantities[<?= (int) $item['ProductID'] ?>]"
                                        value="<?= (int) $item['Quantity'] ?>"
                                        min="1"
                                        max="<?= (int) $item['StockQuantity'] ?>"
                                        class="form-control mx-auto"
                                        style="width: 90px;"
                                    >

                                </td>

                                <td class="text-end fw-bold">

                                    <?= number_format(
                                        (float) $item['Subtotal'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?> đ

                                </td>

                                <td class="text-center">

                                    <button
                                        type="submit"
                                        name="remove_product"
                                        value="<?= (int) $item['ProductID'] ?>"
                                        class="btn btn-sm btn-outline-danger"
                                        formnovalidate
                                    >
                                        Xóa
                                    </button>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                    <tfoot>

                        <tr>

                            <th
                                colspan="3"
                                class="text-end"
                            >
                                Tổng cộng
                            </th>

                            <th class="text-end fs-5">

                                <?= number_format(
                                    $totalAmount,
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ

                            </th>

                            <th></th>

                        </tr>

                    </tfoot>

                </table>

            </div>

            <div class="d-flex justify-content-end mt-3">

                <button
                    type="submit"
                    name="update_cart"
                    class="btn btn-primary"
                >
                    Cập nhật giỏ hàng
                </button>

            </div>

        </form>

        <div class="mt-4 d-flex flex-wrap gap-2">

            <a
                href="/products.php"
                class="btn btn-outline-secondary"
            >
                Tiếp tục mua hàng
            </a>

            <a
                href="/checkout.php"
                class="btn btn-success"
            >
                Tiến hành đặt hàng
            </a>

        </div>

    <?php endif; ?>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

?>
