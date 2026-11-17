<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ============================================
// XỬ LÝ CẬP NHẬT VÀ XÓA SẢN PHẨM
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CẬP NHẬT SỐ LƯỢNG
    if (isset($_POST['update_cart'])) {

        $quantities = $_POST['quantities'] ?? [];

        if (is_array($quantities)) {

            foreach ($quantities as $productID => $quantity) {

                $productID = (int) $productID;
                $quantity = (int) $quantity;

                if (
                    $productID <= 0
                    || !array_key_exists($productID, $_SESSION['cart'])
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

                $_SESSION['cart'][$productID] = min($quantity, $stockQuantity);
            }
        }

        header('Location: /cart.php');
        exit;
    }

    // XÓA SẢN PHẨM
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
$totalQuantity = 0;

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
            WHERE p.ProductID = ?
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

        // Đồng bộ số lượng với tồn kho
        if ($quantity > $stockQuantity) {
            $quantity = $stockQuantity;
            $_SESSION['cart'][$productID] = $quantity;
        }

        $subtotal = (float) $product['Price'] * $quantity;

        $product['Quantity'] = $quantity;
        $product['Subtotal'] = $subtotal;

        $cartItems[] = $product;

        $totalAmount += $subtotal;
        $totalQuantity += $quantity;
    }
}

// ============================================
// NẠP GIAO DIỆN FRONTEND
// ============================================

$pageTitle = 'Giỏ hàng';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="cart-page">
    <div class="home-container">

        <!-- Tiêu đề -->
        <section class="cart-page-heading">
            <div>
                <span class="section-kicker">SHOPPING CART</span>
                <h1>Giỏ hàng của bạn</h1>
                <p>Kiểm tra sản phẩm và số lượng trước khi đặt hàng.</p>
            </div>

            <a href="/products.php" class="cart-continue-top">
                <i class="bi bi-arrow-left"></i>
                Tiếp tục mua hàng
            </a>
        </section>

        <?php if (empty($cartItems)): ?>

            <!-- Giỏ hàng trống -->
            <section class="cart-empty-state">
                <div class="cart-empty-icon">
                    <i class="bi bi-cart3"></i>
                </div>

                <h2>Giỏ hàng đang trống</h2>

                <p>
                    Hiện tại chưa có sản phẩm nào trong giỏ hàng.
                    Hãy khám phá các sản phẩm và chọn món hàng bạn yêu thích nhé!
                </p>

                <a href="/products.php" class="btn-tech-primary">
                    <i class="bi bi-bag"></i>
                    Khám phá sản phẩm
                </a>
            </section>

        <?php else: ?>

            <div class="cart-layout">

                <!-- Danh sách sản phẩm -->
                <section class="cart-items-section">

                    <div class="cart-section-heading">
                        <h2>Sản phẩm đã chọn</h2>
                        <span><?= count($cartItems) ?> sản phẩm</span>
                    </div>

                    <form method="post" action="/cart.php">

                        <div class="cart-table-wrapper">
                            <table class="cart-table">

                                <thead>
                                    <tr>
                                        <th class="cart-product-column">Sản phẩm</th>
                                        <th class="cart-price-column">Đơn giá</th>
                                        <th class="cart-quantity-column">Số lượng</th>
                                        <th class="cart-subtotal-column">Thành tiền</th>
                                        <th class="cart-action-column"></th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($cartItems as $item): ?>

                                        <tr>
                                            <!-- Sản phẩm -->
                                            <td>
                                                <div class="cart-product-info">

                                                    <a
                                                        href="/product-detail.php?id=<?= (int) $item['ProductID'] ?>"
                                                        class="cart-product-image"
                                                    >
                                                        <?php if (!empty($item['ImageFile'])): ?>
                                                            <img
                                                                src="/uploads/products/<?= rawurlencode(basename($item['ImageFile'])) ?>"
                                                                alt="<?= e($item['ProductName']) ?>"
                                                            >
                                                        <?php else: ?>
                                                            <i class="bi bi-image"></i>
                                                        <?php endif; ?>
                                                    </a>

                                                    <div class="cart-product-details">
                                                        <a
                                                            href="/product-detail.php?id=<?= (int) $item['ProductID'] ?>"
                                                            class="cart-product-name"
                                                        >
                                                            <?= e($item['ProductName']) ?>
                                                        </a>

                                                        <span class="cart-product-code">
                                                            Mã: <?= e($item['ProductCode']) ?>
                                                        </span>

                                                        <span class="cart-product-stock">
                                                            <i class="bi bi-check-circle-fill"></i>
                                                            Còn <?= (int) $item['StockQuantity'] ?> sản phẩm
                                                        </span>
                                                    </div>

                                                </div>
                                            </td>

                                            <!-- Đơn giá -->
                                            <td class="cart-price">
                                                <?= number_format((float) $item['Price'], 0, ',', '.') ?> đ
                                            </td>

                                            <!-- Số lượng -->
                                            <td>
                                                <input
                                                    type="number"
                                                    name="quantities[<?= (int) $item['ProductID'] ?>]"
                                                    value="<?= (int) $item['Quantity'] ?>"
                                                    min="1"
                                                    max="<?= (int) $item['StockQuantity'] ?>"
                                                    class="cart-quantity-input"
                                                    aria-label="Số lượng <?= e($item['ProductName']) ?>"
                                                >
                                            </td>

                                            <!-- Thành tiền -->
                                            <td class="cart-subtotal">
                                                <?= number_format((float) $item['Subtotal'], 0, ',', '.') ?> đ
                                            </td>

                                            <!-- Xóa -->
                                            <td class="cart-action">
                                                <button
                                                    type="submit"
                                                    name="remove_product"
                                                    value="<?= (int) $item['ProductID'] ?>"
                                                    class="cart-remove-button"
                                                    formnovalidate
                                                    aria-label="Xóa <?= e($item['ProductName']) ?>"
                                                    title="Xóa sản phẩm"
                                                >
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>
                        </div>

                        <div class="cart-update-area">
                            <button
                                type="submit"
                                name="update_cart"
                                class="btn-cart-update"
                            >
                                <i class="bi bi-arrow-repeat"></i>
                                Cập nhật giỏ hàng
                            </button>
                        </div>

                    </form>

                    <a href="/products.php" class="cart-continue-link">
                        <i class="bi bi-arrow-left"></i>
                        Tiếp tục mua sắm
                    </a>

                </section>

                <!-- Tổng kết đơn hàng -->
                <aside class="cart-summary">

                    <h2>Tổng đơn hàng</h2>

                    <div class="cart-summary-row">
                        <span>Số loại sản phẩm</span>
                        <strong><?= count($cartItems) ?></strong>
                    </div>

                    <div class="cart-summary-row">
                        <span>Tổng số lượng</span>
                        <strong><?= $totalQuantity ?></strong>
                    </div>

                    <div class="cart-summary-divider"></div>

                    <div class="cart-summary-total">
                        <span>Tổng thanh toán</span>
                        <strong>
                            <?= number_format($totalAmount, 0, ',', '.') ?> đ
                        </strong>
                    </div>

                    <p class="cart-summary-note">
                        Tổng tiền được tính dựa trên số lượng và đơn giá hiện tại của sản phẩm.
                    </p>

                    <a href="/checkout.php" class="btn-cart-checkout">
                        Tiến hành đặt hàng
                        <i class="bi bi-arrow-right"></i>
                    </a>

                    <div class="cart-summary-security">
                        <i class="bi bi-shield-check"></i>
                        <span>Kiểm tra thông tin trước khi xác nhận đặt hàng</span>
                    </div>

                </aside>

            </div>

        <?php endif; ?>

    </div>
</main>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
?>
