
<?php

// ============================================
// MỤC 9: KẾT NỐI DATABASE VÀ NHẬN PRODUCT ID
// ============================================

require_once '/var/www/src/config/database.php';

$productID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($productID <= 0) {
    header('Location: /products.php');
    exit;
}


// ============================================
// MỤC 10: TRUY VẤN THÔNG TIN CHI TIẾT SẢN PHẨM
// ============================================

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

    FROM
        products p,
        categories c,
        suppliers s

    WHERE
        p.CategoryID = c.CategoryID
        AND p.SupplierID = s.SupplierID
        AND p.ProductID = ?
        AND p.IsActive = 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Lỗi chuẩn bị truy vấn sản phẩm: ' . $conn->error);
}

$stmt->bind_param(
    'i',
    $productID
);

$stmt->execute();

$result = $stmt->get_result();

$product = $result->fetch_assoc();

$result->free();
$stmt->close();

if (!$product) {
    header('Location: /products.php');
    exit;
}


// ============================================
// MỤC 11: LẤY DANH SÁCH HÌNH ẢNH SẢN PHẨM
// ============================================

$sqlImages = "
    SELECT
        ProductImageID,
        ImageFile,
        IsPrimary

    FROM
        product_images

    WHERE
        ProductID = ?

    ORDER BY
        IsPrimary DESC,
        SortOrder ASC,
        ProductImageID ASC
";

$stmtImages = $conn->prepare($sqlImages);

if (!$stmtImages) {
    die('Lỗi chuẩn bị truy vấn hình ảnh: ' . $conn->error);
}

$stmtImages->bind_param(
    'i',
    $productID
);

$stmtImages->execute();

$imageResult = $stmtImages->get_result();

$images = [];

while ($image = $imageResult->fetch_assoc()) {
    $images[] = $image;
}

$imageResult->free();
$stmtImages->close();


// ============================================
// NẠP GIAO DIỆN FRONTEND
// ============================================

$pageTitle = $product['ProductName'];

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<!-- ==========================================
     MỤC 12: HIỂN THỊ CHI TIẾT SẢN PHẨM
=========================================== -->

<main class="container py-5">

    <div class="mb-4">

        <a
            href="/products.php"
            class="text-decoration-none"
        >
            &larr; Quay lại danh sách sản phẩm
        </a>

    </div>

    <div class="row g-5">

        <!-- KHU VỰC HÌNH ẢNH -->

        <div class="col-lg-6">

            <?php if (!empty($images)): ?>

                <!-- Ảnh chính -->

                <div
                    class="bg-light d-flex
                           align-items-center
                           justify-content-center
                           p-3 mb-3"
                    style="height: 420px;"
                >

                    <img
                        src="/uploads/products/<?= htmlspecialchars(
                            basename($images[0]['ImageFile']),
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

                <!-- Các ảnh phụ -->

                <?php if (count($images) > 1): ?>

                    <div class="row g-2">

                        <?php foreach ($images as $image): ?>

                            <div class="col-4">

                                <div
                                    class="border rounded
                                           bg-light
                                           d-flex
                                           align-items-center
                                           justify-content-center
                                           p-2"
                                    style="height: 120px;"
                                >

                                    <img
                                        src="/uploads/products/<?= htmlspecialchars(
                                            basename($image['ImageFile']),
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

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <div
                    class="bg-light d-flex
                           align-items-center
                           justify-content-center
                           text-muted"
                    style="height: 420px;"
                >
                    Chưa có hình ảnh
                </div>

            <?php endif; ?>

        </div>


        <!-- KHU VỰC THÔNG TIN SẢN PHẨM -->

        <div class="col-lg-6">

            <p class="text-muted mb-2">

                <?= htmlspecialchars(
                    $product['CategoryName'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </p>

            <h1 class="mb-3">

                <?= htmlspecialchars(
                    $product['ProductName'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </h1>

            <p class="text-muted">

                Mã sản phẩm:

                <?= htmlspecialchars(
                    $product['ProductCode'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </p>

            <p class="fs-3 fw-bold">

                <?= number_format(
                    (float) $product['Price'],
                    0,
                    ',',
                    '.'
                ) ?> đ

            </p>

            <hr>

            <dl class="row">

                <dt class="col-sm-4">
                    Danh mục
                </dt>

                <dd class="col-sm-8">

                    <?= htmlspecialchars(
                        $product['CategoryName'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </dd>


                <dt class="col-sm-4">
                    Nhà cung cấp
                </dt>

                <dd class="col-sm-8">

                    <?= htmlspecialchars(
                        $product['SupplierName'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </dd>


                <dt class="col-sm-4">
                    Đơn vị tính
                </dt>

                <dd class="col-sm-8">

                    <?= htmlspecialchars(
                        $product['Unit'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </dd>


                <dt class="col-sm-4">
                    Tồn kho
                </dt>

                <dd class="col-sm-8">

                    <?= (int) $product['StockQuantity'] ?>

                </dd>

            </dl>


            <!-- MÔ TẢ SẢN PHẨM -->

            <?php if (!empty($product['Description'])): ?>

                <hr>

                <h5>Mô tả sản phẩm</h5>

                <p>

                    <?= nl2br(
                        htmlspecialchars(
                            $product['Description'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ) ?>

                </p>

            <?php endif; ?>

        </div>

    </div>

</main>


<?php

// NẠP FOOTER FRONTEND

require_once '/var/www/src/includes/frontend/footer.php';

?>
