<?php
$page_title = 'Reset Password';
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['can_reset_password']) || !$_SESSION['can_reset_password']) {
    redirect('login.php');
}

$error = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($password !== $confirm_password) {
        $error = "Passwords do not match. Please verify both entries.";
    } elseif (($pwd_check = validateStrongPassword($password)) !== true) {
        $error = $pwd_check;
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $update = $pdo->prepare("UPDATE users SET password = ?, is_verified = 1, otp = NULL, otp_expires = NULL, remember_token = NULL WHERE email = ? AND deleted_at IS NULL");
        $update->execute([$hashed_password, $email]);
        
        // Clear reset session variables and regenerate session ID
        unset($_SESSION['reset_email']);
        unset($_SESSION['can_reset_password']);
        session_regenerate_id(true);
        
        $_SESSION['success_msg'] = "Your password has been successfully reset. You can now log in with your new password.";
        redirect('login.php');
    }
}
include '../includes/header.php';
?>

<div class="auth-wrapper fade-in py-4">
    <div class="row justify-content-center w-100">
        <div class="col-md-9 col-lg-6">
            <div class="card auth-card shadow-lg">
                <div class="card-body p-4 p-md-5">
                    <h3 class="text-primary fw-bold mb-1">Set New Password</h3>
                    <p class="text-muted mb-4 small">Please enter your new strong password below for <strong><?php echo htmlspecialchars($email); ?></strong>.</p>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="resetPasswordForm" novalidate>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                            <div class="input-group password-toggle">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="password" id="reg_password" class="form-control" required placeholder="Enter new password">
                                <button type="button" class="toggle-password toggle-btn" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                            </div>

                            <!-- Interactive Password Requirements Checklist -->
                            <div class="password-requirements-box mt-2 p-2.5 rounded bg-light border">
                                <div class="small fw-semibold text-muted mb-1">Password must include:</div>
                                <div class="row g-1 small">
                                    <div class="col-6" id="rule-length"><i class="fa-regular fa-circle text-muted me-1"></i>Min 8 characters</div>
                                    <div class="col-6" id="rule-upper"><i class="fa-regular fa-circle text-muted me-1"></i>1 Uppercase (A-Z)</div>
                                    <div class="col-6" id="rule-lower"><i class="fa-regular fa-circle text-muted me-1"></i>1 Lowercase (a-z)</div>
                                    <div class="col-6" id="rule-number"><i class="fa-regular fa-circle text-muted me-1"></i>1 Number (0-9)</div>
                                    <div class="col-12" id="rule-special"><i class="fa-regular fa-circle text-muted me-1"></i>1 Special char (@$!%*?&#)</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group password-toggle">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="confirm_password" class="form-control" required placeholder="Confirm new password">
                                <button type="button" class="toggle-password toggle-btn" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 mb-3 shadow-sm">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Save New Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

