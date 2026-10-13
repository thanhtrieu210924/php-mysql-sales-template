<?php

$pageTitle = 'Sửa sản phẩm';

require_once '/var/www/src/config/database.php';

$error = '';
$productID = (int) ($_GET['id'] ?? 0);

if ($productID <= 0) {
    header('Location: /products/');
    exit;
}

// ========================================
// 1. LẤY THÔNG TIN SẢN PHẨM
// ========================================

$sqlProduct = "
    SELECT *
    FROM products
    WHERE ProductID = ?
";

$stmtProduct = $conn->prepare($sqlProduct);
$stmtProduct->bind_param('i', $productID);
$stmtProduct->execute();

$product = $stmtProduct->get_result()->fetch_assoc();
$stmtProduct->close();

if (!$product) {
    header('Location: /products/');
    exit;
}

// ========================================
// 2. XỬ LÝ CÁC YÊU CẦU POST
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ====================================
    // 2.1. ĐẶT ẢNH LÀM ẢNH CHÍNH
    // ====================================

    if (isset($_POST['set_primary_image'])) {

        $imageID = filter_var(
            $_POST['set_primary_image'],
            FILTER_VALIDATE_INT
        );

        if ($imageID === false || $imageID === null || $imageID <= 0) {
            $error = 'ID ảnh không hợp lệ.';
        } else {
            $transactionStarted = false;

            try {
                $conn->begin_transaction();
                $transactionStarted = true;

                $sqlResetPrimary = "
                    UPDATE product_images
                    SET IsPrimary = 0
                    WHERE ProductID = ?
                ";

                $stmtResetPrimary = $conn->prepare($sqlResetPrimary);
                $stmtResetPrimary->bind_param('i', $productID);
                $stmtResetPrimary->execute();
                $stmtResetPrimary->close();

                $sqlSetPrimary = "
                    UPDATE product_images
                    SET IsPrimary = 1
                    WHERE ProductImageID = ?
                      AND ProductID = ?
                ";

                $stmtSetPrimary = $conn->prepare($sqlSetPrimary);
                $stmtSetPrimary->bind_param(
                    'ii',
                    $imageID,
                    $productID
                );
                $stmtSetPrimary->execute();

                if ($stmtSetPrimary->affected_rows !== 1) {
                    $stmtSetPrimary->close();

                    throw new Exception(
                        'Không thể đặt ảnh chính. '
                        . 'Vui lòng kiểm tra ảnh được chọn.'
                    );
                }

                $stmtSetPrimary->close();

                $conn->commit();
                $transactionStarted = false;

                header(
                    'Location: /products/edit.php?id='
                    . $productID
                    . '&primary_updated=1'
                );
                exit;

            } catch (Throwable $e) {
                if ($transactionStarted) {
                    try {
                        $conn->rollback();
                    } catch (Throwable $rollbackError) {
                        // Giữ lại lỗi ban đầu.
                    }
                }

                error_log($e->getMessage());
                $error = 'Không thể cập nhật ảnh chính.';
            }
        }

    // ====================================
    // 2.2. XÓA MỘT ẢNH SẢN PHẨM
    // ====================================

    } elseif (isset($_POST['delete_image'])) {

        $imageID = filter_var(
            $_POST['delete_image'],
            FILTER_VALIDATE_INT
        );

        if (
            $imageID === false
            || $imageID === null
            || $imageID <= 0
        ) {
            $error = 'ID ảnh không hợp lệ.';

        } else {

            $transactionStarted = false;

            try {
                $conn->begin_transaction();
                $transactionStarted = true;

                // 1. ĐỌC THÔNG TIN ẢNH TRƯỚC KHI XÓA.
                // Chỉ xóa ảnh thuộc sản phẩm hiện tại.

                $sqlFindImage = "
                    SELECT
                        ImageFile,
                        IsPrimary,
                        SortOrder
                    FROM product_images
                    WHERE ProductImageID = ?
                      AND ProductID = ?
                ";

                $stmtFindImage = $conn->prepare($sqlFindImage);
                $stmtFindImage->bind_param(
                    'ii',
                    $imageID,
                    $productID
                );
                $stmtFindImage->execute();

                $imageToDelete = $stmtFindImage
                    ->get_result()
                    ->fetch_assoc();

                $stmtFindImage->close();

                if (!$imageToDelete) {
                    throw new Exception(
                        'Không tìm thấy ảnh cần xóa.'
                    );
                }

                // Lưu thông tin trước khi xóa bản ghi.
                $imageFile = basename($imageToDelete['ImageFile']);
                $wasPrimary = (int) $imageToDelete['IsPrimary'];

                // 2. XÓA BẢN GHI ẢNH TRONG MYSQL.

                $sqlDeleteImage = "
                    DELETE FROM product_images
                    WHERE ProductImageID = ?
                      AND ProductID = ?
                ";

                $stmtDeleteImage = $conn->prepare($sqlDeleteImage);
                $stmtDeleteImage->bind_param(
                    'ii',
                    $imageID,
                    $productID
                );
                $stmtDeleteImage->execute();

                if ($stmtDeleteImage->affected_rows !== 1) {
                    $stmtDeleteImage->close();

                    throw new Exception(
                        'Không thể xóa bản ghi ảnh.'
                    );
                }

                $stmtDeleteImage->close();

                // 3. NẾU XÓA ẢNH CHÍNH, CHỌN ẢNH CHÍNH MỚI.
                //
                // Đầu tiên, đặt tất cả ảnh còn lại thành ảnh phụ.
                // Sau đó dùng câu SQL có truy vấn con để chọn
                // ảnh có SortOrder nhỏ nhất làm ảnh chính.
                //
                // Nếu không còn ảnh, câu UPDATE thứ hai
                // không tác động bản ghi nào. Đây là trạng thái hợp lệ.

                if ($wasPrimary === 1) {

                    $sqlResetRemainingImages = "
                        UPDATE product_images
                        SET IsPrimary = 0
                        WHERE ProductID = ?
                    ";

                    $stmtResetRemainingImages = $conn->prepare(
                        $sqlResetRemainingImages
                    );

                    $stmtResetRemainingImages->bind_param(
                        'i',
                        $productID
                    );

                    $stmtResetRemainingImages->execute();
                    $stmtResetRemainingImages->close();

                    // BƯỚC 8: CHỌN ẢNH CÒN LẠI ĐẦU TIÊN
                    // LÀM ẢNH CHÍNH MỚI.

                    $sqlSetNewPrimary = "
                        UPDATE product_images
                        SET IsPrimary = 1
                        WHERE ProductImageID = (
                            SELECT ProductImageID
                            FROM (
                                SELECT ProductImageID
                                FROM product_images
                                WHERE ProductID = ?
                                ORDER BY SortOrder ASC, ProductImageID ASC
                                LIMIT 1
                            ) AS remaining_images
                        )
                    ";

                    $stmtSetNewPrimary = $conn->prepare(
                        $sqlSetNewPrimary
                    );

                    $stmtSetNewPrimary->bind_param(
                        'i',
                        $productID
                    );

                    $stmtSetNewPrimary->execute();
                    $stmtSetNewPrimary->close();
                }

                // 4. CHUẨN HÓA LẠI SORTORDER.
                // Sắp xếp ảnh còn lại theo thứ tự cũ,
                // sau đó đánh lại thứ tự từ 1.

                $sqlRemainingImages = "
                    SELECT ProductImageID
                    FROM product_images
                    WHERE ProductID = ?
                    ORDER BY SortOrder ASC, ProductImageID ASC
                ";

                $stmtRemainingImages = $conn->prepare(
                    $sqlRemainingImages
                );

                $stmtRemainingImages->bind_param(
                    'i',
                    $productID
                );

                $stmtRemainingImages->execute();

                $remainingImages = $stmtRemainingImages
                    ->get_result()
                    ->fetch_all(MYSQLI_ASSOC);

                $stmtRemainingImages->close();

                $sqlUpdateSortOrder = "
                    UPDATE product_images
                    SET SortOrder = ?
                    WHERE ProductImageID = ?
                      AND ProductID = ?
                ";

                $stmtUpdateSortOrder = $conn->prepare(
                    $sqlUpdateSortOrder
                );

                $sortOrder = 1;

                foreach ($remainingImages as $remainingImage) {

                    $remainingImageID = (int) $remainingImage[
                        'ProductImageID'
                    ];

                    $stmtUpdateSortOrder->bind_param(
                        'iii',
                        $sortOrder,
                        $remainingImageID,
                        $productID
                    );

                    $stmtUpdateSortOrder->execute();

                    $sortOrder++;
                }

                $stmtUpdateSortOrder->close();

                // 5. LƯU THAY ĐỔI MYSQL TRƯỚC KHI XÓA FILE.

                $conn->commit();
                $transactionStarted = false;

                // 6. XÓA FILE ẢNH VẬT LÝ TRONG DOCKER.

                $uploadDirectory = '/var/www/html/uploads/products/';
                $filePath = $uploadDirectory . $imageFile;

                if (is_file($filePath) && !unlink($filePath)) {
                    // Bản ghi đã xóa nhưng file vẫn còn.
                    header(
                        'Location: /products/edit.php?id='
                        . $productID
                        . '&image_delete_file_error=1'
                    );
                    exit;
                }

                header(
                    'Location: /products/edit.php?id='
                    . $productID
                    . '&image_deleted=1'
                );
                exit;

            } catch (Throwable $e) {

                if ($transactionStarted) {
                    try {
                        $conn->rollback();
                    } catch (Throwable $rollbackError) {
                        // Giữ lại lỗi ban đầu.
                    }
                }

                error_log(
                    'Lỗi xóa ảnh sản phẩm: '
                    . $e->getMessage()
                );

                $error = 'Không thể xóa ảnh. Vui lòng kiểm tra lại.';
            }
        }

    // ====================================
    // 2.3. THÊM NHIỀU ẢNH CHO SẢN PHẨM
    // ====================================

    } elseif (isset($_POST['add_images'])) {

        $files = $_FILES['product_images'] ?? null;

        if (
            !$files
            || !isset(
                $files['name'],
                $files['tmp_name'],
                $files['error'],
                $files['size']
            )
            || !is_array($files['name'])
        ) {
            $error = 'Vui lòng chọn ít nhất một ảnh.';

        } else {

            $maxSize = 2 * 1024 * 1024;

            $extensionMap = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            $validImages = [];
            $fileCount = count($files['name']);
            $finfo = new finfo(FILEINFO_MIME_TYPE);

            // Kiểm tra từng tập tin trước khi lưu.
            for ($i = 0; $i < $fileCount; $i++) {

                if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    $error = 'Có lỗi xảy ra khi upload ảnh.';
                    break;
                }

                if (!is_uploaded_file($files['tmp_name'][$i])) {
                    $error = 'Tập tin tải lên không hợp lệ.';
                    break;
                }

                if ($files['size'][$i] > $maxSize) {
                    $error = 'Mỗi ảnh chỉ được có kích thước '
                        . 'tối đa 2 MB.';
                    break;
                }

                $mimeType = $finfo->file($files['tmp_name'][$i]);

                if (!isset($extensionMap[$mimeType])) {
                    $error = 'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.';
                    break;
                }

                if (@getimagesize($files['tmp_name'][$i]) === false) {
                    $error = 'Tập tin không phải ảnh hợp lệ.';
                    break;
                }

                $fileName = 'product-'
                    . bin2hex(random_bytes(8))
                    . '.'
                    . $extensionMap[$mimeType];

                $validImages[] = [
                    'tmp_name' => $files['tmp_name'][$i],
                    'file_name' => $fileName
                ];
            }

            if ($error === '' && count($validImages) === 0) {
                $error = 'Vui lòng chọn ít nhất một ảnh.';
            }

            // Xác định thứ tự ảnh và trạng thái ảnh chính.
            if ($error === '') {

                $sqlImageState = "
                    SELECT
                        COUNT(*) AS ImageCount,
                        COALESCE(MAX(SortOrder), 0) AS MaxSortOrder
                    FROM product_images
                    WHERE ProductID = ?
                ";

                $stmtImageState = $conn->prepare($sqlImageState);
                $stmtImageState->bind_param('i', $productID);
                $stmtImageState->execute();

                $imageState = $stmtImageState
                    ->get_result()
                    ->fetch_assoc();

                $stmtImageState->close();

                $imageCount = (int) $imageState['ImageCount'];

                $nextSortOrder =
                    (int) $imageState['MaxSortOrder'] + 1;

                $movedFiles = [];
                $transactionStarted = false;
                $stmtInsertImage = null;

                try {
                    $uploadDirectory = '/var/www/html/uploads/products/';

                    if (!is_dir($uploadDirectory)) {
                        throw new Exception(
                            'Thư mục uploads/products chưa tồn tại.'
                        );
                    }

                    if (!is_writable($uploadDirectory)) {
                        throw new Exception(
                            'Thư mục uploads/products không có quyền ghi.'
                        );
                    }

                    $conn->begin_transaction();
                    $transactionStarted = true;

                    $sqlInsertImage = "
                        INSERT INTO product_images
                        (
                            ProductID,
                            ImageFile,
                            AltText,
                            IsPrimary,
                            SortOrder
                        )
                        VALUES (?, ?, ?, ?, ?)
                    ";

                    $stmtInsertImage = $conn->prepare($sqlInsertImage);

                    foreach ($validImages as $index => $image) {

                        $destination = $uploadDirectory
                            . $image['file_name'];

                        if (!move_uploaded_file(
                            $image['tmp_name'],
                            $destination
                        )) {
                            throw new Exception(
                                'Không thể lưu một trong các ảnh.'
                            );
                        }

                        $movedFiles[] = $destination;

                        $isPrimary = (
                            $imageCount === 0 && $index === 0
                        ) ? 1 : 0;

                        $sortOrder = $nextSortOrder + $index;

                        $altText = $product['ProductName']
                            . (
                                $isPrimary === 1
                                ? ' - ảnh chính'
                                : ' - ảnh ' . $sortOrder
                            );

                        $stmtInsertImage->bind_param(
                            'issii',
                            $productID,
                            $image['file_name'],
                            $altText,
                            $isPrimary,
                            $sortOrder
                        );

                        $stmtInsertImage->execute();
                    }

                    $stmtInsertImage->close();
                    $stmtInsertImage = null;

                    $conn->commit();
                    $transactionStarted = false;

                    header(
                        'Location: /products/edit.php?id='
                        . $productID
                        . '&images_added=1'
                    );
                    exit;

                } catch (Throwable $e) {

                    if ($stmtInsertImage !== null) {
                        $stmtInsertImage->close();
                    }

                    if ($transactionStarted) {
                        try {
                            $conn->rollback();
                        } catch (Throwable $rollbackError) {
                            // Tiếp tục dọn các tập tin đã di chuyển.
                        }
                    }

                    foreach ($movedFiles as $movedFile) {
                        if (is_file($movedFile)) {
                            @unlink($movedFile);
                        }
                    }

                    error_log(
                        'Lỗi thêm ảnh sản phẩm: '
                        . $e->getMessage()
                    );

                    $error = $e->getMessage();
                }
            }
        }

    // ====================================
    // 2.4. CẬP NHẬT THÔNG TIN SẢN PHẨM
    // ====================================

    } else {

        $productCode = trim($_POST['product_code'] ?? '');
        $productName = trim($_POST['product_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $unit = trim($_POST['unit'] ?? '');

        $price = filter_var(
            $_POST['price'] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        $stockQuantity = filter_var(
            $_POST['stock_quantity'] ?? null,
            FILTER_VALIDATE_INT
        );

        $categoryID = (int) ($_POST['category_id'] ?? 0);
        $supplierID = (int) ($_POST['supplier_id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($productCode === '') {
            $error = 'Mã sản phẩm không được để trống.';

        } elseif ($productName === '') {
            $error = 'Tên sản phẩm không được để trống.';

        } elseif (
            $price === false
            || $price === null
            || $price < 0
        ) {
            $error = 'Giá sản phẩm không hợp lệ.';

        } elseif (
            $stockQuantity === false
            || $stockQuantity === null
            || $stockQuantity < 0
        ) {
            $error = 'Số lượng tồn kho không hợp lệ.';

        } elseif ($categoryID <= 0) {
            $error = 'Vui lòng chọn danh mục.';

        } elseif ($supplierID <= 0) {
            $error = 'Vui lòng chọn nhà cung cấp.';

        } else {

            $sql = "
                UPDATE products
                SET
                    ProductCode = ?,
                    ProductName = ?,
                    Description = ?,
                    Unit = ?,
                    Price = ?,
                    StockQuantity = ?,
                    IsActive = ?,
                    SupplierID = ?,
                    CategoryID = ?
                WHERE ProductID = ?
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                'ssssdiiiii',
                $productCode,
                $productName,
                $description,
                $unit,
                $price,
                $stockQuantity,
                $isActive,
                $supplierID,
                $categoryID,
                $productID
            );

            try {
                if ($stmt->execute()) {
                    $stmt->close();

                    header('Location: /products/');
                    exit;
                }

                $error = 'Không thể cập nhật sản phẩm.';

            } catch (mysqli_sql_exception $e) {
                error_log($e->getMessage());

                $error = 'Cập nhật thất bại. '
                    . 'Hãy kiểm tra mã sản phẩm có bị trùng không.';
            }

            $stmt->close();
        }
    }
}

// ========================================
// 3. GIỮ LẠI LỰA CHỌN SAU KHI GỬI FORM
// ========================================

$selectedCategoryID = $_POST['category_id']
    ?? $product['CategoryID'];

$selectedSupplierID = $_POST['supplier_id']
    ?? $product['SupplierID'];

// ========================================
// 4. LẤY LẠI DANH SÁCH ẢNH
// ========================================

$sqlImages = "
    SELECT
        ProductImageID,
        ImageFile,
        AltText,
        IsPrimary,
        SortOrder
    FROM product_images
    WHERE ProductID = ?
    ORDER BY SortOrder, ProductImageID
";

$stmtImages = $conn->prepare($sqlImages);
$stmtImages->bind_param('i', $productID);
$stmtImages->execute();

$productImages = $stmtImages->get_result();

// ========================================
// 5. LẤY DANH SÁCH DANH MỤC
// ========================================

$sqlCategories = "
    SELECT CategoryID, CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categories = $conn->query($sqlCategories);

// ========================================
// 6. LẤY DANH SÁCH NHÀ CUNG CẤP
// ========================================

$sqlSuppliers = "
    SELECT SupplierID, SupplierName
    FROM suppliers
    ORDER BY SupplierName
";

$suppliers = $conn->query($sqlSuppliers);

// ========================================
// 7. HIỂN THỊ GIAO DIỆN
// ========================================

require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';
?>

<div class="container mt-4 mb-5">

    <h1 class="mb-4">Sửa sản phẩm</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (
        isset($_GET['primary_updated'])
        && $_GET['primary_updated'] === '1'
    ): ?>
        <div class="alert alert-success">
            Đã cập nhật ảnh chính.
        </div>
    <?php endif; ?>

    <?php if (
        isset($_GET['images_added'])
        && $_GET['images_added'] === '1'
    ): ?>
        <div class="alert alert-success">
            Đã thêm ảnh cho sản phẩm thành công.
        </div>
    <?php endif; ?>

    <?php if (
        isset($_GET['image_deleted'])
        && $_GET['image_deleted'] === '1'
    ): ?>
        <div class="alert alert-success">
            Đã xóa hình ảnh sản phẩm.
        </div>
    <?php endif; ?>

    <?php if (
        isset($_GET['image_delete_file_error'])
        && $_GET['image_delete_file_error'] === '1'
    ): ?>
        <div class="alert alert-warning">
            Bản ghi ảnh đã được xóa khỏi cơ sở dữ liệu,
            nhưng chưa xóa được file vật lý.
            Bà hãy kiểm tra thư mục uploads/products.
        </div>
    <?php endif; ?>

    <!-- ====================================
         8. HIỂN THỊ ẢNH HIỆN CÓ
         ==================================== -->

    <hr class="my-4">

    <h4 class="mb-3">Hình ảnh sản phẩm</h4>

    <div class="row">

        <?php if ($productImages->num_rows === 0): ?>

            <div class="col-12">
                <div class="alert alert-warning">
                    Sản phẩm chưa có hình ảnh.
                </div>
            </div>

        <?php else: ?>

            <?php while ($image = $productImages->fetch_assoc()): ?>

                <div class="col-md-3 mb-3">

                    <div class="card h-100">

                        <img
                            src="/uploads/products/<?= rawurlencode(
                                basename($image['ImageFile'])
                            ) ?>"
                            class="card-img-top"
                            alt="<?= htmlspecialchars(
                                $image['AltText'] ?? ''
                            ) ?>"
                            style="height: 200px; object-fit: contain;"
                        >

                        <div class="card-body">

                            <small class="text-muted">
                                Thứ tự:
                                <?= (int) $image['SortOrder'] ?>
                            </small>

                            <?php if ((int) $image['IsPrimary'] === 1): ?>

                                <div class="mt-2">
                                    <span class="badge bg-success">
                                        Ảnh chính
                                    </span>
                                </div>

                            <?php else: ?>

                                <div class="mt-2">
                                    <span class="badge bg-secondary">
                                        Ảnh phụ
                                    </span>
                                </div>

                                <form
                                    method="POST"
                                    action="/products/edit.php?id=<?= $productID ?>"
                                    class="mt-3"
                                >
                                    <button
                                        type="submit"
                                        class="btn btn-outline-primary btn-sm"
                                        name="set_primary_image"
                                        value="<?= (int) $image['ProductImageID'] ?>"
                                    >
                                        Đặt làm ảnh chính
                                    </button>
                                </form>

                            <?php endif; ?>

                            <!-- XÓA ẢNH CHÍNH HOẶC ẢNH PHỤ -->

                            <form
                                method="POST"
                                action="/products/edit.php?id=<?= $productID ?>"
                                class="mt-2"
                                onsubmit="return confirm('Bạn có chắc muốn xóa ảnh này?');"
                            >
                                <button
                                    type="submit"
                                    class="btn btn-outline-danger btn-sm"
                                    name="delete_image"
                                    value="<?= (int) $image['ProductImageID'] ?>"
                                >
                                    Xóa ảnh
                                </button>
                            </form>

                        </div>
                    </div>

                </div>

            <?php endwhile; ?>

        <?php endif; ?>

    </div>

    <!-- ====================================
         9. FORM THÊM NHIỀU ẢNH
         ==================================== -->

    <hr class="my-4">

    <h4 class="mb-3">Thêm hình ảnh sản phẩm</h4>

    <form
        method="POST"
        action="/products/edit.php?id=<?= $productID ?>"
        enctype="multipart/form-data"
    >

        <div class="mb-3">
            <label for="productImages" class="form-label">
                Chọn hình ảnh
            </label>

            <input
                type="file"
                class="form-control"
                id="productImages"
                name="product_images[]"
                accept="image/jpeg,image/png,image/webp"
                multiple
            >

            <div class="form-text">
                Chấp nhận JPG, PNG hoặc WebP.
                Mỗi ảnh tối đa 2 MB.
            </div>
        </div>

        <button
            type="submit"
            class="btn btn-outline-success"
            name="add_images"
            value="1"
        >
            Thêm ảnh
        </button>

    </form>

    <!-- ====================================
         10. FORM SỬA THÔNG TIN SẢN PHẨM
         ==================================== -->

    <hr class="my-4">

    <h4 class="mb-3">Thông tin sản phẩm</h4>

    <form
        method="POST"
        action="/products/edit.php?id=<?= $productID ?>"
    >

        <div class="mb-3">
            <label for="product_code" class="form-label">
                Mã sản phẩm
            </label>

            <input
                type="text"
                class="form-control"
                id="product_code"
                name="product_code"
                value="<?= htmlspecialchars(
                    $_POST['product_code']
                    ?? $product['ProductCode']
                ) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="product_name" class="form-label">
                Tên sản phẩm
            </label>

            <input
                type="text"
                class="form-control"
                id="product_name"
                name="product_name"
                value="<?= htmlspecialchars(
                    $_POST['product_name']
                    ?? $product['ProductName']
                ) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">
                Mô tả
            </label>

            <textarea
                class="form-control"
                id="description"
                name="description"
                rows="3"
            ><?= htmlspecialchars(
                $_POST['description']
                ?? $product['Description']
                ?? ''
            ) ?></textarea>
        </div>

        <div class="mb-3">
            <label for="unit" class="form-label">
                Đơn vị tính
            </label>

            <input
                type="text"
                class="form-control"
                id="unit"
                name="unit"
                value="<?= htmlspecialchars(
                    $_POST['unit']
                    ?? $product['Unit']
                    ?? ''
                ) ?>"
            >
        </div>

        <div class="mb-3">
            <label for="price" class="form-label">
                Giá
            </label>

            <input
                type="number"
                class="form-control"
                id="price"
                name="price"
                min="0"
                step="0.01"
                value="<?= htmlspecialchars(
                    (string) (
                        $_POST['price']
                        ?? $product['Price']
                    )
                ) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="stock_quantity" class="form-label">
                Số lượng tồn kho
            </label>

            <input
                type="number"
                class="form-control"
                id="stock_quantity"
                name="stock_quantity"
                min="0"
                value="<?= htmlspecialchars(
                    (string) (
                        $_POST['stock_quantity']
                        ?? $product['StockQuantity']
                    )
                ) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="category_id" class="form-label">
                Danh mục
            </label>

            <select
                class="form-select"
                id="category_id"
                name="category_id"
                required
            >
                <option value="">-- Chọn danh mục --</option>

                <?php while ($category = $categories->fetch_assoc()): ?>

                    <option
                        value="<?= (int) $category['CategoryID'] ?>"
                        <?= (string) $selectedCategoryID
                            === (string) $category['CategoryID']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= htmlspecialchars($category['CategoryName']) ?>
                    </option>

                <?php endwhile; ?>

            </select>
        </div>

        <div class="mb-3">
            <label for="supplier_id" class="form-label">
                Nhà cung cấp
            </label>

            <select
                class="form-select"
                id="supplier_id"
                name="supplier_id"
                required
            >
                <option value="">-- Chọn nhà cung cấp --</option>

                <?php while ($supplier = $suppliers->fetch_assoc()): ?>

                    <option
                        value="<?= (int) $supplier['SupplierID'] ?>"
                        <?= (string) $selectedSupplierID
                            === (string) $supplier['SupplierID']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= htmlspecialchars($supplier['SupplierName']) ?>
                    </option>

                <?php endwhile; ?>

            </select>
        </div>

        <div class="form-check mb-3">

            <input
                type="checkbox"
                class="form-check-input"
                id="is_active"
                name="is_active"
                value="1"
                <?= isset($_POST['is_active'])
                    ? 'checked'
                    : (
                        (int) $product['IsActive'] === 1
                        ? 'checked'
                        : ''
                    ) ?>
            >

            <label class="form-check-label" for="is_active">
                Đang bán
            </label>

        </div>

        <button type="submit" class="btn btn-primary">
            Lưu thay đổi
        </button>

        <a href="/products/" class="btn btn-secondary">
            Hủy
        </a>

    </form>

</div>

<?php
$stmtImages->close();

require_once '/var/www/src/includes/footer.php';
?>
```