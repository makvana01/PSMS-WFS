<?php
$page_title = 'Forgot Password';
require_once '../config/db.php';
require_once '../includes/functions.php';

if (isLoggedIn()) {
    redirect('../index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = strtolower(trim(sanitize_input($_POST['email'] ?? '')));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email exists
        $query = "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            if (!$user['is_verified']) {
                $error = "This account is not verified yet. Please login to verify your email first.";
            } else {
                $otp = generateOTP();
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                $update_query = "UPDATE users SET otp = ?, otp_expires = ? WHERE id = ?";
                $update = $pdo->prepare($update_query);
                $update->execute([$otp, $expires, $user['id']]);
                
                sendOTPEmail($user['email'], $otp, 'reset');
                
                $_SESSION['reset_email'] = $user['email'];
                $_SESSION['success_msg'] = "A 6-digit password reset code has been sent to your email.";
                redirect('reset_verify_otp.php');
            }
        } else {
            $error = "No active account found with that email address.";
        }
    }
}
include '../includes/header.php';
?>

<div class="auth-wrapper fade-in">
    <div class="row justify-content-center w-100">
        <div class="col-md-9 col-lg-6">
            <div class="card auth-card shadow-lg">
                <div class="card-body p-4 p-md-5">
                    <h3 class="text-primary fw-bold mb-1">Forgot Password</h3>
                    <p class="text-muted mb-4">Enter your registered email address and we'll send you an OTP to reset your password.</p>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Email address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" required placeholder="you@example.com">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 mb-3">
                            <i class="fa-solid fa-paper-plane me-2"></i>Send Reset OTP
                        </button>
                    </form>
                    <div class="text-center">
                        <p class="mb-0 text-muted">Remembered your password? <a href="login.php" class="text-primary fw-semibold">Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
