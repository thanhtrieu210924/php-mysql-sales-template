<?php
require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Trang chủ | TechStore';

/*
|--------------------------------------------------------------------------
| Lấy danh sách danh mục
|--------------------------------------------------------------------------
*/

$categories = [];

$categorySql = "
    SELECT CategoryID, CategoryName
    FROM categories
    ORDER BY CategoryName ASC
";

$categoryResult = $conn->query($categorySql);

if ($categoryResult instanceof mysqli_result) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Lấy 6 sản phẩm mới nhất đang hoạt động
|--------------------------------------------------------------------------
*/

$products = [];

$productSql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
        p.CategoryID,
        c.CategoryName,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
              AND pi.IsPrimary = 1
            LIMIT 1
        ) AS ImageFile
    FROM products p
    LEFT JOIN categories c
        ON p.CategoryID = c.CategoryID
    WHERE p.IsActive = 1
    ORDER BY p.ProductID DESC
    LIMIT 6
";

$productResult = $conn->query($productSql);

if ($productResult instanceof mysqli_result) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Hàm bảo vệ dữ liệu khi hiển thị HTML
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Chọn biểu tượng cho danh mục
|--------------------------------------------------------------------------
*/

function getCategoryIcon($categoryName)
{
    $name = mb_strtolower((string) $categoryName, 'UTF-8');

    if (str_contains($name, 'laptop')) {
        return 'bi-laptop';
    }

    if (
        str_contains($name, 'điện thoại') ||
        str_contains($name, 'phone')
    ) {
        return 'bi-phone';
    }

    if (
        str_contains($name, 'phụ kiện') ||
        str_contains($name, 'accessory')
    ) {
        return 'bi-headphones';
    }

    if (
        str_contains($name, 'linh kiện') ||
        str_contains($name, 'component')
    ) {
        return 'bi-cpu';
    }

    return 'bi-grid';
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<main class="home-page">

    <!-- HERO BANNER -->
    <section class="home-hero">
        <div class="home-container">
            <div class="row align-items-center">

                <div class="col-lg-7">
                    <div class="hero-content">

                        <div class="hero-kicker">
                            <i class="bi bi-lightning-charge-fill"></i>
                            KHÁM PHÁ THẾ GIỚI CÔNG NGHỆ
                        </div>

                        <h1 class="hero-title">
                            Công nghệ mới,
                            <span>trải nghiệm khác biệt.</span>
                        </h1>

                        <p class="hero-description">
                            Khám phá các sản phẩm công nghệ, thiết bị điện tử
                            và phụ kiện phù hợp với nhu cầu học tập,
                            làm việc và giải trí của bạn.
                        </p>

                        <div class="hero-actions">
                            <a href="/products.php" class="btn-tech-primary">
                                Khám phá sản phẩm
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>

                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="hero-visual">
                        <div class="hero-device">
                            <i class="bi bi-laptop"></i>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>


    <!-- DANH MỤC SẢN PHẨM -->
    <section class="home-section">
        <div class="home-container">

            <div class="section-heading">
                <span class="section-kicker">Danh mục sản phẩm</span>

                <h2 class="section-title">
                    Khám phá theo danh mục
                </h2>

                <p class="section-description">
                    Tìm kiếm nhanh sản phẩm theo nhóm danh mục phù hợp.
                </p>
            </div>

            <div class="category-grid">

                <?php if (!empty($categories)): ?>

                    <?php foreach ($categories as $category): ?>

                        <a
                            href="/products.php?category=<?= (int) $category['CategoryID'] ?>"
                            class="category-card"
                        >

                            <div class="category-icon">
                                <i class="bi <?= e(getCategoryIcon($category['CategoryName'])) ?>"></i>
                            </div>

                            <div>
                                <h3 class="category-name">
                                    <?= e($category['CategoryName']) ?>
                                </h3>

                                <p class="category-subtitle">
                                    Khám phá sản phẩm
                                </p>
                            </div>

                        </a>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="product-empty">
                        <i class="bi bi-grid fs-1"></i>
                        <p class="mt-3 mb-0">
                            Hiện chưa có danh mục sản phẩm.
                        </p>
                    </div>

                <?php endif; ?>

            </div>

        </div>
    </section>


    <!-- SẢN PHẨM MỚI NHẤT -->
    <section class="home-section pt-0" id="featured-products">
        <div class="home-container">

            <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 section-heading">

                <div>
                    <span class="section-kicker">Lựa chọn dành cho bạn</span>

                    <h2 class="section-title">
                        Sản phẩm mới nhất
                    </h2>

                    <p class="section-description">
                        Những sản phẩm đang được giới thiệu trên hệ thống.
                    </p>
                </div>

                <a href="/products.php" class="section-view-all">
                    Xem tất cả
                    <i class="bi bi-arrow-right"></i>
                </a>

            </div>


            <div class="product-grid">

                <?php if (!empty($products)): ?>

                    <?php foreach ($products as $product): ?>

                        <?php
                        $imageFile = !empty($product['ImageFile'])
                            ? '/uploads/products/' . rawurlencode(basename($product['ImageFile']))
                            : null;
                        ?>

                        <article class="product-card">

                            <a
                                href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>"
                                class="product-image"
                            >

                                <?php if ($imageFile): ?>

                                    <img
                                        src="<?= e($imageFile) ?>"
                                        alt="<?= e($product['ProductName']) ?>"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <i class="bi bi-image fs-1 text-secondary"></i>

                                <?php endif; ?>

                                <span class="product-badge">
                                    Sản phẩm
                                </span>

                            </a>


                            <div class="product-info">

                                <div class="product-category">
                                    <?= e($product['CategoryName'] ?? 'Chưa phân loại') ?>
                                </div>

                                <h3 class="product-name">
                                    <a href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>">
                                        <?= e($product['ProductName']) ?>
                                    </a>
                                </h3>

                                <div class="product-price">
                                    <?= number_format((float) $product['Price'], 0, ',', '.') ?>₫
                                </div>

                                <div class="product-actions">
                                    <a
                                        href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>"
                                        class="product-detail-btn"
                                    >
                                        <i class="bi bi-eye"></i>
                                        Xem chi tiết
                                    </a>
                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="product-empty">
                        <i class="bi bi-box-seam fs-1"></i>

                        <h3 class="mt-3">
                            Chưa có sản phẩm
                        </h3>

                        <p class="mb-0">
                            Hiện chưa có sản phẩm nào được hiển thị.
                            Vui lòng quay lại sau.
                        </p>
                    </div>

                <?php endif; ?>

            </div>

        </div>
    </section>


    <!-- ĐIỂM NỔI BẬT -->
    <section class="features-section">
        <div class="home-container">

            <div class="features-grid">

                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="bi bi-grid"></i>
                    </div>

                    <div>
                        <h3 class="feature-title">
                            Đa dạng sản phẩm
                        </h3>

                        <p class="feature-description">
                            Nhiều nhóm sản phẩm công nghệ
                        </p>
                    </div>
                </div>


                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="bi bi-search"></i>
                    </div>

                    <div>
                        <h3 class="feature-title">
                            Tìm kiếm dễ dàng
                        </h3>

                        <p class="feature-description">
                            Hỗ trợ tìm và lọc sản phẩm
                        </p>
                    </div>
                </div>


                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="bi bi-cart3"></i>
                    </div>

                    <div>
                        <h3 class="feature-title">
                            Mua sắm tiện lợi
                        </h3>

                        <p class="feature-description">
                            Giỏ hàng và đặt hàng trực tuyến
                        </p>
                    </div>
                </div>


                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>

                    <div>
                        <h3 class="feature-title">
                            Quản lý đơn hàng
                        </h3>

                        <p class="feature-description">
                            Theo dõi trạng thái đơn hàng
                        </p>
                    </div>
                </div>

            </div>

        </div>
    </section>

</main>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
?>
