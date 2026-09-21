<?php
$page_title = 'View Applicants';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and their role is company or admin
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['company', 'admin'])) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'all';
$archive_filter = isset($_GET['archive']) ? sanitize_input($_GET['archive']) : 'active';

// Verify job access
if ($user_role === 'admin') {
    $query_job = "SELECT * FROM jobs WHERE id = ?";
    $stmt = $pdo->prepare($query_job);
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();
} else {
    $query_job = "SELECT * FROM jobs WHERE id = ? AND company_id = ?";
    $stmt = $pdo->prepare($query_job);
    $stmt->execute([$job_id, $user_id]);
    $job = $stmt->fetch();
}

if (!$job) {
    $_SESSION['error_msg'] = 'Job not found or access denied.';
    redirect($user_role === 'admin' ? 'admin_dashboard.php' : 'company_dashboard.php');
}

// Build query for applicants with full student details
$query_applicants = "
    SELECT a.*, u.name, u.email, 
           s.phone, s.department, s.passing_year, s.cgpa, s.skills, s.bio, s.resume_url, s.id_card_url 
    FROM applications a 
    JOIN users u ON a.student_id = u.id 
    LEFT JOIN students s ON u.id = s.user_id 
    WHERE a.job_id = ? AND u.is_verified = 1
";
$params = [$job_id];

if ($archive_filter === 'active') {
    $query_applicants .= " AND a.deleted_at IS NULL";
} elseif ($archive_filter === 'deactivated') {
    $query_applicants .= " AND a.deleted_at IS NOT NULL";
}

if ($status_filter !== 'all') {
    $query_applicants .= " AND a.status = ?";
    $params[] = $status_filter;
}

$query_applicants .= " ORDER BY a.applied_at DESC";
$stmt = $pdo->prepare($query_applicants);
$stmt->execute($params);
$applicants = $stmt->fetchAll();

// Total counts
$total_active = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ? AND deleted_at IS NULL");
$total_active->execute([$job_id]);
$active_count = $total_active->fetchColumn();

$total_deactive = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ? AND deleted_at IS NOT NULL");
$total_deactive->execute([$job_id]);
$deactive_count = $total_deactive->fetchColumn();

include '../includes/header.php';
?>

<div class="py-4 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="page-header">
            <h2><i class="fa-solid fa-users me-2 text-primary"></i>Applicants for Position</h2>
            <p class="text-muted mb-0 small">Review candidate resumes, complete profiles, and update candidate hiring status</p>
        </div>
        <a href="<?php echo $user_role === 'admin' ? 'admin_dashboard.php' : 'company_dashboard.php'; ?>" class="btn btn-outline-secondary rounded-pill">
            <i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard
        </a>
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

    <!-- Job Details Header Card -->
    <div class="card card-static mb-4 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold text-primary mb-1"><?php echo htmlspecialchars($job['title']); ?></h4>
                    <p class="text-muted mb-0">
                        <i class="fa-solid fa-building me-1 text-success"></i><?php echo htmlspecialchars($job['company_name']); ?> &bull; 
                        <i class="fa-solid fa-location-dot me-1 text-danger"></i><?php echo htmlspecialchars($job['location']); ?> &bull; 
                        <i class="fa-solid fa-money-bill-wave me-1 text-info"></i><?php echo htmlspecialchars($job['salary']); ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-primary rounded-pill px-3 py-2 fs-6">
                        <i class="fa-solid fa-user-check me-1"></i><?php echo $active_count; ?> Active Applicants (1)
                    </span>
                    <?php if($deactive_count > 0): ?>
                        <span class="badge bg-secondary rounded-pill px-3 py-2 fs-6">
                            <i class="fa-solid fa-ban me-1"></i><?php echo $deactive_count; ?> Deactive (0)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Applicants Section with Status Filters & Deactive Filters -->
    <div class="section-card shadow-sm">
        <div class="section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fa-solid fa-list me-2 text-primary"></i>Candidate Applications (<?php echo count($applicants); ?>)</h5>
            
            <div class="d-flex gap-2 flex-wrap">
                <!-- Status Filter Dropdown / Group -->
                <div class="btn-group btn-group-sm" role="group">
                    <a href="?job_id=<?php echo $job_id; ?>&status=all&archive=<?php echo $archive_filter; ?>" class="btn <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">All Status</a>
                    <a href="?job_id=<?php echo $job_id; ?>&status=Pending&archive=<?php echo $archive_filter; ?>" class="btn <?php echo $status_filter === 'Pending' ? 'btn-warning text-dark' : 'btn-outline-secondary'; ?>">Pending</a>
                    <a href="?job_id=<?php echo $job_id; ?>&status=Reviewed&archive=<?php echo $archive_filter; ?>" class="btn <?php echo $status_filter === 'Reviewed' ? 'btn-info text-white' : 'btn-outline-secondary'; ?>">Reviewed</a>
                    <a href="?job_id=<?php echo $job_id; ?>&status=Shortlisted&archive=<?php echo $archive_filter; ?>" class="btn <?php echo $status_filter === 'Shortlisted' ? 'btn-success' : 'btn-outline-secondary'; ?>">Shortlisted</a>
                    <a href="?job_id=<?php echo $job_id; ?>&status=Selected&archive=<?php echo $archive_filter; ?>" class="btn <?php echo $status_filter === 'Selected' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Selected</a>
                    <a href="?job_id=<?php echo $job_id; ?>&status=Rejected&archive=<?php echo $archive_filter; ?>" class="btn <?php echo $status_filter === 'Rejected' ? 'btn-danger' : 'btn-outline-secondary'; ?>">Rejected</a>
                </div>

                <!-- Active / Deactivated 0 Toggle -->
                <div class="btn-group btn-group-sm" role="group">
                    <a href="?job_id=<?php echo $job_id; ?>&status=<?php echo $status_filter; ?>&archive=active" class="btn <?php echo $archive_filter === 'active' ? 'btn-success' : 'btn-outline-secondary'; ?>"><i class="fa-solid fa-circle-check me-1"></i>Active 1</a>
                    <a href="?job_id=<?php echo $job_id; ?>&status=<?php echo $status_filter; ?>&archive=deactivated" class="btn <?php echo $archive_filter === 'deactivated' ? 'btn-danger' : 'btn-outline-secondary'; ?>"><i class="fa-solid fa-ban me-1"></i>Deactive 0</a>
                </div>
            </div>
        </div>

        <div class="p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Candidate Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>CGPA</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($applicants)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted"><i class="fa-solid fa-inbox me-2"></i>No applicants found matching filter criteria.</td></tr>
                        <?php else: ?>
                            <?php $i = 1; foreach($applicants as $app): ?>
                                <?php $isAppActive = empty($app['deleted_at']); ?>
                                <tr class="<?php echo !$isAppActive ? 'table-light opacity-75' : ''; ?>">
                                    <td class="ps-4"><?php echo $i++; ?></td>
                                    <td>
                                        <a href="#" class="fw-bold text-primary text-decoration-none" data-bs-toggle="modal" data-bs-target="#applicantModal_<?php echo $app['id']; ?>">
                                            <i class="fa-solid fa-user-circle me-1"></i><?php echo htmlspecialchars($app['name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($app['email']); ?></td>
                                    <td><?php echo htmlspecialchars($app['department'] ?? 'N/A'); ?></td>
                                    <td><?php echo (!empty($app['cgpa']) && $app['cgpa'] > 0) ? htmlspecialchars($app['cgpa']) : 'N/A'; ?></td>
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
                                    <td class="text-end pe-4">
                                        <!-- View Full Info Modal Button -->
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill me-1" data-bs-toggle="modal" data-bs-target="#applicantModal_<?php echo $app['id']; ?>">
                                            <i class="fa-solid fa-id-card me-1"></i>View Full Info
                                        </button>
                                        
                                        <!-- Soft Delete / Restore Application Toggle -->
                                        <?php if($isAppActive): ?>
                                            <a href="../actions/update_application_status.php?id=<?php echo $app['id']; ?>&action=deactivate" class="btn btn-sm btn-outline-danger rounded-pill" onclick="return confirm('Deactivate (soft delete 0) this application?');" title="Deactivate">
                                                <i class="fa-solid fa-ban"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="../actions/update_application_status.php?id=<?php echo $app['id']; ?>&action=restore" class="btn btn-sm btn-success rounded-pill" onclick="return confirm('Activate (1) this application?');" title="Restore">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Complete Applicant Information Modal for Company HR -->
                                <div class="modal fade" id="applicantModal_<?php echo $app['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content border-0 shadow-lg">
                                            <div class="modal-header gradient-primary text-white">
                                                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-graduate me-2"></i>Full Applicant Details - <?php echo htmlspecialchars($app['name']); ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <!-- Top User Header -->
                                                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle me-3">
                                                            <i class="fa-solid fa-user"></i>
                                                        </div>
                                                        <div>
                                                            <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($app['name']); ?></h4>
                                                            <span class="text-muted small"><i class="fa-solid fa-envelope me-1"></i><?php echo htmlspecialchars($app['email']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <span class="badge <?php echo $badgeClass; ?> rounded-pill px-3 py-2 fs-6">
                                                            Status: <?php echo $app['status']; ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Full User Information Details Grid -->
                                                <div class="row g-3 mb-4">
                                                    <div class="col-md-6">
                                                        <div class="p-3 bg-light rounded-3">
                                                            <label class="text-muted small fw-semibold">Phone Number</label>
                                                            <p class="mb-0 fw-bold"><i class="fa-solid fa-phone me-1 text-primary"></i><?php echo !empty($app['phone']) ? htmlspecialchars($app['phone']) : 'N/A'; ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="p-3 bg-light rounded-3">
                                                            <label class="text-muted small fw-semibold">Course / Department</label>
                                                            <p class="mb-0 fw-bold"><i class="fa-solid fa-graduation-cap me-1 text-primary"></i><?php echo !empty($app['department']) ? htmlspecialchars($app['department']) : 'N/A'; ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="p-3 bg-light rounded-3">
                                                            <label class="text-muted small fw-semibold">Passing Year</label>
                                                            <p class="mb-0 fw-bold"><i class="fa-solid fa-calendar me-1 text-primary"></i><?php echo !empty($app['passing_year']) ? htmlspecialchars($app['passing_year']) : 'N/A'; ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="p-3 bg-light rounded-3">
                                                            <label class="text-muted small fw-semibold">CGPA / Percentage</label>
                                                            <p class="mb-0 fw-bold"><i class="fa-solid fa-star me-1 text-warning"></i><?php echo (!empty($app['cgpa']) && $app['cgpa'] > 0) ? htmlspecialchars($app['cgpa']) : 'N/A'; ?></p>
                                                        </div>
                                                    </div>

                                                    <!-- Technical Skills -->
                                                    <div class="col-12">
                                                        <div class="p-3 bg-light rounded-3">
                                                            <label class="text-muted small fw-semibold d-block mb-2">Technical Skills</label>
                                                            <?php if(!empty($app['skills'])): ?>
                                                                <?php foreach(explode(',', $app['skills']) as $sk): ?>
                                                                    <?php if(trim($sk)): ?>
                                                                        <span class="badge bg-white text-primary border me-1 px-3 py-2"><?php echo htmlspecialchars(trim($sk)); ?></span>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <span class="text-muted fst-italic">No skills specified</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <!-- Bio / Career Objective -->
                                                    <div class="col-12">
                                                        <div class="p-3 bg-light rounded-3">
                                                            <label class="text-muted small fw-semibold">Bio / Career Objective</label>
                                                            <p class="mb-0 text-dark"><?php echo !empty($app['bio']) ? nl2br(htmlspecialchars($app['bio'])) : 'No bio provided.'; ?></p>
                                                        </div>
                                                    </div>

                                                    <!-- Resume and ID Card Download Boxes -->
                                                    <div class="col-md-6">
                                                        <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                            <div>
                                                                <label class="text-muted small fw-semibold d-block">Resume Document</label>
                                                                <span><?php echo !empty($app['resume_url']) ? '<i class="fa-solid fa-file-pdf text-danger me-1"></i>Resume available' : 'No resume uploaded'; ?></span>
                                                            </div>
                                                            <?php if(!empty($app['resume_url'])): ?>
                                                                <a href="<?php echo htmlspecialchars($app['resume_url']); ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
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
                                                                <a href="<?php echo htmlspecialchars($app['id_card_url']); ?>" target="_blank" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm">
                                                                    <i class="fa-solid fa-eye me-1"></i>View ID Card
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Instant HR Status Updater -->
                                                <form action="../actions/update_application_status.php" method="POST" class="p-3 bg-white rounded-3 border shadow-sm">
                                                    <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                        <div>
                                                            <label class="form-label fw-bold mb-0 text-dark">
                                                                <i class="fa-solid fa-user-gear me-1 text-primary"></i>Update Candidate Status
                                                            </label>
                                                            <p class="text-muted small mb-0">Change candidate application state instantly</p>
                                                        </div>
                                                        <div class="d-flex gap-2">
                                                            <select name="status" class="form-select form-select-sm" style="min-width: 160px;">
                                                                <option value="Pending" <?php echo $app['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="Reviewed" <?php echo $app['status'] == 'Reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                                                <option value="Shortlisted" <?php echo $app['status'] == 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                                                <option value="Selected" <?php echo $app['status'] == 'Selected' ? 'selected' : ''; ?>>Selected</option>
                                                                <option value="Rejected" <?php echo $app['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Update</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <a href="student_profile.php?id=<?php echo $app['student_id']; ?>&job_id=<?php echo $job_id; ?>" class="btn btn-outline-primary rounded-pill">
                                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Open Dedicated Profile Page
                                                </a>
                                                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>