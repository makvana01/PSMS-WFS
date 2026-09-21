<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// If user is already logged in, redirect them to the home page
if (isLoggedIn()) {
    redirect('../index.php');
}

// If there is no email in session to verify, send them back to login page
if (!isset($_SESSION['verify_email'])) {
    redirect('login.php');
}

$email = $_SESSION['verify_email'];
$error = '';
$success = '';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Check if the 'Verify' button was clicked
    if (isset($_POST['verify'])) {
        $otp = trim(sanitize_input($_POST['otp'] ?? ''));
        
        if (!preg_match('/^[0-9]{6}$/', $otp)) {
            $error = "Please enter a valid 6-digit numeric OTP.";
        } else {
            // Find the user by their email
            $query = "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // If user is found
            if ($user) {
                // Check if OTP matches and is not expired
                if ($user['otp'] === $otp && strtotime($user['otp_expires']) > time()) {
                    // OTP is correct! Update user status to verified
                    $update_query = "UPDATE users SET is_verified = 1, otp = NULL, otp_expires = NULL WHERE id = ?";
                    $update = $pdo->prepare($update_query);
                    $update->execute([$user['id']]);
                    
                    // Clear the verification session
                    unset($_SESSION['verify_email']);
                    
                    // Regenerate session ID and log in the user upon successful verification
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['last_activity'] = time();
                    
                    $_SESSION['success_msg'] = "Account verified successfully! Welcome to PMS, " . htmlspecialchars($user['name']) . ".";
                    
                    // Redirect to role dashboard
                    if ($user['role'] === 'student') {
                        redirect('../pages/student_dashboard.php');
                    } elseif ($user['role'] === 'company') {
                        redirect('../pages/company_dashboard.php');
                    } elseif ($user['role'] === 'admin') {
                        redirect('../pages/admin_dashboard.php');
                    } else {
                        redirect('../index.php');
                    }
                } else {
                    $error = "Invalid or expired OTP. Please check the code or click 'Resend OTP'.";
                }
            } else {
                $error = "User account not found. Please register again.";
            }
        }
        
    // Check if the 'Resend' button was clicked
    } elseif (isset($_POST['resend'])) {
        // Generate a new OTP and set expiration to 10 minutes from now
        $otp = generateOTP();
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Update the new OTP in the database
        $update_query = "UPDATE users SET otp = ?, otp_expires = ? WHERE email = ?";
        $update = $pdo->prepare($update_query);
        $update->execute([$otp, $expires, $email]);
        
        // Send the email again
        sendOTPEmail($email, $otp, 'verification');
        $success = "A fresh 6-digit OTP has been sent to your email.";
    }
}
include '../includes/header.php';
?>

<div class="auth-wrapper fade-in">
    <div class="row justify-content-center w-100">
        <div class="col-md-6 col-lg-5">
            <div class="card auth-card shadow-lg">
                <div class="card-body p-4 p-md-5 text-center">
                    <div class="mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 p-4 mb-3">
                            <i class="fa-solid fa-envelope-open-text text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <h3 class="text-primary fw-bold mb-2">Verify OTP</h3>
                        <p class="text-muted mb-0">Enter the 6-digit code sent to <strong><?php echo htmlspecialchars($email); ?></strong></p>
                    </div>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fa-solid fa-circle-check me-2"></i><?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="mb-3">
                        <div class="mb-4">
                            <input type="text" name="otp" class="form-control form-control-lg text-center" required maxlength="6" placeholder="• • • • • •" style="letter-spacing: 0.5em; font-size: 1.5rem;">
                        </div>
                        <button type="submit" name="verify" class="btn btn-primary w-100 rounded-pill py-2 mb-3">
                            <i class="fa-solid fa-check-circle me-2"></i>Verify
                        </button>
                    </form>
                    
                    <form method="POST" action="">
                        <button type="submit" name="resend" class="btn btn-link text-decoration-none">
                            <i class="fa-solid fa-rotate me-1"></i>Resend OTP
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
