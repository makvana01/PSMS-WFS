<?php
$page_title = 'Verify Reset OTP';
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['reset_email'])) {
    redirect('forgot_password.php');
}

$error = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp_input = trim(sanitize_input($_POST['otp'] ?? ''));
    
    if (!preg_match('/^[0-9]{6}$/', $otp_input)) {
        $error = "Please enter a valid 6-digit verification code.";
    } else {
        $query = "SELECT * FROM users WHERE email = ? AND otp = ? AND deleted_at IS NULL";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$email, $otp_input]);
        $user = $stmt->fetch();
        
        if ($user) {
            if (strtotime($user['otp_expires']) > time()) {
                // OTP is valid and not expired
                $_SESSION['can_reset_password'] = true;
                
                // Clear OTP
                $update = $pdo->prepare("UPDATE users SET otp = NULL, otp_expires = NULL WHERE id = ?");
                $update->execute([$user['id']]);
                
                redirect('reset_password.php');
            } else {
                $error = "Verification code has expired. Please request a new one.";
            }
        } else {
            $error = "Invalid verification code. Please try again.";
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
                    <h3 class="text-primary fw-bold mb-1">Verify OTP</h3>
                    <p class="text-muted mb-4">Enter the 6-digit verification code sent to <strong><?php echo htmlspecialchars($email); ?></strong>.</p>
                    
                    <?php if(isset($_SESSION['success_msg'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>


                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Verification Code</label>
                            <input type="text" name="otp" class="form-control text-center fs-4 letter-spacing-2" required maxlength="6" pattern="\d{6}" placeholder="------" autocomplete="off">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 mb-3">
                            <i class="fa-solid fa-check-circle me-2"></i>Verify Code
                        </button>
                    </form>
                    <div class="text-center">
                        <p class="mb-0 text-muted"><a href="forgot_password.php" class="text-primary fw-semibold">Request a new code</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
