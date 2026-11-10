<?php

$cart = $_SESSION['cart'] ?? [];

$cartCount = is_array($cart)
    ? array_sum($cart)
    : 0;

$isLoggedIn = isset($_SESSION['customer_id']);

$customerName = $_SESSION['customer_name'] ?? '';

?>

<nav class="navbar navbar-expand-lg bg-dark navbar-dark">

    <div class="container">

        <a class="navbar-brand" href="/">
            Sales Management
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#frontendNavbar"
            aria-controls="frontendNavbar"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div
            class="collapse navbar-collapse"
            id="frontendNavbar"
        >

            <ul class="navbar-nav me-auto">

                <li class="nav-item">
                    <a class="nav-link" href="/">
                        Trang chủ
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/products.php">
                        Sản phẩm
                    </a>
                </li>

            </ul>

            <div class="d-flex gap-2 align-items-center flex-wrap">

                <a
                    class="btn btn-outline-light btn-sm"
                    href="/cart.php"
                >
                    Giỏ hàng (<?= (int) $cartCount ?>)
                </a>

                <a
                    class="btn btn-outline-light btn-sm"
                    href="/admin/"
                >
                    Quản trị
                </a>

                <?php if ($isLoggedIn): ?>

                    <span class="text-white">
                        Xin chào,
                        <?= htmlspecialchars($customerName) ?>
                    </span>

                    <a
                        class="btn btn-outline-warning btn-sm"
                        href="/logout.php"
                    >
                        Đăng xuất
                    </a>

                <?php else: ?>

                    <a
                        class="btn btn-outline-light btn-sm"
                        href="/register.php"
                    >
                        Đăng ký
                    </a>

                    <a
                        class="btn btn-outline-light btn-sm"
                        href="/login.php"
                    >
                        Đăng nhập
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

</nav>
