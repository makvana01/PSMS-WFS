<?php
$page_title = 'My Profile';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// If company, redirect to company profile
if ($role === 'company') {
    redirect('company_profile.php');
}

// Get basic user data from users table
$query_user = "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL";
$stmt = $pdo->prepare($query_user);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get student profile status & data
$profile_status = getStudentProfileStatus($pdo, $user_id);
$profile_data = $profile_status['student'] ?? null;

include '../includes/header.php';
?>

<div class="py-4 fade-in">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="page-header">
                    <h2><i class="fa-solid fa-user me-2"></i>My Profile</h2>
                </div>
                <div>
                    <a href="student_dashboard.php" class="btn btn-outline-secondary rounded-pill me-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
                    <a href="edit_profile.php" class="btn btn-primary rounded-pill"><i class="fa-solid fa-pen me-2"></i>Edit Profile</a>
                </div>
            </div>

            <?php if(isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Completion Banner -->
            <?php renderProfileCompletionBanner($profile_status, 'edit_profile.php', 'Student Profile', 'All profile fields are required before you can apply for jobs.'); ?>

            <div class="card card-static mb-4 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle me-3">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div>
                                <h4 class="mb-0 fw-bold"><?php echo htmlspecialchars($user['name']); ?></h4>
                                <span class="badge bg-primary rounded-pill px-3 text-capitalize"><?php echo $role; ?></span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if(!empty($profile_data['resume_url'])): ?>
                                <a href="<?php echo htmlspecialchars($profile_data['resume_url']); ?>" target="_blank" class="btn btn-outline-primary rounded-pill px-3">
                                    <i class="fa-solid fa-file-pdf me-2"></i>Resume
                                </a>
                            <?php endif; ?>
                            <?php if(!empty($profile_data['id_card_url'])): ?>
                                <a href="<?php echo htmlspecialchars($profile_data['id_card_url']); ?>" target="_blank" class="btn btn-outline-success rounded-pill px-3">
                                    <i class="fa-solid fa-id-card me-2"></i>Student ID Card
                                </a>
                            <?php endif; ?>
                            <?php if(empty($profile_data['resume_url']) || empty($profile_data['id_card_url'])): ?>
                                <a href="edit_profile.php" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                    <i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload Missing Documents
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Email</label>
                            <p class="fw-semibold mb-0"><i class="fa-solid fa-envelope me-2 text-primary"></i><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Phone Number</label>
                            <p class="fw-semibold mb-0">
                                <i class="fa-solid fa-phone me-2 text-primary"></i>
                                <?php echo !empty($profile_data['phone']) ? htmlspecialchars($profile_data['phone']) : '<span class="text-danger small fst-italic">Missing (Required)</span>'; ?>
                            </p>
                        </div>
                    </div>

                    <hr>
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-graduation-cap me-2 text-primary"></i>Academic Details</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="text-muted small">Course / Department</label>
                            <p class="fw-semibold mb-0">
                                <?php echo !empty($profile_data['department']) ? htmlspecialchars($profile_data['department']) : '<span class="text-danger small fst-italic">Missing (Required)</span>'; ?>
                            </p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="text-muted small">Passing Year</label>
                            <p class="fw-semibold mb-0">
                                <?php echo !empty($profile_data['passing_year']) ? htmlspecialchars($profile_data['passing_year']) : '<span class="text-danger small fst-italic">Missing (Required)</span>'; ?>
                            </p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="text-muted small">CGPA / Percentage</label>
                            <p class="fw-semibold mb-0">
                                <?php echo (!empty($profile_data['cgpa']) && (float)$profile_data['cgpa'] > 0) ? htmlspecialchars($profile_data['cgpa']) : '<span class="text-danger small fst-italic">Missing (Required)</span>'; ?>
                            </p>
                        </div>
                    </div>

                    <hr>
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-folder-open me-2 text-primary"></i>Required Verification Documents</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small d-block">Resume Document <span class="text-danger">*</span></span>
                                    <?php if(!empty($profile_data['resume_url'])): ?>
                                        <span class="fw-semibold text-success small"><i class="fa-solid fa-circle-check me-1"></i>Uploaded</span>
                                    <?php else: ?>
                                        <span class="text-danger small fst-italic"><i class="fa-solid fa-circle-xmark me-1"></i>Missing (Required)</span>
                                    <?php endif; ?>
                                </div>
                                <?php if(!empty($profile_data['resume_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($profile_data['resume_url']); ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3">
                                        <i class="fa-solid fa-file-pdf me-1"></i>View
                                    </a>
                                <?php else: ?>
                                    <a href="edit_profile.php" class="btn btn-sm btn-outline-danger rounded-pill px-3">Upload</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small d-block">Student ID Card (I-Card) <span class="text-danger">*</span></span>
                                    <?php if(!empty($profile_data['id_card_url'])): ?>
                                        <span class="fw-semibold text-success small"><i class="fa-solid fa-circle-check me-1"></i>Uploaded</span>
                                    <?php else: ?>
                                        <span class="text-danger small fst-italic"><i class="fa-solid fa-circle-xmark me-1"></i>Missing (Required)</span>
                                    <?php endif; ?>
                                </div>
                                <?php if(!empty($profile_data['id_card_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($profile_data['id_card_url']); ?>" target="_blank" class="btn btn-sm btn-success rounded-pill px-3">
                                        <i class="fa-solid fa-id-card me-1"></i>View
                                    </a>
                                <?php else: ?>
                                    <a href="edit_profile.php" class="btn btn-sm btn-outline-danger rounded-pill px-3">Upload</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-code me-2 text-primary"></i>Skills & Bio</h5>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="text-muted small d-block mb-2">Technical Skills</label>
                            <?php if(!empty($profile_data['skills'])): ?>
                                <?php 
                                    $skills = explode(',', $profile_data['skills']);
                                    foreach($skills as $skill) {
                                        if(trim($skill) !== '') {
                                            echo '<span class="badge bg-light text-dark border me-1 mb-1 px-3 py-2">' . htmlspecialchars(trim($skill)) . '</span>';
                                        }
                                    }
                                ?>
                            <?php else: ?>
                                <span class="text-danger small fst-italic">No skills listed (Required) - <a href="edit_profile.php">Add skills</a></span>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 mb-2">
                            <label class="text-muted small">Bio / Career Objective</label>
                            <div class="p-3 bg-light rounded-3 mt-1">
                                <p class="mb-0 text-dark">
                                    <?php echo !empty($profile_data['bio']) ? nl2br(htmlspecialchars($profile_data['bio'])) : '<span class="text-danger small fst-italic">No bio provided (Required) - <a href="edit_profile.php">Add bio</a></span>'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>