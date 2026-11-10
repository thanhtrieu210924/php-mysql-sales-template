<?php

require_once __DIR__ . '/../src/config/session.php';
require_once __DIR__ . '/../src/config/database.php';

if (isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

$pageTitle = 'Đăng nhập';

$email = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $errorMessage = 'Vui lòng nhập email và mật khẩu.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errorMessage = 'Email không hợp lệ.';

    } else {

        $statement = $conn->prepare(
            'SELECT CustomerID, CustomerName, Email, PasswordHash
             FROM customers
             WHERE Email = ?
             LIMIT 1'
        );

        if (!$statement) {

            $errorMessage = 'Không thể xử lý đăng nhập.';

        } else {

            $statement->bind_param('s', $email);
            $statement->execute();

            $customer = $statement
                ->get_result()
                ->fetch_assoc();

            $statement->close();

            if (
                !$customer
                || empty($customer['PasswordHash'])
                || !password_verify(
                    $password,
                    $customer['PasswordHash']
                )
            ) {

                $errorMessage = 'Email hoặc mật khẩu không đúng.';

            } else {

                session_regenerate_id(true);

                $_SESSION['customer_id'] = (int) $customer['CustomerID'];
                $_SESSION['customer_name'] = $customer['CustomerName'];

                header('Location: /');
                exit;
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
                        Đăng nhập
                    </h1>

                    <?php if (isset($_GET['registered'])): ?>

                        <div class="alert alert-success">
                            Đăng ký tài khoản thành công. Vui lòng đăng nhập.
                        </div>

                    <?php endif; ?>

                    <?php if ($errorMessage !== ''): ?>

                        <div class="alert alert-danger">
                            <?= htmlspecialchars($errorMessage) ?>
                        </div>

                    <?php endif; ?>

                    <form method="POST" action="/login.php">

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
                                required
                            >

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Đăng nhập
                        </button>

                    </form>

                    <p class="text-center mt-3 mb-0">

                        Chưa có tài khoản?

                        <a href="/register.php">
                            Đăng ký
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
