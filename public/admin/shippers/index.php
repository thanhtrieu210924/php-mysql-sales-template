<?php
$pageTitle = 'Quản lý người giao hàng';

require_once '/var/www/src/config/database.php';
require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

$sql = "SELECT ShipperID, ShipperName, Phone
        FROM shippers
        ORDER BY ShipperID ASC";

$result = $conn->query($sql);
?>

<main class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Quản lý người giao hàng</h1>

        <a href="/admin/shippers/create.php" class="btn btn-primary">
            Thêm người giao hàng
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Thao tác đã được thực hiện thành công.
            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Đóng"
            ></button>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th class="text-center">STT</th>
                    <th>Mã</th>
                    <th>Tên người giao hàng</th>
                    <th>Số điện thoại</th>
                    <th class="text-center">Thao tác</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php $stt = 1; ?>

                    <?php while ($shipper = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center">
                                <?= $stt++ ?>
                            </td>

                            <td>
                                <?= (int) $shipper['ShipperID'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $shipper['ShipperName'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $shipper['Phone'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td class="text-center">
                                <a
                                    href="/admin/shippers/edit.php?id=<?= (int) $shipper['ShipperID'] ?>"
                                    class="btn btn-warning btn-sm"
                                >Sửa</a>

                                <form
                                    action="/admin/shippers/delete.php"
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Bạn có chắc muốn xóa người giao hàng này?');"
                                >
                                    <input
                                        type="hidden"
                                        name="shipper_id"
                                        value="<?= (int) $shipper['ShipperID'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-danger btn-sm"
                                    >Xóa</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            Chưa có người giao hàng.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
?>
