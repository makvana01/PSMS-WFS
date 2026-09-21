<?php
// includes/functions.php

// Function to clean input data to prevent security issues like XSS
function sanitize_input($data) {
    $data = trim($data);             // Remove extra spaces from start and end
    $data = stripslashes($data);     // Remove backslashes
    $data = htmlspecialchars($data); // Convert special characters to HTML to prevent code execution
    return $data;
}

// Function to safely generate dynamic application URLs for robust routing
function app_url($path = '') {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(.+?)/(auth|pages|actions|includes)#', $scriptDir, $matches)) {
        $base = rtrim($matches[1], '/');
    } else {
        $base = ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

// Function to easily redirect the user to another page
function redirect($url) {
    header("Location: $url");
    exit();
}

// Function to safely redirect only to internal application URLs (prevents Open Redirect attacks)
function safe_redirect($targetUrl, $fallbackUrl = '../index.php') {
    if (empty($targetUrl)) {
        redirect($fallbackUrl);
    }
    
    // Parse URL
    $parsed = parse_url($targetUrl);
    
    // If it's a relative path or has no external host, allow it
    if (empty($parsed['host'])) {
        // Disallow protocol-relative URLs (e.g. //evil.com)
        if (substr($targetUrl, 0, 2) === '//') {
            redirect($fallbackUrl);
        }
        redirect($targetUrl);
    }
    
    // If host matches the current server host, allow it
    $serverHost = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($serverHost) && strtolower($parsed['host']) === strtolower(explode(':', $serverHost)[0])) {
        redirect($targetUrl);
    }
    
    // Otherwise fallback safely
    redirect($fallbackUrl);
}

// Function to safely validate uploaded files (size, extension, and MIME type)
function validateUploadedFile($fileArray, $allowedExtensions, $allowedMimes, $maxSizeBytes = 5242880) {
    if (!isset($fileArray) || $fileArray['error'] !== UPLOAD_ERR_OK) {
        return "Upload error occurred. Code: " . ($fileArray['error'] ?? 'Unknown');
    }

    if ($fileArray['size'] > $maxSizeBytes) {
        $maxMB = round($maxSizeBytes / (1024 * 1024));
        return "File size exceeds the maximum limit of {$maxMB}MB.";
    }

    $ext = strtolower(pathinfo($fileArray['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        return "Invalid file extension. Allowed: " . implode(', ', $allowedExtensions);
    }

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($fileArray['tmp_name']);
        if ($mime && !in_array($mime, $allowedMimes)) {
            return "Invalid file content type ($mime). Please upload a valid document/image.";
        }
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($fileArray['tmp_name']);
        if ($mime && !in_array($mime, $allowedMimes)) {
            return "Invalid file content type ($mime). Please upload a valid document/image.";
        }
    }

    return true;
}


// Cleanly terminate session and wipe client cookies
function destroyUserSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        
        // Clear PHPSESSID cookie from browser
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params["path"] ?? '/',
                'domain'   => $params["domain"] ?? '',
                'secure'   => $is_https,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        
        session_destroy();
    }
}

// Function to check session idle timeout (default: 30 minutes = 1800 seconds)
function checkSessionTimeout($timeoutMinutes = 30) {
    if (isset($_SESSION['user_id'])) {
        $timeoutSeconds = $timeoutMinutes * 60;
        
        // If last activity is recorded and exceeded the timeout threshold
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeoutSeconds)) {
            destroyUserSession();
            redirect('/placement-management-system/auth/login.php?session_expired=1');
        }
        
        // Update last activity timestamp on each active request
        $_SESSION['last_activity'] = time();
    }
}

// Function to check if a user is currently logged in (with timeout check)
function isLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    checkSessionTimeout(30);
    return isset($_SESSION['user_id']);
}

// Function to generate a random One Time Password (OTP)
function generateOTP($length = 6) {
    $otp = '';
    // Loop to add random digits
    for($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

// Function to validate Name (letters A-Z, a-z, and spaces only)
function validateName($name) {
    $trimmed = trim($name);
    if (empty($trimmed)) {
        return false;
    }
    // Allow only alphabets and spaces (2 to 100 characters)
    return (bool)preg_match('/^[a-zA-Z\s]{2,100}$/', $trimmed);
}

// Function to validate Indian Phone Number (10 digits starting with 6, 7, 8, or 9)
function validateIndianPhone($phone) {
    if (empty($phone)) {
        return false;
    }
    // Remove spaces, hyphens, parentheses, and leading plus sign
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    
    // Strip leading +91 or 91 country code if present with 12 digits
    if (strlen($cleaned) === 12 && substr($cleaned, 0, 2) === '91') {
        $cleaned = substr($cleaned, 2);
    }
    // Strip leading 0 if present with 11 digits
    elseif (strlen($cleaned) === 11 && substr($cleaned, 0, 1) === '0') {
        $cleaned = substr($cleaned, 1);
    }
    
    // Check if exactly 10 digits starting with 6, 7, 8, or 9
    if (preg_match('/^[6-9]\d{9}$/', $cleaned)) {
        return $cleaned; // Return cleaned 10-digit phone
    }
    return false;
}

// Function to validate Strong Password (min 8 chars, 1 uppercase, 1 lowercase, 1 digit, 1 special character)
function validateStrongPassword($password) {
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter (A-Z).";
    }
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter (a-z).";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number (0-9).";
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?~`]/', $password)) {
        return "Password must contain at least one special character (e.g. @, #, $, %, etc.).";
    }
    return true;
}

// Function to send an OTP email using PHPMailer
function sendOTPEmail($to, $otp, $type = 'verification') {
    require_once __DIR__ . '/../config/mail.php';
    require_once __DIR__ . '/PHPMailer/Exception.php';
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';
    
    $isReset = ($type === 'reset');
    $subject = $isReset ? "Password Reset Verification Code - PMS" : "Account Verification Code - PMS";
    $heading = $isReset ? "Password Reset Request" : "Account Verification";
    $message = $isReset 
        ? "We received a request to reset your password. Use the verification code below to proceed:" 
        : "Thank you for registering with Placement Management System. Use the verification code below to complete your registration:";

    // Professional, responsive HTML email template
    $htmlContent = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($subject) . '</title>
        <style>
            body { font-family: "Segoe UI", Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #333333; }
            .email-container { max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; }
            .email-header { background: linear-gradient(135deg, #4a3b7c 0%, #6c5ce7 100%); padding: 30px 25px; text-align: center; color: #ffffff; }
            .email-header h1 { margin: 0; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
            .email-body { padding: 30px 25px; }
            .email-body h2 { color: #2d3748; font-size: 18px; margin-top: 0; margin-bottom: 12px; }
            .email-body p { color: #4a5568; font-size: 15px; line-height: 1.6; margin: 0 0 16px 0; }
            .otp-box { background: #f8fafc; border: 2px dashed #6c5ce7; border-radius: 10px; padding: 18px; text-align: center; margin: 24px 0; }
            .otp-code { font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #4a3b7c; font-family: "Courier New", monospace; margin: 0; }
            .badge-timer { display: inline-block; background: #fff3cd; color: #856404; font-size: 13px; font-weight: 600; padding: 4px 12px; border-radius: 20px; margin-top: 10px; }
            .email-footer { background: #f8fafc; padding: 20px 25px; text-align: center; font-size: 12px; color: #718096; border-top: 1px solid #e5e7eb; }
            .email-footer p { margin: 4px 0; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <h1>🎓 Placement Management System</h1>
            </div>
            <div class="email-body">
                <h2>' . htmlspecialchars($heading) . '</h2>
                <p>' . htmlspecialchars($message) . '</p>
                
                <div class="otp-box">
                    <div class="otp-code">' . htmlspecialchars($otp) . '</div>
                    <div class="badge-timer">⏱️ Valid for 10 minutes only</div>
                </div>
                
                <p style="font-size: 13px; color: #718096;">If you did not make this request, please ignore this email or contact the administrator immediately. Never share your OTP with anyone.</p>
            </div>
            <div class="email-footer">
                <p>&copy; ' . date('Y') . ' Placement Management System. All rights reserved.</p>
                <p>This is an automated security email. Please do not reply directly.</p>
            </div>
        </div>
    </body>
    </html>';

    // Plain-text alternative
    $altBody = "Placement Management System\n" .
               "$heading\n\n" .
               "$message\n\n" .
               "Your Verification Code is: $otp\n" .
               "Note: This code will expire in 10 minutes.\n\n" .
               "If you did not make this request, please ignore this email.";

    // Create new PHPMailer instance
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        
        // Dynamic port and encryption detection
        if (defined('SMTP_PORT') && SMTP_PORT == 465) {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
        } else {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
        }

        // SSL options to prevent certificate handshake failures in local Windows/XAMPP environments
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ];

        // Connection timeouts to prevent page freezing
        $mail->Timeout  = 10;
        $mail->CharSet  = 'UTF-8';

        // Sender & Recipient
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress(trim($to));

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlContent;
        $mail->AltBody = $altBody;

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log("Failed to send OTP email to $to via PHPMailer. Mailer Error: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage() . " | OTP: $otp");
        return false;
    }
}

// Function to calculate Student Profile Completeness
function getStudentProfileStatus($pdo, $userId) {
    // Fetch user basic info
    $stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt_user->execute([$userId]);
    $user = $stmt_user->fetch();

    // Fetch student specific info
    $stmt_student = $pdo->prepare("SELECT * FROM students WHERE user_id = ? AND deleted_at IS NULL");
    $stmt_student->execute([$userId]);
    $student = $stmt_student->fetch();

    if (!$user) {
        return null;
    }

    $fields = [
        'name' => [
            'label' => 'Full Name',
            'filled' => !empty(trim($user['name'] ?? ''))
        ],
        'phone' => [
            'label' => 'Phone Number',
            'filled' => !empty(trim($student['phone'] ?? ''))
        ],
        'department' => [
            'label' => 'Course / Department',
            'filled' => !empty(trim($student['department'] ?? ''))
        ],
        'passing_year' => [
            'label' => 'Passing Year',
            'filled' => !empty($student['passing_year']) && (int)$student['passing_year'] > 0
        ],
        'cgpa' => [
            'label' => 'CGPA / Percentage',
            'filled' => !empty($student['cgpa']) && (float)$student['cgpa'] > 0
        ],
        'skills' => [
            'label' => 'Skills',
            'filled' => !empty(trim($student['skills'] ?? ''))
        ],
        'bio' => [
            'label' => 'Bio / Objective',
            'filled' => !empty(trim($student['bio'] ?? ''))
        ],
        'resume_url' => [
            'label' => 'Resume Document',
            'filled' => !empty(trim($student['resume_url'] ?? ''))
        ],
        'id_card_url' => [
            'label' => 'Student ID Card (I-Card)',
            'filled' => !empty(trim($student['id_card_url'] ?? ''))
        ]
    ];

    $total = count($fields);
    $filled = 0;
    $missing = [];
    $completed = [];

    foreach ($fields as $key => $data) {
        if ($data['filled']) {
            $filled++;
            $completed[] = $data['label'];
        } else {
            $missing[] = $data['label'];
        }
    }

    $percentage = ($total > 0) ? (int)round(($filled / $total) * 100) : 0;

    return [
        'is_complete' => ($filled === $total),
        'percentage' => $percentage,
        'filled_count' => $filled,
        'total_count' => $total,
        'missing_fields' => $missing,
        'completed_fields' => $completed,
        'user' => $user,
        'student' => $student
    ];
}

// Function to calculate Company Profile Completeness
function getCompanyProfileStatus($pdo, $userId) {
    // Fetch user basic info
    $stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt_user->execute([$userId]);
    $user = $stmt_user->fetch();

    // Fetch company specific info
    $stmt_company = $pdo->prepare("SELECT * FROM companies WHERE user_id = ? AND deleted_at IS NULL");
    $stmt_company->execute([$userId]);
    $company = $stmt_company->fetch();

    if (!$user) {
        return null;
    }

    $fields = [
        'name' => [
            'label' => 'Contact Person Name',
            'filled' => !empty(trim($user['name'] ?? ''))
        ],
        'company_name' => [
            'label' => 'Company Name',
            'filled' => !empty(trim($company['company_name'] ?? ''))
        ],
        'industry' => [
            'label' => 'Industry / Sector',
            'filled' => !empty(trim($company['industry'] ?? ''))
        ],
        'location' => [
            'label' => 'Location / City',
            'filled' => !empty(trim($company['location'] ?? ''))
        ],
        'website' => [
            'label' => 'Website URL',
            'filled' => !empty(trim($company['website'] ?? ''))
        ],
        'description' => [
            'label' => 'About / Company Description',
            'filled' => !empty(trim($company['description'] ?? ''))
        ]
    ];

    $total = count($fields);
    $filled = 0;
    $missing = [];
    $completed = [];

    foreach ($fields as $key => $data) {
        if ($data['filled']) {
            $filled++;
            $completed[] = $data['label'];
        } else {
            $missing[] = $data['label'];
        }
    }

    $percentage = ($total > 0) ? (int)round(($filled / $total) * 100) : 0;

    return [
        'is_complete' => ($filled === $total),
        'percentage' => $percentage,
        'filled_count' => $filled,
        'total_count' => $total,
        'missing_fields' => $missing,
        'completed_fields' => $completed,
        'user' => $user,
        'company' => $company
    ];
}

// Function to render the profile completion banner widget
function renderProfileCompletionBanner($status, $editUrl, $roleLabel = 'Profile', $blockActionMsg = '') {
    if (!$status) return;

    $percentage = $status['percentage'];
    $isComplete = $status['is_complete'];
    $missing = $status['missing_fields'];

    if ($isComplete) {
        ?>
        <div class="card profile-completion-card border-0 mb-4 shadow-sm complete-state">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div class="d-flex align-items-center">
                        <div class="completion-icon-wrapper bg-success-subtle text-success me-3">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h5 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($roleLabel); ?> is 100% Complete</h5>
                                <span class="badge bg-success rounded-pill px-3 py-1"><i class="fa-solid fa-star me-1"></i>Verified</span>
                            </div>
                            <p class="text-muted mb-0 small">All required fields are completed. Your information is visible to others.</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-end d-none d-lg-block">
                            <span class="fw-bold text-success fs-5">100%</span>
                            <div class="text-muted small">Completed</div>
                        </div>
                        <a href="<?php echo htmlspecialchars($editUrl); ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                            <i class="fa-solid fa-pen me-1"></i>Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } else {
        // Incomplete State
        $progressColorClass = ($percentage < 50) ? 'bg-danger' : 'bg-warning';
        ?>
        <div class="card profile-completion-card border-0 mb-4 shadow-sm incomplete-state">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
                    <div class="d-flex align-items-center">
                        <div class="completion-icon-wrapper bg-warning-subtle text-warning me-3">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h5 class="mb-0 fw-bold text-dark">Complete Your <?php echo htmlspecialchars($roleLabel); ?></h5>
                                <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 fw-bold"><?php echo $percentage; ?>% Completed</span>
                            </div>
                            <p class="text-muted mb-0 small">
                                <?php if (!empty($blockActionMsg)): ?>
                                    <?php echo htmlspecialchars($blockActionMsg); ?>
                                <?php else: ?>
                                    Please complete all required fields to enable full access and maximize your opportunities.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div>
                        <a href="<?php echo htmlspecialchars($editUrl); ?>" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm pulse-button">
                            <i class="fa-solid fa-user-pen me-2"></i>Complete Profile Now
                        </a>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="completion-progress-wrapper mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold text-muted">Profile Completion Status</span>
                        <span class="small fw-bold text-primary"><?php echo $status['filled_count']; ?> of <?php echo $status['total_count']; ?> required fields filled (<?php echo $percentage; ?>%)</span>
                    </div>
                    <div class="progress completion-progress" style="height: 10px; border-radius: 5px;">
                        <div class="progress-bar <?php echo $progressColorClass; ?> progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <!-- Missing Fields Checklist -->
                <?php if (!empty($missing)): ?>
                    <div class="missing-fields-box p-3 rounded-3 bg-light border">
                        <div class="small fw-bold text-danger mb-2">
                            <i class="fa-solid fa-circle-exclamation me-1"></i>Required Fields Missing (<?php echo count($missing); ?>):
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($missing as $mField): ?>
                                <span class="badge bg-white text-danger border border-danger-subtle py-1.5 px-2.5 rounded-pill shadow-xs">
                                    <i class="fa-solid fa-xmark me-1 text-danger"></i><?php echo htmlspecialchars($mField); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
?>
