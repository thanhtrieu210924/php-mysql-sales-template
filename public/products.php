
<?php

require_once '/var/www/src/config/database.php';

/*
|--------------------------------------------------------------------------
| 1. LẤY DANH SÁCH DANH MỤC
|--------------------------------------------------------------------------
*/

$sqlCategories = "
    SELECT
        CategoryID,
        CategoryName
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
| 2. NHẬN THAM SỐ TÌM KIẾM TỪ GET
|--------------------------------------------------------------------------
*/

$categoryID = isset($_GET['category'])
    ? (int) $_GET['category']
    : 0;

$keyword = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

/*
|--------------------------------------------------------------------------
| 3. CẤU HÌNH PHÂN TRANG
|--------------------------------------------------------------------------
*/

$itemsPerPage = 6;

$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}

$searchKeyword = '%' . $keyword . '%';

/*
|--------------------------------------------------------------------------
| 4. ĐẾM TỔNG SỐ SẢN PHẨM PHÙ HỢP
|--------------------------------------------------------------------------
*/

$sqlCount = "
    SELECT
        COUNT(*) AS TotalProducts
    FROM
        products p,
        categories c
    WHERE
        p.CategoryID = c.CategoryID
        AND p.IsActive = 1
";

if ($categoryID > 0) {
    $sqlCount .= "
        AND p.CategoryID = ?
    ";
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

/*
|--------------------------------------------------------------------------
| 5. GẮN THAM SỐ CHO TRUY VẤN ĐẾM
|--------------------------------------------------------------------------
*/

if ($categoryID > 0 && $keyword !== '') {

    $stmtCount->bind_param(
        'iss',
        $categoryID,
        $searchKeyword,
        $searchKeyword
    );

} elseif ($categoryID > 0) {

    $stmtCount->bind_param(
        'i',
        $categoryID
    );

} elseif ($keyword !== '') {

    $stmtCount->bind_param(
        'ss',
        $searchKeyword,
        $searchKeyword
    );
}

/*
|--------------------------------------------------------------------------
| 6. THỰC THI TRUY VẤN ĐẾM
|--------------------------------------------------------------------------
*/

if (!$stmtCount->execute()) {
    die('Lỗi thực thi truy vấn đếm sản phẩm: ' . $stmtCount->error);
}

$countResult = $stmtCount->get_result();
$countRow = $countResult->fetch_assoc();

$totalProducts = (int) $countRow['TotalProducts'];

$countResult->free();
$stmtCount->close();

/*
|--------------------------------------------------------------------------
| 7. TÍNH TỔNG SỐ TRANG VÀ OFFSET
|--------------------------------------------------------------------------
*/

$totalPages = (int) ceil(
    $totalProducts / $itemsPerPage
);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $itemsPerPage;

/*
|--------------------------------------------------------------------------
| 8. XÂY DỰNG TRUY VẤN LẤY SẢN PHẨM
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

    FROM
        products p,
        categories c

    WHERE
        p.CategoryID = c.CategoryID
        AND p.IsActive = 1
";

/*
|--------------------------------------------------------------------------
| 8.1. ĐIỀU KIỆN LỌC DANH MỤC
|--------------------------------------------------------------------------
*/

if ($categoryID > 0) {
    $sql .= "
        AND p.CategoryID = ?
    ";
}

/*
|--------------------------------------------------------------------------
| 8.2. ĐIỀU KIỆN TÌM KIẾM TỪ KHÓA
|--------------------------------------------------------------------------
*/

if ($keyword !== '') {
    $sql .= "
        AND (
            p.ProductName LIKE ?
            OR p.ProductCode LIKE ?
        )
    ";
}

/*
|--------------------------------------------------------------------------
| 8.3. SẮP XẾP VÀ PHÂN TRANG
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        p.ProductID DESC
    LIMIT ?
    OFFSET ?
";

/*
|--------------------------------------------------------------------------
| 9. CHUẨN BỊ VÀ GẮN THAM SỐ TRUY VẤN SẢN PHẨM
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| 9.1. THỰC THI TRUY VẤN SẢN PHẨM
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {
    die('Lỗi thực thi truy vấn sản phẩm: ' . $stmt->error);
}

$result = $stmt->get_result();

if (!$result) {
    die('Lỗi lấy kết quả sản phẩm: ' . $stmt->error);
}

/*
|--------------------------------------------------------------------------
| 10. GIAO DIỆN
|--------------------------------------------------------------------------
*/

$pageTitle = 'Sản phẩm';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-5">

    <!-- Tiêu đề trang -->

    <div class="mb-4">

        <h1>Sản phẩm</h1>

        <p class="text-muted">
            Khám phá các sản phẩm hiện có tại cửa hàng.
        </p>

    </div>

    <!-- Form tìm kiếm và lọc danh mục -->

    <form
        method="get"
        action="/products.php"
        class="row g-3 mb-4"
    >

        <div class="col-md-6 col-lg-4">

            <label
                for="keyword"
                class="form-label"
            >
                Tìm sản phẩm
            </label>

            <input
                type="text"
                name="keyword"
                id="keyword"
                class="form-control"
                value="<?= htmlspecialchars(
                    $keyword,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                placeholder="Nhập tên hoặc mã sản phẩm"
            >

        </div>

        <div class="col-md-6 col-lg-4">

            <label
                for="category"
                class="form-label"
            >
                Danh mục
            </label>

            <select
                name="category"
                id="category"
                class="form-select"
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
                        <?= htmlspecialchars(
                            $category['CategoryName'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="col-md-auto align-self-end">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Tìm kiếm
            </button>

            <a
                href="/products.php"
                class="btn btn-outline-secondary"
            >
                Xóa bộ lọc
            </a>

        </div>

    </form>

    <!-- Số lượng sản phẩm tìm được -->

    <p class="text-muted">

        Tìm thấy
        <strong><?= $totalProducts ?></strong>
        sản phẩm.

    </p>

    <!-- Danh sách Card sản phẩm -->

    <?php if ($result->num_rows > 0): ?>

        <div class="row g-4">

            <?php while ($product = $result->fetch_assoc()): ?>

                <div class="col-md-6 col-lg-4">

                    <div class="card h-100">

                        <?php if (!empty($product['ImageFile'])): ?>

                            <div
                                class="bg-light d-flex
                                       align-items-center
                                       justify-content-center
                                       p-3"
                                style="height: 260px;"
                            >

                                <img
                                    src="/uploads/products/<?= htmlspecialchars(
                                        basename($product['ImageFile']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    alt="<?= htmlspecialchars(
                                        $product['ProductName'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    style="
                                        width: 100%;
                                        height: 100%;
                                        object-fit: contain;
                                    "
                                >

                            </div>

                        <?php else: ?>

                            <div
                                class="bg-light d-flex
                                       align-items-center
                                       justify-content-center
                                       text-muted"
                                style="height: 260px;"
                            >
                                Chưa có hình ảnh
                            </div>

                        <?php endif; ?>

                        <div class="card-body d-flex flex-column">

                            <p class="text-muted small mb-1">

                                <?= htmlspecialchars(
                                    $product['CategoryName'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </p>

                            <h5 class="card-title">

                                <?= htmlspecialchars(
                                    $product['ProductName'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </h5>

                            <p class="text-muted small">

                                Mã sản phẩm:

                                <?= htmlspecialchars(
                                    $product['ProductCode'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </p>

                            <p class="fw-bold fs-5 mb-3">

                                <?= number_format(
                                    (float) $product['Price'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ

                            </p>

                            <a
                                href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>"
                                class="btn btn-outline-primary mt-auto"
                            >
                                Xem chi tiết
                            </a>

                        </div>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="alert alert-info">

            Hiện chưa có sản phẩm nào để hiển thị.

        </div>

    <?php endif; ?>

    <!-- Phân trang -->

    <?php if ($totalPages > 1): ?>

        <nav
            class="mt-5"
            aria-label="Phân trang sản phẩm"
        >

            <ul
                class="pagination
                       justify-content-center
                       flex-wrap"
            >

                <!-- Nút Trước -->

                <?php

                $previousQuery = http_build_query([
                    'keyword' => $keyword,
                    'category' => $categoryID,
                    'page' => max(1, $page - 1)
                ]);

                ?>

                <li
                    class="page-item <?= $page <= 1
                        ? 'disabled'
                        : '' ?>"
                >

                    <a
                        class="page-link"
                        href="/products.php?<?= htmlspecialchars(
                            $previousQuery,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                        &laquo; Trước
                    </a>

                </li>

                <!-- Các số trang -->

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

                    <li
                        class="page-item <?= $pageNumber === $page
                            ? 'active'
                            : '' ?>"
                    >

                        <a
                            class="page-link"
                            href="/products.php?<?= htmlspecialchars(
                                $query,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            <?= $pageNumber ?>
                        </a>

                    </li>

                <?php endfor; ?>

                <!-- Nút Sau -->

                <?php

                $nextQuery = http_build_query([
                    'keyword' => $keyword,
                    'category' => $categoryID,
                    'page' => min(
                        $totalPages,
                        $page + 1
                    )
                ]);

                ?>

                <li
                    class="page-item <?= $page >= $totalPages
                        ? 'disabled'
                        : '' ?>"
                >

                    <a
                        class="page-link"
                        href="/products.php?<?= htmlspecialchars(
                            $nextQuery,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                        Sau &raquo;
                    </a>

                </li>

            </ul>

        </nav>

    <?php endif; ?>

</main>

<?php

/*
|--------------------------------------------------------------------------
| 11. GIẢI PHÓNG KẾT QUẢ VÀ ĐÓNG STATEMENT
|--------------------------------------------------------------------------
*/

$result->free();
$stmt->close();

require_once '/var/www/src/includes/frontend/footer.php';

?>
