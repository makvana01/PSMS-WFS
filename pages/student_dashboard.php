<?php
$page_title = 'Student Dashboard';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and their role is student
if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get Student Profile Status
$profile_status = getStudentProfileStatus($pdo, $user_id);

// Ensure user has verified email OTP
if (!$profile_status || empty($profile_status['user']) || !$profile_status['user']['is_verified']) {
    $_SESSION['verify_email'] = $profile_status['user']['email'] ?? '';
    $_SESSION['error_msg'] = "Please verify your email with OTP before accessing the dashboard.";
    redirect('../auth/verify_otp.php');
}

// Check Admin I-Card verification status
$stmt_st_doc = $pdo->prepare("SELECT is_verified, id_card_url, resume_url FROM students WHERE user_id = ?");
$stmt_st_doc->execute([$user_id]);
$st_doc = $stmt_st_doc->fetch(PDO::FETCH_ASSOC);
$is_admin_verified = !empty($st_doc['is_verified']);

// --- Get Student Statistics ---

// Count total applications submitted by this student
$query_total_applications = "SELECT COUNT(*) FROM applications WHERE student_id = ? AND deleted_at IS NULL";
$stmt = $pdo->prepare($query_total_applications);
$stmt->execute([$user_id]);
$total_applications = $stmt->fetchColumn();

// Count how many of their applications are shortlisted
$query_shortlisted = "SELECT COUNT(*) FROM applications WHERE student_id = ? AND status = 'Shortlisted' AND deleted_at IS NULL";
$stmt = $pdo->prepare($query_shortlisted);
$stmt->execute([$user_id]);
$shortlisted = $stmt->fetchColumn();

// Fetch the student's 5 most recent applications along with job details
$query_recent_apps = "
    SELECT a.*, j.title, j.company_name 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE a.student_id = ? AND a.deleted_at IS NULL AND j.deleted_at IS NULL
    ORDER BY a.applied_at DESC LIMIT 5
";
$stmt = $pdo->prepare($query_recent_apps);
$stmt->execute([$user_id]);
$recent_applications = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="py-4 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="page-header">
            <h2><i class="fa-solid fa-gauge me-2"></i>Student Dashboard</h2>
        </div>
        <div>
            <a href="profile.php" class="btn btn-outline-primary rounded-pill me-2"><i class="fa-solid fa-user me-2"></i>My Profile</a>
            <a href="jobs.php" class="btn btn-primary rounded-pill"><i class="fa-solid fa-magnifying-glass me-2"></i>Find Jobs</a>
        </div>
    </div>

    <?php if(isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['profile_warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-warning">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <i class="fa-solid fa-triangle-exclamation me-2 fs-5 text-warning"></i>
                    <strong>Action Required:</strong> <?php echo $_SESSION['profile_warning']; unset($_SESSION['profile_warning']); ?>
                </div>
                <a href="edit_profile.php" class="btn btn-sm btn-warning rounded-pill fw-bold ms-3">Complete Profile</a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Profile Completion Banner -->
    <?php renderProfileCompletionBanner($profile_status, 'edit_profile.php', 'Student Profile', 'All profile fields are required before you can apply for jobs.'); ?>

    <!-- Admin I-Card Document Verification Banner -->
    <?php if($is_admin_verified): ?>
        <div class="alert alert-success d-flex align-items-center justify-content-between p-3 rounded-4 mb-4 shadow-sm border-0 bg-success bg-opacity-10 text-success">
            <div>
                <i class="fa-solid fa-shield-check me-2 fs-5 text-success"></i>
                <strong>Institutional Verification Active:</strong> Your College ID Card (I-Card) and Resume have been checked & approved by the Placement Administrator.
            </div>
            <span class="badge bg-success rounded-pill px-3 py-1.5"><i class="fa-solid fa-circle-check me-1"></i>Verified Student</span>
        </div>
    <?php else: ?>
        <div class="alert alert-warning d-flex align-items-center justify-content-between p-3 rounded-4 mb-4 shadow-sm border-0 bg-warning bg-opacity-10 text-dark">
            <div>
                <i class="fa-solid fa-clock me-2 fs-5 text-warning"></i>
                <strong>I-Card Verification Pending:</strong> Your uploaded College ID Card (I-Card) and Resume are pending review by the Placement Administrator.
            </div>
            <span class="badge bg-warning text-dark rounded-pill px-3 py-1.5"><i class="fa-solid fa-hourglass-half me-1"></i>Unverified (Under Review)</span>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card gradient-primary text-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Applications</div>
                        <div class="stat-value"><?php echo $total_applications; ?></div>
                    </div>
                    <i class="fa-solid fa-paper-plane stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card gradient-success text-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Shortlisted</div>
                        <div class="stat-value"><?php echo $shortlisted; ?></div>
                    </div>
                    <i class="fa-solid fa-check-circle stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card <?php echo ($profile_status && $profile_status['is_complete']) ? 'gradient-success' : 'gradient-warning'; ?> text-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Profile Status</div>
                        <div class="stat-value" style="font-size: 1.4rem;">
                            <?php echo $profile_status ? $profile_status['percentage'] . '% Complete' : 'Incomplete'; ?>
                        </div>
                    </div>
                    <i class="fa-solid <?php echo ($profile_status && $profile_status['is_complete']) ? 'fa-user-check' : 'fa-user-clock'; ?> stat-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header">
            <h5><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Recent Applications</h5>
        </div>
        <div class="p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Job Title</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($recent_applications)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted"><i class="fa-solid fa-inbox me-2"></i>No applications found. Start applying!</td></tr>
                        <?php else: ?>
                            <?php foreach($recent_applications as $app): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold"><?php echo htmlspecialchars($app['title']); ?></td>
                                    <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                    <td>
                                        <?php 
                                            $badgeClass = 'bg-secondary';
                                            if($app['status'] == 'Shortlisted') $badgeClass = 'bg-success';
                                            elseif($app['status'] == 'Selected') $badgeClass = 'bg-primary';
                                            elseif($app['status'] == 'Rejected') $badgeClass = 'bg-danger';
                                            elseif($app['status'] == 'Reviewed') $badgeClass = 'bg-info';
                                            elseif($app['status'] == 'Pending') $badgeClass = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?> rounded-pill px-3"><?php echo $app['status']; ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
