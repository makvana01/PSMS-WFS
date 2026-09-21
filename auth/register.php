<?php
$page_title = 'Register';
require_once '../config/db.php';
require_once '../includes/functions.php';

// If user is already logged in, redirect them to their respective dashboard
if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'student') redirect('../pages/student_dashboard.php');
    elseif ($role === 'company') redirect('../pages/company_dashboard.php');
    elseif ($role === 'admin') redirect('../pages/admin_dashboard.php');
    else redirect('../index.php');
}

$error = '';
$name_val = '';
$email_val = '';
$role_val = (isset($_GET['role']) && in_array($_GET['role'], ['student', 'company'])) ? sanitize_input($_GET['role']) : 'student';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Clean all user inputs to prevent hacking
    $name = sanitize_input($_POST['name'] ?? '');
    $email = strtolower(trim(sanitize_input($_POST['email'] ?? '')));
    $password = $_POST['password'] ?? '';
    $role = sanitize_input($_POST['role'] ?? 'student');
    $captcha_input = sanitize_input($_POST['captcha'] ?? '');

    $name_val = $name;
    $email_val = $email;
    $role_val = in_array($role, ['student', 'company']) ? $role : 'student';
    
    // 1. Captcha validation
    $expected_captcha = $_SESSION['captcha_answer'] ?? null;
    unset($_SESSION['captcha_answer']); // Invalidate captcha immediately to prevent replay attacks

    if ($expected_captcha === null || $captcha_input != $expected_captcha) {
        $error = "Invalid Captcha answer. Please solve the math problem again.";
    }
    // 2. Name validation (Letters A-Z, a-z, and spaces only)
    elseif (!validateName($name)) {
        $error = "Name can only contain alphabetic characters (A-Z, a-z) and spaces (2 to 100 characters).";
    }
    // 3. Email validation
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    // 4. Strong Password validation
    elseif (($pwd_check = validateStrongPassword($password)) !== true) {
        $error = $pwd_check;
    } else {
        // Check if email already exists in the database
        $check_query = "SELECT id, is_verified, deleted_at FROM users WHERE email = ?";
        $stmt = $pdo->prepare($check_query);
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch();
        
        // If an existing verified user is found, reject registration
        if ($existing_user && $existing_user['is_verified']) {
            $error = "An account with this email is already registered. Please login instead.";
        } else {
            // Hash password securely
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $otp = generateOTP();
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            try {
                $pdo->beginTransaction();
                
                if ($existing_user && !$existing_user['is_verified']) {
                    // Update existing unverified user record with new details and fresh OTP
                    $user_id = $existing_user['id'];
                    $update_query = "UPDATE users SET name = ?, password = ?, role = ?, is_verified = 0, otp = ?, otp_expires = ?, deleted_at = NULL WHERE id = ?";
                    $stmt_update = $pdo->prepare($update_query);
                    $stmt_update->execute([$name, $hashed_password, $role, $otp, $expires, $user_id]);
                    
                    // Ensure role table record exists
                    if ($role === 'student') {
                        $check_s = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
                        $check_s->execute([$user_id]);
                        if (!$check_s->fetch()) {
                            $pdo->prepare("INSERT INTO students (user_id) VALUES (?)")->execute([$user_id]);
                        }
                    } elseif ($role === 'company') {
                        $check_c = $pdo->prepare("SELECT id FROM companies WHERE user_id = ?");
                        $check_c->execute([$user_id]);
                        if (!$check_c->fetch()) {
                            $pdo->prepare("INSERT INTO companies (user_id, company_name) VALUES (?, ?)")->execute([$user_id, $name]);
                        } else {
                            $pdo->prepare("UPDATE companies SET company_name = ? WHERE user_id = ?")->execute([$name, $user_id]);
                        }
                    }
                } else {
                    // Insert brand new user
                    $insert_query = "INSERT INTO users (name, email, password, role, is_verified, otp, otp_expires) VALUES (?, ?, ?, ?, 0, ?, ?)";
                    $insert = $pdo->prepare($insert_query);
                    $insert->execute([$name, $email, $hashed_password, $role, $otp, $expires]);
                    $user_id = $pdo->lastInsertId();
                    
                    // Create corresponding profile entry
                    if ($role === 'student') {
                        $profile_query = "INSERT INTO students (user_id) VALUES (?)";
                        $stmt_profile = $pdo->prepare($profile_query);
                        $stmt_profile->execute([$user_id]);
                    } elseif ($role === 'company') {
                        $profile_query = "INSERT INTO companies (user_id, company_name) VALUES (?, ?)";
                        $stmt_profile = $pdo->prepare($profile_query);
                        $stmt_profile->execute([$user_id, $name]);
                    }
                }
                
                // Commit changes
                $pdo->commit();
                
                // Send verification OTP email to Gmail
                sendOTPEmail($email, $otp, 'verification');
                $_SESSION['verify_email'] = $email;
                $_SESSION['success_msg'] = "Registration initiated! Please enter the 6-digit OTP sent to your email to verify your account.";
                
                // Redirect to OTP verification page
                redirect('verify_otp.php');
                
            } catch(Exception $e) {
                $pdo->rollBack();
                error_log("Registration error: " . $e->getMessage());
                $error = "Registration failed due to a system error. Please try again.";
            }
        }
    }
}
include '../includes/header.php';
?>

<div class="auth-wrapper fade-in py-4">
    <div class="row justify-content-center w-100">
        <div class="col-md-10 col-lg-8">
            <div class="card auth-card shadow-lg">
                <div class="row g-0">
                    <div class="col-md-5 d-none d-md-block">
                        <div class="auth-side h-100 p-4 d-flex flex-column justify-content-center text-white">
                            <i class="fa-solid fa-user-plus mb-3" style="font-size: 3rem;"></i>
                            <h2 class="fw-bold">Join Us!</h2>
                            <p class="small text-white-50">Create an account and start your journey towards your dream career.</p>
                            <div class="mt-4 pt-3 border-top border-white-50 small">
                                <p class="mb-1"><i class="fa-solid fa-circle-check text-success me-2"></i>Secure OTP verification</p>
                                <p class="mb-1"><i class="fa-solid fa-circle-check text-success me-2"></i>Direct employer connect</p>
                                <p class="mb-0"><i class="fa-solid fa-circle-check text-success me-2"></i>Live job application tracking</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card-body p-4 p-md-5">
                            <h3 class="text-primary fw-bold mb-1">Create Account</h3>
                            <p class="text-muted mb-4 small">Fill in the form below to get registered</p>
                            
                            <?php if($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                                    <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="" id="registerForm" novalidate>
                                <!-- Name field (A-Z only) -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Full Name / Contact Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                        <input type="text" name="name" id="reg_name" class="form-control alpha-only-input" required 
                                            placeholder="e.g. Rahul Sharma" 
                                            value="<?php echo htmlspecialchars($name_val); ?>"
                                            maxlength="100"
                                            pattern="[a-zA-Z\s]{2,100}" 
                                            title="Only alphabets (A-Z, a-z) and spaces are allowed.">
                                    </div>
                                    <div class="form-text text-muted small"><i class="fa-solid fa-info-circle me-1"></i>Only alphabetic letters (A-Z, a-z) and spaces (Max 100 characters).</div>
                                </div>

                                <!-- Email field -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                        <input type="email" name="email" id="reg_email" class="form-control" required 
                                            placeholder="you@gmail.com" 
                                            maxlength="100"
                                            value="<?php echo htmlspecialchars($email_val); ?>">
                                    </div>
                                </div>

                                <!-- Password field with strength rules -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                    <div class="input-group password-toggle">
                                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                        <input type="password" name="password" id="reg_password" class="form-control" required 
                                            autocomplete="new-password" placeholder="Create a strong password">
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

                                <!-- Role select -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Register As <span class="text-danger">*</span></label>
                                    <select name="role" class="form-select" required>
                                        <option value="student" <?php echo $role_val === 'student' ? 'selected' : ''; ?>>Student</option>
                                        <option value="company" <?php echo $role_val === 'company' ? 'selected' : ''; ?>>Company / Employer</option>
                                    </select>
                                </div>

                                <!-- Captcha -->
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Solve this: <img src="../includes/captcha.php" alt="captcha" class="captcha-img ms-2 rounded border" style="cursor: pointer;" title="Click to refresh"></label>
                                    <input type="text" name="captcha" class="form-control" required placeholder="Enter the math answer" autocomplete="off">
                                    <small class="text-muted"><i class="fa-solid fa-rotate me-1"></i>Click the image if unreadable</small>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 mb-3 shadow-sm">
                                    <i class="fa-solid fa-user-plus me-2"></i>Register & Send OTP
                                </button>
                            </form>
                            <div class="text-center">
                                <p class="mb-0 text-muted small">Already have an account? <a href="login.php" class="text-primary fw-semibold">Login here</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

