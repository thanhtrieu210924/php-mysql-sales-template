<?php

$pageTitle = 'Quản lý sản phẩm';

require_once '/var/www/src/config/database.php';

$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Unit,
        p.Price,
        p.StockQuantity,
        p.IsActive,
        c.CategoryName,
        s.SupplierName
    FROM
        products AS p,
        categories AS c,
        suppliers AS s
    WHERE
        p.CategoryID = c.CategoryID
        AND p.SupplierID = s.SupplierID
    ORDER BY
        p.ProductID
";

$result = $conn->query($sql);

require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';

?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Quản lý sản phẩm</h2>

        <a href="/products/create.php" class="btn btn-primary">
            Thêm sản phẩm
        </a>
    </div>

    <div class="table-responsive">

        <table class="table table-bordered table-striped align-middle">

            <thead class="table-dark">
                <tr>
                    <th>Mã SP</th>
                    <th>Tên sản phẩm</th>
                    <th>Danh mục</th>
                    <th>Nhà cung cấp</th>
                    <th>Đơn vị</th>
                    <th>Giá</th>
                    <th>Tồn kho</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

            <tbody>

            <?php while ($product = $result->fetch_assoc()): ?>

                <tr>

                    <td><?= htmlspecialchars($product['ProductCode']) ?></td>

                    <td><?= htmlspecialchars($product['ProductName']) ?></td>

                    <td><?= htmlspecialchars($product['CategoryName']) ?></td>

                    <td><?= htmlspecialchars($product['SupplierName']) ?></td>

                    <td><?= htmlspecialchars($product['Unit'] ?? '') ?></td>

                    <td class="text-end">
                        <?= number_format(
                            (float) $product['Price'],
                            0,
                            ',',
                            '.'
                        ) ?> đ
                    </td>

                    <td class="text-end">
                        <?= (int) $product['StockQuantity'] ?>
                    </td>

                    <td>
                        <?php if ((int) $product['IsActive'] === 1): ?>

                            <span class="badge bg-success">
                                Đang bán
                            </span>

                        <?php else: ?>

                            <span class="badge bg-secondary">
                                Ngừng bán
                            </span>

                        <?php endif; ?>
                    </td>

                    <td>
                        <a href="#" class="btn btn-sm btn-warning">
                            Sửa
                        </a>

                        <a href="#" class="btn btn-sm btn-danger">
                            Xóa
                        </a>
                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

<?php

require_once '/var/www/src/includes/footer.php';

$conn->close();

?>