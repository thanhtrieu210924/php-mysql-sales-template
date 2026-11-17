<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

/*
|--------------------------------------------------------------------------
| 1. HÀM HỖ TRỢ
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| 2. LẤY DANH SÁCH DANH MỤC
|--------------------------------------------------------------------------
*/

$sqlCategories = "
    SELECT CategoryID, CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categoryResult = $conn->query($sqlCategories);

if (!$categoryResult) {
    die('Lỗi truy vấn danh mục: ' . $conn->error);
}

$categories = [];

while ($category = $categoryResult->fetch_assoc()) {
    $categories[] = $category;
}

$categoryResult->free();

/*
|--------------------------------------------------------------------------
| 3. NHẬN THAM SỐ TÌM KIẾM
|--------------------------------------------------------------------------
*/

$categoryID = isset($_GET['category'])
    ? max(0, (int) $_GET['category'])
    : 0;

$keyword = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

$searchKeyword = '%' . $keyword . '%';

/*
|--------------------------------------------------------------------------
| 4. CẤU HÌNH PHÂN TRANG
|--------------------------------------------------------------------------
*/

$itemsPerPage = 6;

$page = isset($_GET['page'])
    ? max(1, (int) $_GET['page'])
    : 1;

/*
|--------------------------------------------------------------------------
| 5. ĐẾM TỔNG SỐ SẢN PHẨM
|--------------------------------------------------------------------------
*/

$sqlCount = "
    SELECT COUNT(*) AS TotalProducts
    FROM products p
    INNER JOIN categories c
        ON p.CategoryID = c.CategoryID
    WHERE p.IsActive = 1
";

if ($categoryID > 0) {
    $sqlCount .= " AND p.CategoryID = ?";
}

if ($keyword !== '') {
    $sqlCount .= "
        AND (
            p.ProductName LIKE ?
            OR p.ProductCode LIKE ?
        )
    ";
}

$stmtCount = $conn->prepare($sqlCount);

if (!$stmtCount) {
    die('Lỗi chuẩn bị truy vấn đếm sản phẩm: ' . $conn->error);
}

if ($categoryID > 0 && $keyword !== '') {
    $stmtCount->bind_param(
        'iss',
        $categoryID,
        $searchKeyword,
        $searchKeyword
    );
} elseif ($categoryID > 0) {
    $stmtCount->bind_param('i', $categoryID);
} elseif ($keyword !== '') {
    $stmtCount->bind_param(
        'ss',
        $searchKeyword,
        $searchKeyword
    );
}

if (!$stmtCount->execute()) {
    die('Lỗi đếm sản phẩm: ' . $stmtCount->error);
}

$countResult = $stmtCount->get_result();
$countRow = $countResult->fetch_assoc();

$totalProducts = (int) $countRow['TotalProducts'];

$countResult->free();
$stmtCount->close();

/*
|--------------------------------------------------------------------------
| 6. TÍNH PHÂN TRANG
|--------------------------------------------------------------------------
*/

$totalPages = (int) ceil($totalProducts / $itemsPerPage);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $itemsPerPage;

/*
|--------------------------------------------------------------------------
| 7. TRUY VẤN DANH SÁCH SẢN PHẨM
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
        c.CategoryName,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
              AND pi.IsPrimary = 1
            LIMIT 1
        ) AS ImageFile
    FROM products p
    INNER JOIN categories c
        ON p.CategoryID = c.CategoryID
    WHERE p.IsActive = 1
";

if ($categoryID > 0) {
    $sql .= " AND p.CategoryID = ?";
}

if ($keyword !== '') {
    $sql .= "
        AND (
            p.ProductName LIKE ?
            OR p.ProductCode LIKE ?
        )
    ";
}

$sql .= "
    ORDER BY p.ProductID DESC
    LIMIT ?
    OFFSET ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Lỗi chuẩn bị truy vấn sản phẩm: ' . $conn->error);
}

if ($categoryID > 0 && $keyword !== '') {
    $stmt->bind_param(
        'issii',
        $categoryID,
        $searchKeyword,
        $searchKeyword,
        $itemsPerPage,
        $offset
    );
} elseif ($categoryID > 0) {
    $stmt->bind_param(
        'iii',
        $categoryID,
        $itemsPerPage,
        $offset
    );
} elseif ($keyword !== '') {
    $stmt->bind_param(
        'ssii',
        $searchKeyword,
        $searchKeyword,
        $itemsPerPage,
        $offset
    );
} else {
    $stmt->bind_param(
        'ii',
        $itemsPerPage,
        $offset
    );
}

if (!$stmt->execute()) {
    die('Lỗi thực thi truy vấn sản phẩm: ' . $stmt->error);
}

$result = $stmt->get_result();

if (!$result) {
    die('Lỗi lấy kết quả sản phẩm: ' . $stmt->error);
}

/*
|--------------------------------------------------------------------------
| 8. TIÊU ĐỀ VÀ HEADER
|--------------------------------------------------------------------------
*/

$pageTitle = 'Sản phẩm | TechStore';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="products-page">

    <!-- PAGE HERO -->
    <section class="products-hero">
        <div class="home-container">

            <div class="products-hero-content">

                <span class="section-kicker">
                    TECHSTORE COLLECTION
                </span>

                <h1 class="products-hero-title">
                    Khám phá thế giới
                    <span>công nghệ</span>
                </h1>

                <p>
                    Tìm kiếm và lựa chọn những sản phẩm phù hợp
                    với nhu cầu học tập, làm việc và giải trí.
                </p>

                <div class="products-breadcrumb">
                    <a href="/">Trang chủ</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>Sản phẩm</span>
                </div>

            </div>

        </div>
    </section>


    <!-- PRODUCT CONTENT -->
    <section class="home-section">
        <div class="home-container">

            <!-- HEADING -->
            <div class="section-heading">

                <span class="section-kicker">
                    DANH MỤC SẢN PHẨM
                </span>

                <h2 class="section-title">
                    Tất cả sản phẩm
                </h2>

                <p class="section-description">
                    Lựa chọn sản phẩm phù hợp với nhu cầu của bạn.
                </p>

            </div>


            <!-- FILTER FORM -->
            <div class="products-filter-card">

                <form
                    method="get"
                    action="/products.php"
                    class="row g-3 align-items-end"
                >

                    <div class="col-lg-5 col-md-6">

                        <label for="keyword" class="filter-label">
                            <i class="bi bi-search"></i>
                            Tìm kiếm sản phẩm
                        </label>

                        <input
                            type="text"
                            name="keyword"
                            id="keyword"
                            class="form-control tech-input"
                            value="<?= e($keyword) ?>"
                            placeholder="Nhập tên hoặc mã sản phẩm..."
                        >

                    </div>


                    <div class="col-lg-4 col-md-6">

                        <label for="category" class="filter-label">
                            <i class="bi bi-grid"></i>
                            Danh mục sản phẩm
                        </label>

                        <select
                            name="category"
                            id="category"
                            class="form-select tech-input"
                        >

                            <option value="0">
                                Tất cả danh mục
                            </option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= (int) $category['CategoryID'] ?>"
                                    <?= $categoryID === (int) $category['CategoryID']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= e($category['CategoryName']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-lg-3 col-md-12">

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn-tech-search"
                            >
                                <i class="bi bi-search"></i>
                                Tìm kiếm
                            </button>

                            <a
                                href="/products.php"
                                class="btn-tech-reset"
                                title="Xóa bộ lọc"
                            >
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>

                        </div>

                    </div>

                </form>

            </div>


            <!-- PRODUCT RESULTS HEADER -->
            <div class="products-results-header">

                <div>
                    <h3>
                        Kết quả sản phẩm
                    </h3>

                    <p>
                        Tìm thấy
                        <strong><?= $totalProducts ?></strong>
                        sản phẩm
                        <?php if ($keyword !== ''): ?>
                            cho từ khóa
                            <strong>"<?= e($keyword) ?>"</strong>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="products-sort-label">
                    <i class="bi bi-clock-history"></i>
                    Sản phẩm mới nhất
                </div>

            </div>


            <!-- PRODUCT GRID -->
            <?php if ($result->num_rows > 0): ?>

                <div class="product-grid">

                    <?php while ($product = $result->fetch_assoc()): ?>

                        <?php
                        $imageFile = !empty($product['ImageFile'])
                            ? '/uploads/products/' . rawurlencode(
                                basename($product['ImageFile'])
                            )
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

                                    <div class="product-no-image">
                                        <i class="bi bi-image"></i>
                                        <span>Chưa có hình ảnh</span>
                                    </div>

                                <?php endif; ?>

                                <span class="product-badge">
                                    <?= e($product['CategoryName']) ?>
                                </span>

                            </a>


                            <div class="product-info">

                                <div class="product-category">
                                    <?= e($product['ProductCode']) ?>
                                </div>

                                <h3 class="product-name">
                                    <a href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>">
                                        <?= e($product['ProductName']) ?>
                                    </a>
                                </h3>

                                <div class="product-price">
                                    <?= number_format(
                                        (float) $product['Price'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>₫
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

                    <?php endwhile; ?>

                </div>

            <?php else: ?>

                <div class="products-empty-state">

                    <div class="products-empty-icon">
                        <i class="bi bi-search"></i>
                    </div>

                    <h3>
                        Không tìm thấy sản phẩm
                    </h3>

                    <p>
                        Không có sản phẩm phù hợp với thông tin tìm kiếm.
                        Ní hãy thử từ khóa hoặc danh mục khác nhé.
                    </p>

                    <a href="/products.php" class="btn-tech-primary">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Xem tất cả sản phẩm
                    </a>

                </div>

            <?php endif; ?>


            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>

                <nav
                    class="tech-pagination"
                    aria-label="Phân trang sản phẩm"
                >

                    <ul class="pagination justify-content-center flex-wrap">

                        <?php
                        $previousQuery = http_build_query([
                            'keyword' => $keyword,
                            'category' => $categoryID,
                            'page' => max(1, $page - 1)
                        ]);
                        ?>

                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                            <a
                                class="page-link"
                                href="/products.php?<?= e($previousQuery) ?>"
                                aria-label="Trang trước"
                            >
                                <i class="bi bi-chevron-left"></i>
                            </a>

                        </li>


                        <?php for (
                            $pageNumber = 1;
                            $pageNumber <= $totalPages;
                            $pageNumber++
                        ): ?>

                            <?php
                            $query = http_build_query([
                                'keyword' => $keyword,
                                'category' => $categoryID,
                                'page' => $pageNumber
                            ]);
                            ?>

                            <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>">

                                <a
                                    class="page-link"
                                    href="/products.php?<?= e($query) ?>"
                                >
                                    <?= $pageNumber ?>
                                </a>

                            </li>

                        <?php endfor; ?>


                        <?php
                        $nextQuery = http_build_query([
                            'keyword' => $keyword,
                            'category' => $categoryID,
                            'page' => min($totalPages, $page + 1)
                        ]);
                        ?>

                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">

                            <a
                                class="page-link"
                                href="/products.php?<?= e($nextQuery) ?>"
                                aria-label="Trang sau"
                            >
                                <i class="bi bi-chevron-right"></i>
                            </a>

                        </li>

                    </ul>

                </nav>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php

$result->free();
$stmt->close();

require_once '/var/www/src/includes/frontend/footer.php';

?>
