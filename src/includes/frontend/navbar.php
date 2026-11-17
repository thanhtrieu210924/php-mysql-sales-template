<?php

$cart = $_SESSION['cart'] ?? [];

$cartCount = is_array($cart)
    ? array_sum($cart)
    : 0;

$isLoggedIn = isset($_SESSION['customer_id']);

$customerName = $_SESSION['customer_name'] ?? '';

?>

<nav class="navbar navbar-expand-lg navbar-dark site-navbar">

    <div class="container">

        <!-- BRAND -->
        <a class="navbar-brand" href="/">

            <span class="brand-mark">
                <i class="bi bi-cpu-fill"></i>
            </span>

            <span class="brand-name">
                Tech<span>Store</span>
            </span>

        </a>


        <!-- MOBILE TOGGLER -->
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


        <!-- NAVIGATION -->
        <div
            class="collapse navbar-collapse"
            id="frontendNavbar"
        >

            <ul class="navbar-nav me-auto ms-lg-4">

                <li class="nav-item">
                    <a class="nav-link" href="/">
                        <i class="bi bi-house-door me-1"></i>
                        Trang chủ
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/products.php">
                        <i class="bi bi-grid me-1"></i>
                        Sản phẩm
                    </a>
                </li>

            </ul>


            <!-- USER ACTIONS -->
            <div class="d-flex gap-2 align-items-center flex-wrap mt-3 mt-lg-0">

                <!-- CART -->
                <a
                    class="nav-action nav-action-primary cart-wrapper"
                    href="/cart.php"
                >
                    <i class="bi bi-cart3"></i>
                    <span>Giỏ hàng</span>

                    <span class="cart-badge">
                        <?= (int) $cartCount ?>
                    </span>
                </a>


                <!-- ADMIN -->
                <a
                    class="nav-action"
                    href="/admin/"
                >
                    <i class="bi bi-speedometer2"></i>
                    <span>Quản trị</span>
                </a>


                <?php if ($isLoggedIn): ?>

                    <!-- CUSTOMER -->
                    <span class="nav-action">
                        <i class="bi bi-person-circle"></i>

                        <?= htmlspecialchars(
                            $customerName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                    <!-- LOGOUT -->
                    <a
                        class="nav-action"
                        href="/logout.php"
                    >
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Đăng xuất</span>
                    </a>

                <?php else: ?>

                    <!-- REGISTER -->
                    <a
                        class="nav-action"
                        href="/register.php"
                    >
                        <i class="bi bi-person-plus"></i>
                        <span>Đăng ký</span>
                    </a>

                    <!-- LOGIN -->
                    <a
                        class="nav-action"
                        href="/login.php"
                    >
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Đăng nhập</span>
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

</nav>
