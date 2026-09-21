<?php
$page_title = 'Company Dashboard';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and their role is company
if (!isLoggedIn() || $_SESSION['user_role'] !== 'company') {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get Company Profile Status
$company_status = getCompanyProfileStatus($pdo, $user_id);

// Ensure user is verified
if (!$company_status || empty($company_status['user']) || !$company_status['user']['is_verified']) {
    $_SESSION['verify_email'] = $company_status['user']['email'] ?? '';
    $_SESSION['error_msg'] = "Please verify your email with OTP before accessing the company dashboard.";
    redirect('../auth/verify_otp.php');
}

$job_filter = isset($_GET['job_filter']) ? sanitize_input($_GET['job_filter']) : 'all';

// --- Get Company Statistics ---

// Count total number of jobs posted by this company
$active_jobs = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ? AND deleted_at IS NULL");
$active_jobs->execute([$user_id]);
$total_active_jobs = $active_jobs->fetchColumn();

$deactive_jobs = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ? AND deleted_at IS NOT NULL");
$deactive_jobs->execute([$user_id]);
$total_deactive_jobs = $deactive_jobs->fetchColumn();

$total_jobs = $total_active_jobs + $total_deactive_jobs;

// Count total number of applicants for this company's jobs
$query_total_applicants = "
    SELECT COUNT(a.id) 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.company_id = ? AND a.deleted_at IS NULL AND j.deleted_at IS NULL
";
$stmt = $pdo->prepare($query_total_applicants);
$stmt->execute([$user_id]);
$total_applicants = $stmt->fetchColumn();

// Fetch jobs based on filter
$jobs_query = "SELECT * FROM jobs WHERE company_id = ?";
$params = [$user_id];
if ($job_filter === 'active') {
    $jobs_query .= " AND deleted_at IS NULL";
} elseif ($job_filter === 'deactivated') {
    $jobs_query .= " AND deleted_at IS NOT NULL";
}
$jobs_query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($jobs_query);
$stmt->execute($params);
$company_jobs = $stmt->fetchAll();

// Fetch 5 most recent applicants across all company jobs
$recent_applicants_query = "
    SELECT a.*, u.name as student_name, u.email as student_email, j.title as job_title,
           s.department, s.cgpa, s.phone, s.skills, s.bio, s.resume_url, s.id_card_url, s.passing_year
    FROM applications a
    JOIN users u ON a.student_id = u.id
    JOIN jobs j ON a.job_id = j.id
    LEFT JOIN students s ON u.id = s.user_id
    WHERE j.company_id = ? AND a.deleted_at IS NULL AND u.is_verified = 1
    ORDER BY a.applied_at DESC LIMIT 5
";
$stmt_app = $pdo->prepare($recent_applicants_query);
$stmt_app->execute([$user_id]);
$recent_applicants = $stmt_app->fetchAll();

include '../includes/header.php';
?>

<div class="py-4 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="page-header">
            <h2><i class="fa-solid fa-gauge me-2"></i>Company Dashboard</h2>
            <p class="text-muted mb-0 small">Manage your posted jobs, applications, and soft-delete states (Active 1 / Deactive 0)</p>
        </div>
        <div>
            <a href="company_profile.php" class="btn btn-outline-primary rounded-pill me-2"><i class="fa-solid fa-building me-2"></i>Company Profile</a>
            <a href="post_job.php" class="btn btn-primary rounded-pill"><i class="fa-solid fa-plus me-2"></i>Post New Job</a>
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

    <?php if(isset($_SESSION['profile_warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-warning">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <i class="fa-solid fa-triangle-exclamation me-2 fs-5 text-warning"></i>
                    <strong>Action Required:</strong> <?php echo $_SESSION['profile_warning']; unset($_SESSION['profile_warning']); ?>
                </div>
                <a href="edit_company_profile.php" class="btn btn-sm btn-warning rounded-pill fw-bold ms-3">Complete Profile</a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Profile Completion Banner -->
    <?php renderProfileCompletionBanner($company_status, 'edit_company_profile.php', 'Company Profile', 'All company profile details are required to attract top candidates and post jobs.'); ?>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card gradient-primary text-white h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Posted Jobs</div>
                        <div class="stat-value"><?php echo $total_jobs; ?></div>
                        <div class="small mt-1 opacity-75">
                            <span class="badge bg-white text-dark rounded-pill"><?php echo $total_active_jobs; ?> Active (1)</span>
                            <span class="badge bg-dark bg-opacity-25 rounded-pill"><?php echo $total_deactive_jobs; ?> Deactive (0)</span>
                        </div>
                    </div>
                    <i class="fa-solid fa-briefcase stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card gradient-success text-white h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Applicants</div>
                        <div class="stat-value"><?php echo $total_applicants; ?></div>
                        <div class="small mt-1 opacity-75">Candidates applied</div>
                    </div>
                    <i class="fa-solid fa-users stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card <?php echo ($company_status && $company_status['is_complete']) ? 'gradient-success' : 'gradient-warning'; ?> text-white h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Profile Status</div>
                        <div class="stat-value" style="font-size: 1.4rem;">
                            <?php echo $company_status ? $company_status['percentage'] . '% Complete' : 'Incomplete'; ?>
                        </div>
                        <div class="small mt-1 opacity-75"><?php echo ($company_status && $company_status['is_complete']) ? 'Verified Profile' : 'Complete all fields'; ?></div>
                    </div>
                    <i class="fa-solid <?php echo ($company_status && $company_status['is_complete']) ? 'fa-building-circle-check' : 'fa-building-circle-exclamation'; ?> stat-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Company Jobs Section with Soft Delete (Active 1 / Deactive 0) -->
    <div class="section-card shadow-sm mb-4">
        <div class="section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fa-solid fa-list me-2 text-primary"></i>My Job Postings (<?php echo count($company_jobs); ?>)</h5>
            <!-- Filter buttons -->
            <div class="btn-group btn-group-sm" role="group">
                <a href="?job_filter=all" class="btn <?php echo $job_filter === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">All Jobs (<?php echo $total_jobs; ?>)</a>
                <a href="?job_filter=active" class="btn <?php echo $job_filter === 'active' ? 'btn-success' : 'btn-outline-secondary'; ?>"><i class="fa-solid fa-circle-check me-1"></i>Active 1 (<?php echo $total_active_jobs; ?>)</a>
                <a href="?job_filter=deactivated" class="btn <?php echo $job_filter === 'deactivated' ? 'btn-danger' : 'btn-outline-secondary'; ?>"><i class="fa-solid fa-ban me-1"></i>Deactive 0 (<?php echo $total_deactive_jobs; ?>)</a>
            </div>
        </div>
        <div class="p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Job Title</th>
                            <th>Location</th>
                            <th>Approval</th>
                            <th>Status (Active/Deactive)</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($company_jobs)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted"><i class="fa-solid fa-inbox me-2"></i>No jobs found under "<?php echo htmlspecialchars($job_filter); ?>" filter.</td></tr>
                        <?php else: ?>
                            <?php foreach($company_jobs as $job): ?>
                                <?php $isJobActive = empty($job['deleted_at']); ?>
                                <tr class="<?php echo !$isJobActive ? 'table-light opacity-75' : ''; ?>">
                                    <td class="ps-4 fw-semibold"><?php echo htmlspecialchars($job['title']); ?></td>
                                    <td><?php echo htmlspecialchars($job['location']); ?></td>
                                    <td>
                                        <?php 
                                            $appClass = 'bg-warning text-dark';
                                            if($job['approval_status'] == 'Approved') $appClass = 'bg-success';
                                            elseif($job['approval_status'] == 'Rejected') $appClass = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $appClass; ?> rounded-pill px-3"><?php echo $job['approval_status']; ?></span>
                                    </td>
                                    <td>
                                        <?php if($isJobActive): ?>
                                            <span class="badge bg-success rounded-pill px-3 py-1"><i class="fa-solid fa-circle-check me-1"></i>Active (1)</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill px-3 py-1"><i class="fa-solid fa-ban me-1"></i>Deactivated (0)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="view_applicants.php?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fa-solid fa-users me-1"></i>Applicants</a>
                                        <?php if($isJobActive): ?>
                                            <a href="../actions/delete_job.php?id=<?php echo $job['id']; ?>&action=deactivate" class="btn btn-sm btn-outline-danger rounded-pill ms-1" onclick="return confirm('Deactivate (soft delete 0) this job? Candidates will no longer see it.');">
                                                <i class="fa-solid fa-ban me-1"></i>Deactivate (0)
                                            </a>
                                        <?php else: ?>
                                            <a href="../actions/delete_job.php?id=<?php echo $job['id']; ?>&action=restore" class="btn btn-sm btn-success rounded-pill ms-1" onclick="return confirm('Activate (1) this job?');">
                                                <i class="fa-solid fa-rotate-left me-1"></i>Activate (1)
                                            </a>
                                            <a href="../actions/delete_job.php?id=<?php echo $job['id']; ?>&action=hard_delete" class="btn btn-sm btn-danger rounded-pill ms-1" onclick="return confirm('⚠️ PERMANENT DELETE WARNING:\n\nAre you sure you want to permanently delete this job from the database?\nAll applications for this job will be deleted forever.\n\nThis action CANNOT be undone!');">
                                                <i class="fa-solid fa-trash-can me-1"></i>Hard Delete
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Applicants Quick Overview with Full Information Modal -->
    <?php if(!empty($recent_applicants)): ?>
    <div class="section-card shadow-sm">
        <div class="section-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa-solid fa-user-clock me-2 text-success"></i>Recent Applicants (Click to view full user information)</h5>
        </div>
        <div class="p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Candidate Name</th>
                            <th>Applied Job</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_applicants as $app): ?>
                            <tr>
                                <td class="ps-4">
                                    <a href="#" class="fw-bold text-dark text-decoration-none" data-bs-toggle="modal" data-bs-target="#appModal_<?php echo $app['id']; ?>">
                                        <i class="fa-solid fa-user-circle me-1 text-primary"></i><?php echo htmlspecialchars($app['student_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                                <td><?php echo htmlspecialchars($app['department'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                        $badgeClass = 'bg-secondary';
                                        if($app['status'] == 'Shortlisted') $badgeClass = 'bg-success';
                                        elseif($app['status'] == 'Selected') $badgeClass = 'bg-primary';
                                        elseif($app['status'] == 'Rejected') $badgeClass = 'bg-danger';
                                        elseif($app['status'] == 'Reviewed') $badgeClass = 'bg-info';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?> rounded-pill px-3"><?php echo $app['status']; ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#appModal_<?php echo $app['id']; ?>">
                                        <i class="fa-solid fa-id-card me-1"></i>View Full Info
                                    </button>
                                </td>
                            </tr>

                            <!-- Candidate Details Modal -->
                            <div class="modal fade" id="appModal_<?php echo $app['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content border-0 shadow-lg">
                                        <div class="modal-header gradient-primary text-white">
                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-graduate me-2"></i>Candidate Profile - <?php echo htmlspecialchars($app['student_name']); ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-3">
                                                        <i class="fa-solid fa-user"></i>
                                                    </div>
                                                    <div>
                                                        <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($app['student_name']); ?></h4>
                                                        <span class="text-muted small"><i class="fa-solid fa-envelope me-1"></i><?php echo htmlspecialchars($app['student_email']); ?></span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <span class="badge <?php echo $badgeClass; ?> rounded-pill px-3 py-2 fs-6">
                                                        Status: <?php echo $app['status']; ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <div class="p-3 bg-light rounded-3">
                                                        <label class="text-muted small fw-semibold">Applied Position</label>
                                                        <p class="mb-0 fw-bold text-primary"><?php echo htmlspecialchars($app['job_title']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="p-3 bg-light rounded-3">
                                                        <label class="text-muted small fw-semibold">Phone Number</label>
                                                        <p class="mb-0 fw-bold"><?php echo !empty($app['phone']) ? htmlspecialchars($app['phone']) : 'N/A'; ?></p>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="p-3 bg-light rounded-3">
                                                        <label class="text-muted small fw-semibold">Course / Department</label>
                                                        <p class="mb-0 fw-bold"><?php echo !empty($app['department']) ? htmlspecialchars($app['department']) : 'N/A'; ?></p>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="p-3 bg-light rounded-3">
                                                        <label class="text-muted small fw-semibold">Passing Year</label>
                                                        <p class="mb-0 fw-bold"><?php echo !empty($app['passing_year']) ? htmlspecialchars($app['passing_year']) : 'N/A'; ?></p>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="p-3 bg-light rounded-3">
                                                        <label class="text-muted small fw-semibold">CGPA</label>
                                                        <p class="mb-0 fw-bold"><?php echo (!empty($app['cgpa']) && $app['cgpa'] > 0) ? htmlspecialchars($app['cgpa']) : 'N/A'; ?></p>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="p-3 bg-light rounded-3">
                                                        <label class="text-muted small fw-semibold d-block mb-1">Skills</label>
                                                        <?php if(!empty($app['skills'])): ?>
                                                            <?php foreach(explode(',', $app['skills']) as $sk): ?>
                                                                <?php if(trim($sk)): ?>
                                                                    <span class="badge bg-white text-primary border me-1 px-2.5 py-1.5"><?php echo htmlspecialchars(trim($sk)); ?></span>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted fst-italic">No skills listed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="p-3 bg-light rounded-3">
                                                        <label class="text-muted small fw-semibold">Bio / Objective</label>
                                                        <p class="mb-0"><?php echo !empty($app['bio']) ? nl2br(htmlspecialchars($app['bio'])) : 'No bio provided.'; ?></p>
                                                    </div>
                                                </div>
                                                 <div class="col-md-6">
                                                     <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                         <div>
                                                             <label class="text-muted small fw-semibold d-block">Resume Document</label>
                                                             <span><?php echo !empty($app['resume_url']) ? '<i class="fa-solid fa-file-pdf text-danger me-1"></i>Resume file available' : 'No resume file uploaded'; ?></span>
                                                         </div>
                                                         <?php if(!empty($app['resume_url'])): ?>
                                                             <a href="<?php echo htmlspecialchars($app['resume_url']); ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3">
                                                                 <i class="fa-solid fa-download me-1"></i>Resume
                                                             </a>
                                                         <?php endif; ?>
                                                     </div>
                                                 </div>
                                                 <div class="col-md-6">
                                                     <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                         <div>
                                                             <label class="text-muted small fw-semibold d-block">Student ID Card (I-Card)</label>
                                                             <span><?php echo !empty($app['id_card_url']) ? '<i class="fa-solid fa-id-card text-success me-1"></i>ID card available' : 'No ID card uploaded'; ?></span>
                                                         </div>
                                                         <?php if(!empty($app['id_card_url'])): ?>
                                                             <a href="<?php echo htmlspecialchars($app['id_card_url']); ?>" target="_blank" class="btn btn-sm btn-success rounded-pill px-3">
                                                                 <i class="fa-solid fa-eye me-1"></i>View ID Card
                                                             </a>
                                                         <?php endif; ?>
                                                     </div>
                                                 </div>
                                             </div>

                                            <!-- HR Update Status Form -->
                                            <form action="../actions/update_application_status.php" method="POST" class="p-3 bg-light rounded-3 border">
                                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                <label class="form-label fw-bold small text-primary mb-2"><i class="fa-solid fa-pen-to-square me-1"></i>Update Hiring Status</label>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <select name="status" class="form-select form-select-sm" style="max-width: 200px;">
                                                        <option value="Pending" <?php echo $app['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="Reviewed" <?php echo $app['status'] == 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                                        <option value="Shortlisted" <?php echo $app['status'] == 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                                        <option value="Selected" <?php echo $app['status'] == 'Selected' ? 'selected' : ''; ?>>Selected</option>
                                                        <option value="Rejected" <?php echo $app['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Update Status</button>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="modal-footer bg-light">
                                            <a href="student_profile.php?id=<?php echo $app['student_id']; ?>" class="btn btn-outline-primary rounded-pill">Open Full Profile Page</a>
                                            <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
