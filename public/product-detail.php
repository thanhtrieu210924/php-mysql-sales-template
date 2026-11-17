<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Lấy mã sản phẩm
$productID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($productID <= 0) {
    header('Location: /products.php');
    exit;
}

// Lấy thông tin sản phẩm
$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Description,
        p.Unit,
        p.Price,
        p.StockQuantity,
        c.CategoryName,
        s.SupplierName
    FROM products p
    INNER JOIN categories c ON p.CategoryID = c.CategoryID
    INNER JOIN suppliers s ON p.SupplierID = s.SupplierID
    WHERE p.ProductID = ?
      AND p.IsActive = 1
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Lỗi chuẩn bị truy vấn sản phẩm: ' . $conn->error);
}

$stmt->bind_param('i', $productID);

if (!$stmt->execute()) {
    die('Lỗi truy vấn sản phẩm: ' . $stmt->error);
}

$result = $stmt->get_result();
$product = $result->fetch_assoc();

$result->free();
$stmt->close();

if (!$product) {
    header('Location: /products.php');
    exit;
}

// Xử lý thêm sản phẩm vào giỏ hàng
$cartMessage = '';
$cartMessageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 1;
    $stockQuantity = (int) $product['StockQuantity'];

    if ($quantity < 1) {
        $quantity = 1;
    }

    if ($stockQuantity <= 0) {
        $cartMessage = 'Sản phẩm hiện đã hết hàng.';
        $cartMessageType = 'warning';
    } else {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $currentQuantity = (int) ($_SESSION['cart'][$productID] ?? 0);
        $newQuantity = $currentQuantity + $quantity;

        if ($newQuantity > $stockQuantity) {
            $newQuantity = $stockQuantity;
        }

        $_SESSION['cart'][$productID] = $newQuantity;

        $cartMessage = 'Đã thêm sản phẩm vào giỏ hàng.';
        $cartMessageType = 'success';
    }
}

// Lấy danh sách hình ảnh sản phẩm
$sqlImages = "
    SELECT
        ProductImageID,
        ImageFile,
        IsPrimary
    FROM product_images
    WHERE ProductID = ?
    ORDER BY IsPrimary DESC, SortOrder ASC, ProductImageID ASC
";

$stmtImages = $conn->prepare($sqlImages);

if (!$stmtImages) {
    die('Lỗi chuẩn bị truy vấn hình ảnh: ' . $conn->error);
}

$stmtImages->bind_param('i', $productID);

if (!$stmtImages->execute()) {
    die('Lỗi truy vấn hình ảnh: ' . $stmtImages->error);
}

$imageResult = $stmtImages->get_result();
$images = [];

while ($image = $imageResult->fetch_assoc()) {
    $images[] = $image;
}

$imageResult->free();
$stmtImages->close();

$pageTitle = $product['ProductName'];

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="product-detail-page">
    <div class="home-container">

        <!-- Breadcrumb -->
        <div class="detail-breadcrumb">
            <a href="/">Trang chủ</a>
            <span>/</span>
            <a href="/products.php">Sản phẩm</a>
            <span>/</span>
            <span><?= e($product['ProductName']) ?></span>
        </div>

        <div class="detail-layout">

            <!-- Khu vực hình ảnh -->
            <section class="detail-gallery">

                <?php if (!empty($images)): ?>

                    <div class="detail-main-image">
                        <img
                            src="/uploads/products/<?= rawurlencode(basename($images[0]['ImageFile'])) ?>"
                            alt="<?= e($product['ProductName']) ?>"
                        >
                    </div>

                    <?php if (count($images) > 1): ?>
                        <div class="detail-thumbnail-grid">
                            <?php foreach ($images as $index => $image): ?>
                                <div class="detail-thumbnail">
                                    <img
                                        src="/uploads/products/<?= rawurlencode(basename($image['ImageFile'])) ?>"
                                        alt="<?= e($product['ProductName']) ?> - Ảnh <?= $index + 1 ?>"
                                        loading="lazy"
                                    >
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>

                    <div class="detail-no-image">
                        <i class="bi bi-image"></i>
                        <span>Chưa có hình ảnh sản phẩm</span>
                    </div>

                <?php endif; ?>

            </section>

            <!-- Khu vực thông tin sản phẩm -->
            <section class="detail-information">

                <div class="detail-category">
                    <i class="bi bi-grid"></i>
                    <?= e($product['CategoryName']) ?>
                </div>

                <h1 class="detail-product-name">
                    <?= e($product['ProductName']) ?>
                </h1>

                <p class="detail-product-code">
                    Mã sản phẩm:
                    <strong><?= e($product['ProductCode']) ?></strong>
                </p>

                <div class="detail-price">
                    <?= number_format((float) $product['Price'], 0, ',', '.') ?> đ
                </div>

                <div class="detail-stock">
                    <?php if ((int) $product['StockQuantity'] > 0): ?>
                        <span class="stock-available">
                            <i class="bi bi-check-circle-fill"></i>
                            Còn hàng
                        </span>
                    <?php else: ?>
                        <span class="stock-unavailable">
                            <i class="bi bi-x-circle-fill"></i>
                            Hết hàng
                        </span>
                    <?php endif; ?>

                    <span class="stock-quantity">
                        Số lượng tồn: <?= (int) $product['StockQuantity'] ?>
                    </span>
                </div>

                <div class="detail-divider"></div>

                <!-- Thông tin cơ bản -->
                <div class="detail-specifications">
                    <div class="specification-item">
                        <span class="specification-label">Danh mục</span>
                        <span class="specification-value">
                            <?= e($product['CategoryName']) ?>
                        </span>
                    </div>

                    <div class="specification-item">
                        <span class="specification-label">Nhà cung cấp</span>
                        <span class="specification-value">
                            <?= e($product['SupplierName']) ?>
                        </span>
                    </div>

                    <div class="specification-item">
                        <span class="specification-label">Đơn vị tính</span>
                        <span class="specification-value">
                            <?= e($product['Unit'] ?? '') ?>
                        </span>
                    </div>
                </div>

                <!-- Thông báo thêm giỏ hàng -->
                <?php if ($cartMessage !== ''): ?>
                    <div class="alert alert-<?= e($cartMessageType) ?> detail-cart-alert" role="alert">
                        <?= e($cartMessage) ?>

                        <?php if ($cartMessageType === 'success'): ?>
                            <a href="/cart.php" class="alert-link ms-1">
                                Xem giỏ hàng
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Form thêm vào giỏ hàng -->
                <?php if ((int) $product['StockQuantity'] > 0): ?>

                    <form method="post" class="detail-add-cart-form">

                        <label for="quantity" class="detail-quantity-label">
                            Số lượng
                        </label>

                        <div class="detail-purchase-actions">
                            <input
                                type="number"
                                name="quantity"
                                id="quantity"
                                class="detail-quantity-input"
                                value="1"
                                min="1"
                                max="<?= (int) $product['StockQuantity'] ?>"
                                required
                            >

                            <button
                                type="submit"
                                name="add_to_cart"
                                class="btn-tech-primary detail-add-cart-button"
                            >
                                <i class="bi bi-cart-plus"></i>
                                Thêm vào giỏ hàng
                            </button>
                        </div>

                    </form>

                <?php else: ?>

                    <button type="button" class="btn-tech-disabled" disabled>
                        <i class="bi bi-cart-x"></i>
                        Sản phẩm đã hết hàng
                    </button>

                <?php endif; ?>

                <a href="/products.php" class="detail-back-link">
                    <i class="bi bi-arrow-left"></i>
                    Tiếp tục xem sản phẩm
                </a>

            </section>

        </div>

        <!-- Mô tả sản phẩm -->
        <?php if (!empty($product['Description'])): ?>
            <section class="detail-description-section">
                <div class="detail-section-heading">
                    <span class="section-kicker">THÔNG TIN SẢN PHẨM</span>
                    <h2>Mô tả sản phẩm</h2>
                </div>

                <div class="detail-description-content">
                    <?= nl2br(e($product['Description'])) ?>
                </div>
            </section>
        <?php endif; ?>

    </div>
</main>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
?>
