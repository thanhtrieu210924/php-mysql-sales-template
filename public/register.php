<?php

require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/config/database.php';

if (isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

$pageTitle = 'Đăng ký tài khoản';

$customerName = '';
$phone = '';
$email = '';

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (
        $customerName === ''
        || $phone === ''
        || $email === ''
        || $password === ''
        || $confirmPassword === ''
    ) {
        $errorMessage = 'Vui lòng nhập đầy đủ thông tin.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Email không hợp lệ.';

    } elseif (strlen($password) < 6) {
        $errorMessage = 'Mật khẩu phải có ít nhất 6 ký tự.';

    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'Mật khẩu xác nhận không khớp.';

    } else {

        $checkStatement = $conn->prepare(
            'SELECT CustomerID FROM customers WHERE Email = ?'
        );

        if (!$checkStatement) {
            $errorMessage = 'Không thể kiểm tra email.';

        } else {

            $checkStatement->bind_param('s', $email);
            $checkStatement->execute();

            $existingCustomer = $checkStatement
                ->get_result()
                ->fetch_assoc();

            $checkStatement->close();

            if ($existingCustomer) {
                $errorMessage = 'Email này đã được sử dụng.';

            } else {

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $insertStatement = $conn->prepare(
                    'INSERT INTO customers
                        (CustomerName, Phone, Email, PasswordHash)
                     VALUES (?, ?, ?, ?)'
                );

                if (!$insertStatement) {
                    $errorMessage = 'Không thể tạo tài khoản.';

                } else {

                    $insertStatement->bind_param(
                        'ssss',
                        $customerName,
                        $phone,
                        $email,
                        $passwordHash
                    );

                    if ($insertStatement->execute()) {

                        $insertStatement->close();

                        header('Location: /login.php?registered=1');
                        exit;

                    } else {

                        if ($insertStatement->errno === 1062) {
                            $errorMessage = 'Email này đã được sử dụng.';
                        } else {
                            $errorMessage = 'Đăng ký thất bại. Vui lòng thử lại.';
                        }

                        $insertStatement->close();
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/../src/includes/frontend/header.php';
require_once __DIR__ . '/../src/includes/frontend/navbar.php';

?>

<div class="container mt-5">

    <div class="row justify-content-center">

        <div class="col-md-6 col-lg-5">

            <div class="card shadow-sm">

                <div class="card-body p-4">

                    <h1 class="h3 text-center mb-4">
                        Đăng ký tài khoản
                    </h1>

                    <?php if ($errorMessage !== ''): ?>

                        <div class="alert alert-danger">
                            <?= htmlspecialchars($errorMessage) ?>
                        </div>

                    <?php endif; ?>

                    <form method="POST" action="/register.php">

                        <div class="mb-3">

                            <label for="customer_name" class="form-label">
                                Họ và tên
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="customer_name"
                                name="customer_name"
                                value="<?= htmlspecialchars($customerName) ?>"
                                maxlength="100"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label for="phone" class="form-label">
                                Số điện thoại
                            </label>

                            <input
                                type="tel"
                                class="form-control"
                                id="phone"
                                name="phone"
                                value="<?= htmlspecialchars($phone) ?>"
                                maxlength="20"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label for="email" class="form-label">
                                Email
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars($email) ?>"
                                maxlength="255"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label for="password" class="form-label">
                                Mật khẩu
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                minlength="6"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label for="confirm_password" class="form-label">
                                Xác nhận mật khẩu
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="confirm_password"
                                name="confirm_password"
                                minlength="6"
                                required
                            >

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Đăng ký
                        </button>

                    </form>

                    <p class="text-center mt-3 mb-0">

                        Đã có tài khoản?

                        <a href="/login.php">
                            Đăng nhập
                        </a>

                    </p>

                </div>

            </div>

        </div>

    </div>

</div>

<?php

require_once __DIR__ . '/../src/includes/frontend/footer.php';

?>
