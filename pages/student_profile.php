<?php
$page_title = 'Applicant Profile';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is company or admin
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['company', 'admin'])) {
    redirect('../auth/login.php');
}

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
$user_role = $_SESSION['user_role'];
$current_user_id = $_SESSION['user_id'];

// Fetch student data (Admin can view even if deactivated)
if ($user_role === 'admin') {
    $query_user = "SELECT * FROM users WHERE id = ? AND role = 'student'";
} else {
    $query_user = "SELECT * FROM users WHERE id = ? AND role = 'student' AND deleted_at IS NULL";
}
$stmt = $pdo->prepare($query_user);
$stmt->execute([$student_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error_msg'] = "Student not found or access denied.";
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    safe_redirect($referer, '../index.php');
}

// Fetch student academic data
$query_student = "SELECT * FROM students WHERE user_id = ?";
$stmt = $pdo->prepare($query_student);
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Check if there is an application associated with a job
$application = null;
if ($job_id > 0) {
    if ($user_role === 'company') {
        $stmt_app = $pdo->prepare("
            SELECT a.*, j.title as job_title 
            FROM applications a 
            JOIN jobs j ON a.job_id = j.id 
            WHERE a.job_id = ? AND a.student_id = ? AND j.company_id = ?
        ");
        $stmt_app->execute([$job_id, $student_id, $current_user_id]);
    } else {
        $stmt_app = $pdo->prepare("
            SELECT a.*, j.title as job_title 
            FROM applications a 
            JOIN jobs j ON a.job_id = j.id 
            WHERE a.job_id = ? AND a.student_id = ?
        ");
        $stmt_app->execute([$job_id, $student_id]);
    }
    $application = $stmt_app->fetch();
}

$isActive = empty($user['deleted_at']);

include '../includes/header.php';
?>

<div class="py-4 fade-in">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div class="page-header">
                    <h2><i class="fa-solid fa-user-graduate me-2 text-primary"></i>Candidate Profile</h2>
                    <p class="text-muted mb-0 small">Full student background, academic history, skills, and resume details</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if($job_id > 0): ?>
                        <a href="view_applicants.php?job_id=<?php echo $job_id; ?>" class="btn btn-outline-secondary rounded-pill">
                            <i class="fa-solid fa-arrow-left me-2"></i>Back to Applicants
                        </a>
                    <?php else: ?>
                        <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill">
                            <i class="fa-solid fa-arrow-left me-2"></i>Back
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if(isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm">
                    <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error_msg'])): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card card-static mb-4 shadow-sm">
                <div class="card-body p-4">
                    <!-- Top Avatar & Name Info -->
                    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle me-3 shadow-sm">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div>
                                <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars($user['name']); ?></h3>
                                <p class="text-muted mb-0"><i class="fa-solid fa-envelope me-2 text-primary"></i><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if($isActive): ?>
                                <span class="badge bg-success rounded-pill px-3 py-2"><i class="fa-solid fa-circle-check me-1"></i>Active (1)</span>
                            <?php else: ?>
                                <span class="badge bg-danger rounded-pill px-3 py-2"><i class="fa-solid fa-ban me-1"></i>Deactivated (0)</span>
                            <?php endif; ?>
                            
                            <?php if(!empty($student['resume_url'])): ?>
                                <a href="<?php echo htmlspecialchars($student['resume_url']); ?>" target="_blank" class="btn btn-primary rounded-pill px-3 shadow-sm">
                                    <i class="fa-solid fa-download me-1"></i>Resume
                                </a>
                            <?php endif; ?>

                            <?php if(!empty($student['id_card_url'])): ?>
                                <a href="<?php echo htmlspecialchars($student['id_card_url']); ?>" target="_blank" class="btn btn-success rounded-pill px-3 shadow-sm">
                                    <i class="fa-solid fa-id-card me-1"></i>Student ID Card
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Application Context Card if applying to job -->
                    <?php if($application): ?>
                        <div class="p-3 bg-light rounded-3 border mb-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <span class="text-muted small fw-semibold d-block">Application For Position</span>
                                    <h5 class="mb-0 fw-bold text-primary"><?php echo htmlspecialchars($application['job_title']); ?></h5>
                                    <small class="text-muted">Applied on <?php echo date('M d, Y', strtotime($application['applied_at'])); ?></small>
                                </div>
                                <form action="../actions/update_application_status.php" method="POST" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                    <label class="small fw-bold text-muted mb-0">Hiring Status:</label>
                                    <select name="status" class="form-select form-select-sm" style="min-width: 140px;">
                                        <option value="Pending" <?php echo $application['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Reviewed" <?php echo $application['status'] == 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                        <option value="Shortlisted" <?php echo $application['status'] == 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                        <option value="Selected" <?php echo $application['status'] == 'Selected' ? 'selected' : ''; ?>>Selected</option>
                                        <option value="Rejected" <?php echo $application['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Save</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Information Grid -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <label class="text-muted small fw-semibold">Course / Department</label>
                                <p class="fw-bold mb-0"><i class="fa-solid fa-graduation-cap me-2 text-primary"></i><?php echo htmlspecialchars($student['department'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <label class="text-muted small fw-semibold">Passing Year</label>
                                <p class="fw-bold mb-0"><i class="fa-solid fa-calendar-alt me-2 text-primary"></i><?php echo htmlspecialchars($student['passing_year'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <label class="text-muted small fw-semibold">CGPA / Percentage</label>
                                <p class="fw-bold mb-0"><i class="fa-solid fa-star me-2 text-warning"></i><?php echo (!empty($student['cgpa']) && $student['cgpa'] > 0) ? htmlspecialchars($student['cgpa']) : 'N/A'; ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <label class="text-muted small fw-semibold">Phone Number</label>
                                <p class="fw-bold mb-0"><i class="fa-solid fa-phone me-2 text-primary"></i><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></p>
                            </div>
                        </div>

                        <!-- Documents Section -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <label class="text-muted small fw-semibold d-block">Resume Document</label>
                                    <span><?php echo !empty($student['resume_url']) ? '<i class="fa-solid fa-file-pdf text-danger me-1"></i>Resume Available' : 'No Resume Uploaded'; ?></span>
                                </div>
                                <?php if(!empty($student['resume_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($student['resume_url']); ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3">
                                        <i class="fa-solid fa-download me-1"></i>Download
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <label class="text-muted small fw-semibold d-block">Student ID Card (I-Card)</label>
                                    <span><?php echo !empty($student['id_card_url']) ? '<i class="fa-solid fa-id-card text-success me-1"></i>ID Card Available' : 'No ID Card Uploaded'; ?></span>
                                </div>
                                <?php if(!empty($student['id_card_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($student['id_card_url']); ?>" target="_blank" class="btn btn-sm btn-success rounded-pill px-3">
                                        <i class="fa-solid fa-eye me-1"></i>View ID Card
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3">
                                <label class="text-muted small fw-semibold d-block mb-2">Technical Skills</label>
                                <?php if(!empty($student['skills'])): ?>
                                    <?php 
                                        $skills = explode(',', $student['skills']);
                                        foreach($skills as $skill) {
                                            if (trim($skill)) {
                                                echo '<span class="badge bg-white text-primary border me-1 mb-1 px-3 py-2 fs-6">' . htmlspecialchars(trim($skill)) . '</span>';
                                            }
                                        }
                                    ?>
                                <?php else: ?>
                                    <p class="text-muted fst-italic mb-0">No technical skills specified</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3">
                                <label class="text-muted small fw-semibold">Bio / Career Objective</label>
                                <div class="mt-1">
                                    <p class="mb-0 text-dark"><?php echo nl2br(htmlspecialchars($student['bio'] ?? 'No bio provided.')); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

