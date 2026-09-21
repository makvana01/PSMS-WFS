<?php
$page_title = 'Login';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is already logged in, redirect them to dashboard
if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'student') redirect('../pages/student_dashboard.php');
    elseif ($role === 'company') redirect('../pages/company_dashboard.php');
    elseif ($role === 'admin') redirect('../pages/admin_dashboard.php');
    else redirect('../index.php');
}

$error = '';
$success = '';

if (isset($_GET['session_expired'])) {
    $error = "Your session has expired due to inactivity. Please log in again.";
} elseif (isset($_GET['logout'])) {
    $success = "You have been logged out successfully.";
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data and clean it
    $email = strtolower(trim(sanitize_input($_POST['email'] ?? '')));
    $password = $_POST['password'] ?? '';
    $captcha_input = sanitize_input($_POST['captcha'] ?? '');

    // Captcha validation: compare user input with session value
    $expected_captcha = $_SESSION['captcha_answer'] ?? null;
    unset($_SESSION['captcha_answer']); // Invalidate captcha immediately to prevent replay attacks

    if ($expected_captcha === null || $captcha_input != $expected_captcha) {
        $error = "Invalid Captcha answer. Please solve the new problem.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (empty($password)) {
        $error = "Please enter your password.";
    } else {
        // Find the user in the database using their email
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // If user exists and password is correct
        if ($user && password_verify($password, $user['password'])) {

            // Check if user is deactivated (soft deleted)
            if (!empty($user['deleted_at'])) {
                $error = "Your account is deactivated. For activation, please contact the administrator.";
            } elseif (!$user['is_verified']) {
                // Generate a new OTP and set its expiration time (10 minutes)
                $otp = generateOTP();
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                // Update the user's OTP in the database
                $update_query = "UPDATE users SET otp = ?, otp_expires = ? WHERE id = ?";
                $update = $pdo->prepare($update_query);
                $update->execute([$otp, $expires, $user['id']]);

                // Send the new OTP to the user's email
                sendOTPEmail($user['email'], $otp, 'verification');

                // Save email in session to verify it on the next page
                $_SESSION['verify_email'] = $user['email'];
                $_SESSION['success_msg'] = "Your account is not verified yet. A new verification OTP has been sent to your email. Please verify to continue.";
                redirect('verify_otp.php');
            } else {
                // User is verified, regenerate session ID to prevent Session Fixation
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['last_activity'] = time();

                // Remember Me token generation (if checkbox was checked)
                if (isset($_POST['remember_me'])) {
                    $token = bin2hex(random_bytes(32)); // Create a random token

                    // Save token in the database
                    $updateToken_query = "UPDATE users SET remember_token = ? WHERE id = ?";
                    $updateToken = $pdo->prepare($updateToken_query);
                    $updateToken->execute([$token, $user['id']]);

                    // Set cookie for 30 days securely
                    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                    setcookie('remember_me', $user['id'] . ':' . $token, [
                        'expires'  => time() + (86400 * 30),
                        'path'     => '/',
                        'domain'   => '',
                        'secure'   => $is_https,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }

                // Check profile completion status
                if ($user['role'] === 'student') {
                    $pStatus = getStudentProfileStatus($pdo, $user['id']);
                    if ($pStatus && !$pStatus['is_complete']) {
                        $_SESSION['profile_warning'] = "Welcome back, " . htmlspecialchars($user['name']) . "! Your profile is " . $pStatus['percentage'] . "% complete. Please complete all required profile fields.";
                    }
                    redirect('../pages/student_dashboard.php');
                } elseif ($user['role'] === 'company') {
                    $pStatus = getCompanyProfileStatus($pdo, $user['id']);
                    if ($pStatus && !$pStatus['is_complete']) {
                        $_SESSION['profile_warning'] = "Welcome back, " . htmlspecialchars($user['name']) . "! Your company profile is " . $pStatus['percentage'] . "% complete. Please complete all required company fields.";
                    }
                    redirect('../pages/company_dashboard.php');
                } elseif ($user['role'] === 'admin') {
                    redirect('../pages/admin_dashboard.php');
                } else {
                    redirect('../index.php');
                }
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
include '../includes/header.php';
?>

<div class="auth-wrapper fade-in">
    <div class="row justify-content-center w-100">
        <div class="col-md-9 col-lg-7">
            <div class="card auth-card shadow-lg">
                <div class="row g-0">
                    <div class="col-md-5 d-none d-md-block">
                        <div class="auth-side h-100">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            <h2>Welcome Back!</h2>
                            <p>Login to access your dashboard, track applications, and discover new opportunities.</p>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card-body p-4 p-md-5">
                            <h3 class="text-primary fw-bold mb-1">Login</h3>
                            <p class="text-muted mb-4">Enter your credentials to continue</p>

                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="fa-solid fa-circle-check me-2"></i><?php echo $success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['success_msg'])): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['success_msg'];
                                    unset($_SESSION['success_msg']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Email address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                        <input type="email" name="email" class="form-control" required
                                            placeholder="you@example.com">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold d-flex justify-content-between w-100">
                                        Password
                                        <a href="forgot_password.php"
                                            class="text-primary fw-normal fs-6 text-decoration-none"
                                            style="font-size: 0.9rem;">Forgot Password?</a>
                                    </label>
                                    <div class="input-group password-toggle">
                                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                        <input type="password" name="password" class="form-control" required
                                            autocomplete="current-password" placeholder="Enter password">
                                        <button type="button" class="toggle-password toggle-btn"><i
                                                class="fa-solid fa-eye"></i></button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Solve this: <img src="../includes/captcha.php"
                                            alt="captcha" class="captcha-img ms-2"></label>
                                    <input type="text" name="captcha" class="form-control" required
                                        placeholder="Enter answer">

                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                                    <label class="form-check-label" for="rememberMe">Remember Me</label>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 mb-3">
                                    <i class="fa-solid fa-right-to-bracket me-2"></i>Login
                                </button>
                            </form>
                            <div class="text-center">
                                <p class="mb-0 text-muted">Don't have an account? <a href="register.php"
                                        class="text-primary fw-semibold">Register</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Prevent navigating back to protected session pages from the login screen
    if (window.history && window.history.pushState) {
        window.history.pushState(null, "", window.location.href);
        window.onpopstate = function () {
            window.history.pushState(null, "", window.location.href);
        };
    }
</script>

<?php include '../includes/footer.php'; ?>