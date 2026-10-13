<?php

$pageTitle = 'Sửa sản phẩm';

require_once '/var/www/src/config/database.php';

$error = '';

$productID = (int) ($_GET['id'] ?? 0);

if ($productID <= 0) {
    header('Location: /products/');
    exit;
}

// Lấy thông tin sản phẩm hiện tại
$sqlProduct = "
    SELECT *
    FROM products
    WHERE ProductID = ?
";

$stmtProduct = $conn->prepare($sqlProduct);
$stmtProduct->bind_param('i', $productID);
$stmtProduct->execute();

$resultProduct = $stmtProduct->get_result();
$product = $resultProduct->fetch_assoc();

$stmtProduct->close();

if (!$product) {
    header('Location: /products/');
    exit;
}

// Lấy danh sách danh mục
$sqlCategories = "
    SELECT CategoryID, CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categories = $conn->query($sqlCategories);

// Lấy danh sách nhà cung cấp
$sqlSuppliers = "
    SELECT SupplierID, SupplierName
    FROM suppliers
    ORDER BY SupplierName
";

$suppliers = $conn->query($sqlSuppliers);

// Xử lý cập nhật khi gửi form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productCode = trim($_POST['product_code'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');

    $price = (float) ($_POST['price'] ?? 0);
    $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);

    $categoryID = (int) ($_POST['category_id'] ?? 0);
    $supplierID = (int) ($_POST['supplier_id'] ?? 0);

    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($productCode === '') {
        $error = 'Mã sản phẩm không được để trống.';

    } elseif ($productName === '') {
        $error = 'Tên sản phẩm không được để trống.';

    } elseif ($price < 0) {
        $error = 'Giá sản phẩm không hợp lệ.';

    } elseif ($stockQuantity < 0) {
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

        if ($stmt->execute()) {
            header('Location: /products/');
            exit;
        }

        $error = 'Không thể cập nhật sản phẩm.';
        $stmt->close();
    }
}

// Giữ lại lựa chọn sau khi gửi form
$selectedCategoryID =
    $_POST['category_id']
    ?? $product['CategoryID'];

$selectedSupplierID =
    $_POST['supplier_id']
    ?? $product['SupplierID'];

require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';
?>

<div class="container mt-4">
    <h1>Sửa sản phẩm</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="product_code" class="form-label">
                Mã sản phẩm
            </label>
            <input
                type="text"
                class="form-control"
                id="product_code"
                name="product_code"
                value="<?= htmlspecialchars($_POST['product_code'] ?? $product['ProductCode']) ?>"
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
                value="<?= htmlspecialchars($_POST['product_name'] ?? $product['ProductName']) ?>"
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
            ><?= htmlspecialchars($_POST['description'] ?? $product['Description'] ?? '') ?></textarea>
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
                value="<?= htmlspecialchars($_POST['unit'] ?? $product['Unit'] ?? '') ?>"
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
                value="<?= htmlspecialchars((string) ($_POST['price'] ?? $product['Price'])) ?>"
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
                value="<?= htmlspecialchars((string) ($_POST['stock_quantity'] ?? $product['StockQuantity'])) ?>"
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
                        value="<?= $category['CategoryID'] ?>"
                        <?= (string) $selectedCategoryID === (string) $category['CategoryID'] ? 'selected' : '' ?>
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
                        value="<?= $supplier['SupplierID'] ?>"
                        <?= (string) $selectedSupplierID === (string) $supplier['SupplierID'] ? 'selected' : '' ?>
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
                    : ((int) $product['IsActive'] === 1 ? 'checked' : '') ?>
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

<?php require_once '/var/www/src/includes/footer.php'; ?>

