
<?php

$pageTitle = 'Thêm sản phẩm';

require_once '/var/www/src/config/database.php';

$error = '';

$sqlCategories = "
    SELECT CategoryID, CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categories = $conn->query($sqlCategories);

$sqlSuppliers = "
    SELECT SupplierID, SupplierName
    FROM suppliers
    ORDER BY SupplierName
";

$suppliers = $conn->query($sqlSuppliers);

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
            INSERT INTO products
            (
                ProductCode,
                ProductName,
                Description,
                Unit,
                Price,
                StockQuantity,
                IsActive,
                SupplierID,
                CategoryID
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            'ssssdiiii',
            $productCode,
            $productName,
            $description,
            $unit,
            $price,
            $stockQuantity,
            $isActive,
            $supplierID,
            $categoryID
        );

        if ($stmt->execute()) {
            header('Location: /products/');
            exit;
        }

        $error = 'Không thể thêm sản phẩm.';
        $stmt->close();
    }
}

require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';
?>

<div class="container mt-4">

    <h2>Thêm sản phẩm</h2>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="post">

        <div class="mb-3">
            <label class="form-label">Mã sản phẩm</label>

            <input
                type="text"
                name="product_code"
                class="form-control"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Tên sản phẩm</label>

            <input
                type="text"
                name="product_name"
                class="form-control"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Mô tả</label>

            <textarea
                name="description"
                class="form-control"
                rows="3"
            ></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Đơn vị</label>

            <input
                type="text"
                name="unit"
                class="form-control"
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Giá</label>

            <input
                type="number"
                name="price"
                class="form-control"
                min="0"
                step="0.01"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Tồn kho</label>

            <input
                type="number"
                name="stock_quantity"
                class="form-control"
                min="0"
                required
            >
        </div>

        <div class="mb-3">
            <label class="form-label">Danh mục</label>

            <select name="category_id" class="form-select" required>

                <option value="">
                    -- Chọn danh mục --
                </option>

                <?php while ($category = $categories->fetch_assoc()): ?>

                    <option
                        value="<?= $category['CategoryID'] ?>"
                    >
                        <?= htmlspecialchars($category['CategoryName']) ?>
                    </option>

                <?php endwhile; ?>

            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Nhà cung cấp</label>

            <select name="supplier_id" class="form-select" required>

                <option value="">
                    -- Chọn nhà cung cấp --
                </option>

                <?php while ($supplier = $suppliers->fetch_assoc()): ?>

                    <option
                        value="<?= $supplier['SupplierID'] ?>"
                    >
                        <?= htmlspecialchars($supplier['SupplierName']) ?>
                    </option>

                <?php endwhile; ?>

            </select>
        </div>

        <div class="form-check mb-3">

            <input
                type="checkbox"
                name="is_active"
                class="form-check-input"
                id="is_active"
                checked
            >

            <label
                class="form-check-label"
                for="is_active"
            >
                Đang kinh doanh
            </label>

        </div>

        <button
            type="submit"
            class="btn btn-primary"
        >
            Thêm sản phẩm
        </button>

        <a
            href="/products/"
            class="btn btn-secondary"
        >
            Quay lại
        </a>

    </form>

</div>

<?php

require_once '/var/www/src/includes/footer.php';

$conn->close();

?>

