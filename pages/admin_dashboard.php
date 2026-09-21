<?php
$page_title = 'Admin Dashboard';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and their role is admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../auth/login.php');
}

// Active Tab parameter (default: students)
$active_tab = isset($_GET['tab']) ? sanitize_input($_GET['tab']) : 'students';
if (!in_array($active_tab, ['students', 'companies', 'jobs', 'pending'])) {
    $active_tab = 'students';
}

// Get filter parameters (DEFAULT student filter: 'all')
$student_filter = isset($_GET['student_filter']) ? sanitize_input($_GET['student_filter']) : 'all';
$company_filter = isset($_GET['company_filter']) ? sanitize_input($_GET['company_filter']) : 'all';
$job_filter = isset($_GET['job_filter']) ? sanitize_input($_GET['job_filter']) : 'all';

// Search parameters
$student_search = isset($_GET['student_search']) ? trim(sanitize_input($_GET['student_search'])) : '';
$company_search = isset($_GET['company_search']) ? trim(sanitize_input($_GET['company_search'])) : '';
$job_search = isset($_GET['job_search']) ? trim(sanitize_input($_GET['job_search'])) : '';

// --- Get Comprehensive Admin Statistics (Only OTP-Verified Users) ---
// Students counts (Only users who completed Email OTP, with admin I-Card status)
$total_students = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN students s ON u.id = s.user_id WHERE u.role = 'student' AND u.is_verified = 1")->fetchColumn();
$unverified_students = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN students s ON u.id = s.user_id WHERE u.role = 'student' AND u.is_verified = 1 AND (s.is_verified = 0 OR s.is_verified IS NULL)")->fetchColumn();
$verified_students = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN students s ON u.id = s.user_id WHERE u.role = 'student' AND u.is_verified = 1 AND s.is_verified = 1")->fetchColumn();
$active_students = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_verified = 1 AND deleted_at IS NULL")->fetchColumn();
$deactive_students = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_verified = 1 AND deleted_at IS NOT NULL")->fetchColumn();

// Companies count (Only OTP-verified companies)
$total_companies = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'company' AND is_verified = 1")->fetchColumn();
$active_companies = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'company' AND is_verified = 1 AND deleted_at IS NULL")->fetchColumn();
$deactive_companies = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'company' AND is_verified = 1 AND deleted_at IS NOT NULL")->fetchColumn();

// Jobs count
$total_jobs = (int)$pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$active_jobs = (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE deleted_at IS NULL")->fetchColumn();
$deactive_jobs = (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE deleted_at IS NOT NULL")->fetchColumn();
$pending_jobs = (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE approval_status = 'Pending' AND deleted_at IS NULL")->fetchColumn();

// --- 1. Fetch Students Query (Only OTP-verified students, showing admin I-Card status) ---
$student_sql = "
    SELECT u.*, s.phone, s.department, s.passing_year, s.cgpa, s.skills, s.bio, s.resume_url, s.id_card_url, COALESCE(s.is_verified, 0) AS is_student_verified
    FROM users u 
    JOIN students s ON u.id = s.user_id 
    WHERE u.role = 'student' AND u.is_verified = 1
";
$params_s = [];
if ($student_filter === 'unverified') {
    $student_sql .= " AND (s.is_verified = 0 OR s.is_verified IS NULL)";
} elseif ($student_filter === 'verified') {
    $student_sql .= " AND s.is_verified = 1";
} elseif ($student_filter === 'active') {
    $student_sql .= " AND u.deleted_at IS NULL";
} elseif ($student_filter === 'deactivated') {
    $student_sql .= " AND u.deleted_at IS NOT NULL";
}

if (!empty($student_search)) {
    $student_sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR s.department LIKE ? OR s.skills LIKE ?)";
    $term_s = "%{$student_search}%";
    $params_s = [$term_s, $term_s, $term_s, $term_s];
}
$student_sql .= " ORDER BY u.created_at DESC";
$stmt_st = $pdo->prepare($student_sql);
$stmt_st->execute($params_s);
$students = $stmt_st->fetchAll();

// --- 2. Fetch Companies Query (Only OTP-verified companies) ---
$company_sql = "
    SELECT u.*, c.company_name, c.industry, c.location, c.website, c.description, c.logo_url 
    FROM users u 
    LEFT JOIN companies c ON u.id = c.user_id 
    WHERE u.role = 'company' AND u.is_verified = 1
";
$params_c = [];
if ($company_filter === 'active') {
    $company_sql .= " AND u.deleted_at IS NULL";
} elseif ($company_filter === 'deactivated') {
    $company_sql .= " AND u.deleted_at IS NOT NULL";
}

if (!empty($company_search)) {
    $company_sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR c.company_name LIKE ? OR c.industry LIKE ? OR c.location LIKE ?)";
    $term_c = "%{$company_search}%";
    $params_c = [$term_c, $term_c, $term_c, $term_c, $term_c];
}
$company_sql .= " ORDER BY u.created_at DESC";
$stmt_comp = $pdo->prepare($company_sql);
$stmt_comp->execute($params_c);
$companies = $stmt_comp->fetchAll();

// --- 3. Fetch Jobs Query ---
$jobs_sql = "SELECT * FROM jobs WHERE 1=1";
$params_j = [];
if ($job_filter === 'active') {
    $jobs_sql .= " AND deleted_at IS NULL";
} elseif ($job_filter === 'deactivated') {
    $jobs_sql .= " AND deleted_at IS NOT NULL";
} elseif ($job_filter === 'pending') {
    $jobs_sql .= " AND approval_status = 'Pending' AND deleted_at IS NULL";
} elseif ($job_filter === 'approved') {
    $jobs_sql .= " AND approval_status = 'Approved' AND deleted_at IS NULL";
} elseif ($job_filter === 'rejected') {
    $jobs_sql .= " AND approval_status = 'Rejected'";
}

if (!empty($job_search)) {
    $jobs_sql .= " AND (title LIKE ? OR company_name LIKE ? OR location LIKE ? OR salary LIKE ? OR eligibility LIKE ?)";
    $term_j = "%{$job_search}%";
    $params_j = [$term_j, $term_j, $term_j, $term_j, $term_j];
}
$jobs_sql .= " ORDER BY created_at DESC";
$stmt_jobs = $pdo->prepare($jobs_sql);
$stmt_jobs->execute($params_j);
$all_jobs = $stmt_jobs->fetchAll();

// Pending jobs query specifically for approvals view
$pending_jobs_list = $pdo->query("SELECT * FROM jobs WHERE approval_status = 'Pending' AND deleted_at IS NULL ORDER BY created_at DESC")->fetchAll();

include '../includes/header.php';
?>

<div class="py-4 fade-in">
    <!-- Header with Action Buttons -->
    <div class="page-header mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-shield-halved me-2 text-primary"></i>Admin Command Center</h2>
            <p class="text-muted mb-0 small">Audit Student I-Cards, manage verified Corporate accounts, and moderate Job Drives</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="../actions/export_report.php?type=students" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fa-solid fa-file-export me-1"></i>Export Students</a>
            <a href="../actions/export_report.php?type=companies" class="btn btn-sm btn-outline-success rounded-pill"><i class="fa-solid fa-file-export me-1"></i>Export Companies</a>
            <a href="../actions/export_report.php?type=jobs" class="btn btn-sm btn-outline-info rounded-pill"><i class="fa-solid fa-file-csv me-1"></i>Export Jobs</a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if(isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['warning_msg'])): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $_SESSION['warning_msg']; unset($_SESSION['warning_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Interactive Clickable Stat Cards (Click card to open corresponding module view) -->
    <div class="row g-4 mb-4">
        <!-- 1. Students Card -->
        <div class="col-md-3">
            <div class="stat-card gradient-primary text-white h-100 shadow-sm cursor-pointer position-relative <?php echo $active_tab === 'students' ? 'ring-active' : ''; ?>" onclick="switchAdminTab('students')" title="Click to view Students Module">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Students</div>
                        <div class="stat-value"><?php echo $total_students; ?></div>
                        <div class="small mt-1 d-flex flex-wrap gap-1">
                            <span class="badge <?php echo $unverified_students > 0 ? 'bg-warning text-dark fw-bold' : 'bg-white text-dark'; ?> rounded-pill">
                                <i class="fa-solid fa-clock me-1"></i><?php echo $unverified_students; ?> Unverified (I-Card)
                            </span>
                            <span class="badge bg-white text-dark rounded-pill"><?php echo $verified_students; ?> Verified</span>
                        </div>
                    </div>
                    <i class="fa-solid fa-user-graduate stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- 2. Companies Card -->
        <div class="col-md-3">
            <div class="stat-card gradient-success text-white h-100 shadow-sm cursor-pointer position-relative <?php echo $active_tab === 'companies' ? 'ring-active' : ''; ?>" onclick="switchAdminTab('companies')" title="Click to view Companies Module">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Companies</div>
                        <div class="stat-value"><?php echo $total_companies; ?></div>
                        <div class="small mt-1 d-flex gap-1">
                            <span class="badge bg-white text-dark rounded-pill"><?php echo $active_companies; ?> Active (1)</span>
                            <span class="badge bg-dark bg-opacity-25 rounded-pill"><?php echo $deactive_companies; ?> Deactive (0)</span>
                        </div>
                    </div>
                    <i class="fa-solid fa-building stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- 3. Total Jobs Card -->
        <div class="col-md-3">
            <div class="stat-card gradient-info text-white h-100 shadow-sm cursor-pointer position-relative <?php echo $active_tab === 'jobs' ? 'ring-active' : ''; ?>" onclick="switchAdminTab('jobs')" title="Click to view Job Postings Module">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Jobs</div>
                        <div class="stat-value"><?php echo $total_jobs; ?></div>
                        <div class="small mt-1 d-flex gap-1">
                            <span class="badge bg-white text-dark rounded-pill"><?php echo $active_jobs; ?> Active (1)</span>
                            <span class="badge bg-dark bg-opacity-25 rounded-pill"><?php echo $deactive_jobs; ?> Deactive (0)</span>
                        </div>
                    </div>
                    <i class="fa-solid fa-briefcase stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- 4. Pending Approvals Card -->
        <div class="col-md-3">
            <div class="stat-card gradient-warning text-white h-100 shadow-sm cursor-pointer position-relative <?php echo $active_tab === 'pending' ? 'ring-active' : ''; ?>" onclick="switchAdminTab('pending')" title="Click to moderate Pending Job Drives">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Pending Approvals</div>
                        <div class="stat-value"><?php echo $pending_jobs; ?></div>
                        <div class="small mt-1">
                            <?php if($pending_jobs > 0): ?>
                                <span class="badge bg-danger rounded-pill"><i class="fa-solid fa-bell me-1"></i>Action Required</span>
                            <?php else: ?>
                                <span class="badge bg-white text-dark rounded-pill"><i class="fa-solid fa-check me-1"></i>All Moderated</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <i class="fa-solid fa-clock stat-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tab Pills to Switch Modules -->
    <div class="card border-0 shadow-sm mb-4 rounded-4 bg-white p-2">
        <ul class="nav nav-pills nav-fill gap-2" id="adminModuleTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill py-2.5 fw-bold <?php echo $active_tab === 'students' ? 'active' : ''; ?>" id="tab-btn-students" data-bs-toggle="pill" data-bs-target="#panel-students" type="button" role="tab" onclick="updateTabUrl('students')">
                    <i class="fa-solid fa-user-graduate me-2"></i>Students Module (<?php echo $total_students; ?>)
                    <?php if($unverified_students > 0): ?>
                        <span class="badge bg-warning text-dark rounded-pill ms-1"><?php echo $unverified_students; ?> Pending I-Card</span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill py-2.5 fw-bold <?php echo $active_tab === 'companies' ? 'active' : ''; ?>" id="tab-btn-companies" data-bs-toggle="pill" data-bs-target="#panel-companies" type="button" role="tab" onclick="updateTabUrl('companies')">
                    <i class="fa-solid fa-building me-2"></i>Companies Module (<?php echo $total_companies; ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill py-2.5 fw-bold <?php echo $active_tab === 'jobs' ? 'active' : ''; ?>" id="tab-btn-jobs" data-bs-toggle="pill" data-bs-target="#panel-jobs" type="button" role="tab" onclick="updateTabUrl('jobs')">
                    <i class="fa-solid fa-briefcase me-2"></i>Job Drives / Posts (<?php echo $total_jobs; ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill py-2.5 fw-bold <?php echo $active_tab === 'pending' ? 'active' : ''; ?>" id="tab-btn-pending" data-bs-toggle="pill" data-bs-target="#panel-pending" type="button" role="tab" onclick="updateTabUrl('pending')">
                    <i class="fa-solid fa-clock me-2"></i>Pending Approvals
                    <?php if($pending_jobs > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-1"><?php echo $pending_jobs; ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>
    </div>

    <!-- ==================== TAB PANELS CONTAINER ==================== -->
    <div class="tab-content" id="adminModuleTabContent">

        <!-- ==================== 1. STUDENTS MODULE PANEL ==================== -->
        <div class="tab-pane fade <?php echo $active_tab === 'students' ? 'show active' : ''; ?>" id="panel-students" role="tabpanel">
            <div class="section-card shadow-sm border-0 rounded-4">
                <div class="section-header p-3 bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-user-graduate me-2 text-primary"></i>Manage Students (<?php echo count($students); ?>)</h5>
                    </div>

                    <!-- Search Box & Filter Controls -->
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!-- Live Search Input -->
                        <form method="GET" class="d-flex align-items-center gap-1">
                            <input type="hidden" name="tab" value="students">
                            <input type="hidden" name="student_filter" value="<?php echo htmlspecialchars($student_filter); ?>">
                            <div class="input-group input-group-sm" style="min-width: 220px;">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                <input type="text" name="student_search" id="liveStudentSearch" class="form-control border-start-0 ps-0" placeholder="Search student, email, dept, skills..." value="<?php echo htmlspecialchars($student_search); ?>">
                                <?php if(!empty($student_search)): ?>
                                    <a href="?tab=students&student_filter=<?php echo $student_filter; ?>" class="btn btn-outline-secondary btn-sm" title="Clear Search"><i class="fa-solid fa-xmark"></i></a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- Student Status Filters -->
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="?tab=students&student_filter=all<?php echo !empty($student_search)?'&student_search='.urlencode($student_search):''; ?>" class="btn <?php echo $student_filter === 'all' ? 'btn-primary fw-bold' : 'btn-outline-secondary'; ?>">
                                All (<?php echo $total_students; ?>)
                            </a>
                            <a href="?tab=students&student_filter=unverified<?php echo !empty($student_search)?'&student_search='.urlencode($student_search):''; ?>" class="btn <?php echo $student_filter === 'unverified' ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning text-dark'; ?>">
                                <i class="fa-solid fa-clock me-1"></i>Unverified (<?php echo $unverified_students; ?>)
                            </a>
                            <a href="?tab=students&student_filter=verified<?php echo !empty($student_search)?'&student_search='.urlencode($student_search):''; ?>" class="btn <?php echo $student_filter === 'verified' ? 'btn-success fw-bold' : 'btn-outline-success'; ?>">
                                <i class="fa-solid fa-circle-check me-1"></i>Verified (<?php echo $verified_students; ?>)
                            </a>
                            <a href="?tab=students&student_filter=active<?php echo !empty($student_search)?'&student_search='.urlencode($student_search):''; ?>" class="btn <?php echo $student_filter === 'active' ? 'btn-dark fw-bold' : 'btn-outline-dark'; ?>">
                                Active 1 (<?php echo $active_students; ?>)
                            </a>
                            <a href="?tab=students&student_filter=deactivated<?php echo !empty($student_search)?'&student_search='.urlencode($student_search):''; ?>" class="btn <?php echo $student_filter === 'deactivated' ? 'btn-danger fw-bold' : 'btn-outline-danger'; ?>">
                                Deactive 0 (<?php echo $deactive_students; ?>)
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle" id="studentsTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Student Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>CGPA</th>
                                    <th>I-Card & Resume Status</th>
                                    <th>Status (Active/Deactive)</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($students)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-user-slash fs-2 mb-2 d-block text-secondary opacity-50"></i>
                                            No students found matching filter "<strong><?php echo htmlspecialchars($student_filter); ?></strong>"
                                            <?php if(!empty($student_search)) echo ' and search "<strong>'.htmlspecialchars($student_search).'</strong>"'; ?>.
                                            <div class="mt-2">
                                                <a href="?tab=students&student_filter=all" class="btn btn-sm btn-outline-primary rounded-pill">View All Students</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($students as $st): ?>
                                        <?php $isActive = empty($st['deleted_at']); ?>
                                        <?php $isDocVerified = !empty($st['is_student_verified']); ?>
                                        <tr class="<?php echo !$isActive ? 'table-light opacity-75' : ''; ?>">
                                            <td class="ps-4">
                                                <a href="#" class="fw-bold text-dark text-decoration-none" data-bs-toggle="modal" data-bs-target="#studentModal_<?php echo $st['id']; ?>">
                                                    <i class="fa-solid fa-user-circle me-1 text-primary"></i><?php echo htmlspecialchars($st['name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($st['email']); ?></td>
                                            <td><?php echo htmlspecialchars($st['department'] ?? 'N/A'); ?></td>
                                            <td><?php echo (!empty($st['cgpa']) && $st['cgpa'] > 0) ? htmlspecialchars($st['cgpa']) : 'N/A'; ?></td>
                                            <td>
                                                <?php if($isDocVerified): ?>
                                                    <a href="../actions/toggle_verification.php?id=<?php echo $st['id']; ?>&status=0" class="badge bg-success text-decoration-none rounded-pill px-2.5 py-1.5" onclick="return confirm('Revoke verification and mark student as Unverified?');" title="Click to mark Unverified">
                                                        <i class="fa-solid fa-circle-check me-1"></i>Verified (I-Card Checked)
                                                    </a>
                                                <?php else: ?>
                                                    <a href="../actions/toggle_verification.php?id=<?php echo $st['id']; ?>&status=1" class="badge bg-warning text-dark text-decoration-none rounded-pill px-2.5 py-1.5" onclick="return confirm('Verify student after checking I-Card and Resume?');" title="Click to Verify">
                                                        <i class="fa-solid fa-clock me-1"></i>Unverified (Check I-Card)
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($isActive): ?>
                                                    <span class="badge bg-success rounded-pill px-3 py-1"><i class="fa-solid fa-circle-check me-1"></i>Active (1)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger rounded-pill px-3 py-1"><i class="fa-solid fa-ban me-1"></i>Deactivated (0)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill me-1" data-bs-toggle="modal" data-bs-target="#studentModal_<?php echo $st['id']; ?>">
                                                    <i class="fa-solid fa-eye me-1"></i>Inspect & Verify
                                                </button>
                                                
                                                <?php if($isActive): ?>
                                                    <a href="../actions/delete_user.php?id=<?php echo $st['id']; ?>&role=student&action=deactivate" class="btn btn-sm btn-outline-danger rounded-pill" onclick="return confirm('Are you sure you want to deactivate (soft delete 0) this student?');">
                                                        <i class="fa-solid fa-ban me-1"></i>Deactivate (0)
                                                    </a>
                                                <?php else: ?>
                                                    <a href="../actions/delete_user.php?id=<?php echo $st['id']; ?>&role=student&action=restore" class="btn btn-sm btn-success rounded-pill me-1" onclick="return confirm('Are you sure you want to reactivate (1) this student?');">
                                                        <i class="fa-solid fa-rotate-left me-1"></i>Activate (1)
                                                    </a>
                                                    <a href="../actions/delete_user.php?id=<?php echo $st['id']; ?>&role=student&action=hard_delete" class="btn btn-sm btn-danger rounded-pill" onclick="return confirm('⚠️ PERMANENT DELETE WARNING: Are you sure you want to permanently delete this student from the database?');">
                                                        <i class="fa-solid fa-trash-can me-1"></i>Hard Delete
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <!-- Student Full Information Modal (Document Review & Verification) -->
                                        <div class="modal fade" id="studentModal_<?php echo $st['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                                <div class="modal-content border-0 shadow-lg rounded-4">
                                                    <div class="modal-header gradient-primary text-white">
                                                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-graduate me-2"></i>Student Details - <?php echo htmlspecialchars($st['name']); ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar-circle me-3">
                                                                    <i class="fa-solid fa-user"></i>
                                                                </div>
                                                                <div>
                                                                    <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($st['name']); ?></h4>
                                                                    <span class="text-muted small"><i class="fa-solid fa-envelope me-1"></i><?php echo htmlspecialchars($st['email']); ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex gap-2">
                                                                <?php if($isDocVerified): ?>
                                                                    <span class="badge bg-success rounded-pill px-3 py-1.5"><i class="fa-solid fa-circle-check me-1"></i>Verified</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1.5"><i class="fa-solid fa-clock me-1"></i>Unverified (I-Card)</span>
                                                                <?php endif; ?>
                                                                <?php if($isActive): ?>
                                                                    <span class="badge bg-success rounded-pill px-3 py-1.5"><i class="fa-solid fa-circle-check me-1"></i>Active (1)</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger rounded-pill px-3 py-1.5"><i class="fa-solid fa-ban me-1"></i>Deactivated (0)</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <div class="p-3 bg-light rounded-3">
                                                                    <label class="text-muted small fw-semibold">Phone Number</label>
                                                                    <p class="mb-0 fw-bold"><?php echo !empty($st['phone']) ? htmlspecialchars($st['phone']) : 'N/A'; ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="p-3 bg-light rounded-3">
                                                                    <label class="text-muted small fw-semibold">Course / Department</label>
                                                                    <p class="mb-0 fw-bold"><?php echo !empty($st['department']) ? htmlspecialchars($st['department']) : 'N/A'; ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="p-3 bg-light rounded-3">
                                                                    <label class="text-muted small fw-semibold">Passing Year</label>
                                                                    <p class="mb-0 fw-bold"><?php echo !empty($st['passing_year']) ? htmlspecialchars($st['passing_year']) : 'N/A'; ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="p-3 bg-light rounded-3">
                                                                    <label class="text-muted small fw-semibold">CGPA / Percentage</label>
                                                                    <p class="mb-0 fw-bold"><?php echo (!empty($st['cgpa']) && $st['cgpa'] > 0) ? htmlspecialchars($st['cgpa']) : 'N/A'; ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="col-12">
                                                                <div class="p-3 bg-light rounded-3">
                                                                    <label class="text-muted small fw-semibold d-block mb-1">Skills</label>
                                                                    <?php if(!empty($st['skills'])): ?>
                                                                        <?php foreach(explode(',', $st['skills']) as $sk): ?>
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
                                                                    <p class="mb-0"><?php echo !empty($st['bio']) ? nl2br(htmlspecialchars($st['bio'])) : 'No bio provided.'; ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                                    <div>
                                                                        <label class="text-muted small fw-semibold d-block">Resume Document</label>
                                                                        <span><?php echo !empty($st['resume_url']) ? '<i class="fa-solid fa-file-pdf text-danger me-1"></i>Resume uploaded' : 'No resume uploaded'; ?></span>
                                                                    </div>
                                                                    <?php if(!empty($st['resume_url'])): ?>
                                                                        <a href="<?php echo htmlspecialchars($st['resume_url']); ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3">
                                                                            <i class="fa-solid fa-download me-1"></i>Download Resume
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                                    <div>
                                                                        <label class="text-muted small fw-semibold d-block">Student ID Card (I-Card)</label>
                                                                        <span><?php echo !empty($st['id_card_url']) ? '<i class="fa-solid fa-id-card text-success me-1"></i>ID card uploaded' : 'No ID card uploaded'; ?></span>
                                                                    </div>
                                                                    <?php if(!empty($st['id_card_url'])): ?>
                                                                        <a href="<?php echo htmlspecialchars($st['id_card_url']); ?>" target="_blank" class="btn btn-sm btn-success rounded-pill px-3">
                                                                            <i class="fa-solid fa-eye me-1"></i>View ID Card
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>

                                                            <!-- Admin Verification Action Card (I-Card & Resume Checked) -->
                                                            <div class="col-12">
                                                                <div class="p-3 border rounded-3 <?php echo $isDocVerified ? 'bg-success-subtle border-success' : 'bg-warning-subtle border-warning'; ?>">
                                                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                                        <div>
                                                                            <label class="fw-bold d-block text-dark mb-1">
                                                                                <i class="fa-solid fa-shield-halved me-1 text-primary"></i>Admin Document Verification (I-Card & Resume)
                                                                            </label>
                                                                            <span class="small text-muted">
                                                                                <?php if($isDocVerified): ?>
                                                                                    <span class="text-success fw-bold"><i class="fa-solid fa-circle-check me-1"></i>Verified:</span> Student I-Card and Resume have been checked & approved by Admin.
                                                                                <?php else: ?>
                                                                                    <span class="text-dark fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>Unverified:</span> Check uploaded I-Card and Resume before verifying student.
                                                                                <?php endif; ?>
                                                                            </span>
                                                                        </div>
                                                                        <div>
                                                                            <?php if($isDocVerified): ?>
                                                                                <a href="../actions/toggle_verification.php?id=<?php echo $st['id']; ?>&status=0" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Revoke verification and mark student as Unverified?');">
                                                                                    <i class="fa-solid fa-xmark me-1"></i>Mark as Unverified
                                                                                </a>
                                                                            <?php else: ?>
                                                                                <a href="../actions/toggle_verification.php?id=<?php echo $st['id']; ?>&status=1" class="btn btn-sm btn-success rounded-pill px-3" onclick="return confirm('Confirm that student I-Card and Resume have been checked and approved?');">
                                                                                    <i class="fa-solid fa-check-double me-1"></i>Verify Student (I-Card Checked)
                                                                                </a>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                                                        <?php if($isActive): ?>
                                                            <a href="../actions/delete_user.php?id=<?php echo $st['id']; ?>&role=student&action=deactivate" class="btn btn-danger rounded-pill" onclick="return confirm('Deactivate this student?');"><i class="fa-solid fa-ban me-1"></i>Deactivate Student (0)</a>
                                                        <?php else: ?>
                                                            <a href="../actions/delete_user.php?id=<?php echo $st['id']; ?>&role=student&action=restore" class="btn btn-success rounded-pill" onclick="return confirm('Activate this student?');"><i class="fa-solid fa-rotate-left me-1"></i>Activate Student (1)</a>
                                                            <a href="../actions/delete_user.php?id=<?php echo $st['id']; ?>&role=student&action=hard_delete" class="btn btn-danger rounded-pill" onclick="return confirm('⚠️ PERMANENT DELETE WARNING: Are you sure you want to permanently delete this student? All data and files will be permanently erased.');"><i class="fa-solid fa-trash-can me-1"></i>Hard Delete Permanent</a>
                                                        <?php endif; ?>
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

        <!-- ==================== 2. COMPANIES MODULE PANEL ==================== -->
        <div class="tab-pane fade <?php echo $active_tab === 'companies' ? 'show active' : ''; ?>" id="panel-companies" role="tabpanel">
            <div class="section-card shadow-sm border-0 rounded-4">
                <div class="section-header p-3 bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-building me-2 text-success"></i>Manage Companies (<?php echo count($companies); ?>)</h5>
                    </div>

                    <!-- Search Box & Filter Controls -->
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!-- Company Search Input -->
                        <form method="GET" class="d-flex align-items-center gap-1">
                            <input type="hidden" name="tab" value="companies">
                            <input type="hidden" name="company_filter" value="<?php echo htmlspecialchars($company_filter); ?>">
                            <div class="input-group input-group-sm" style="min-width: 220px;">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                <input type="text" name="company_search" id="liveCompanySearch" class="form-control border-start-0 ps-0" placeholder="Search company, person, email, location..." value="<?php echo htmlspecialchars($company_search); ?>">
                                <?php if(!empty($company_search)): ?>
                                    <a href="?tab=companies&company_filter=<?php echo $company_filter; ?>" class="btn btn-outline-secondary btn-sm" title="Clear Search"><i class="fa-solid fa-xmark"></i></a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="btn-group btn-group-sm" role="group">
                            <a href="?tab=companies&company_filter=all<?php echo !empty($company_search)?'&company_search='.urlencode($company_search):''; ?>" class="btn <?php echo $company_filter === 'all' ? 'btn-primary fw-bold' : 'btn-outline-secondary'; ?>">All (<?php echo $total_companies; ?>)</a>
                            <a href="?tab=companies&company_filter=active<?php echo !empty($company_search)?'&company_search='.urlencode($company_search):''; ?>" class="btn <?php echo $company_filter === 'active' ? 'btn-success fw-bold' : 'btn-outline-secondary'; ?>"><i class="fa-solid fa-circle-check me-1"></i>Active 1 (<?php echo $active_companies; ?>)</a>
                            <a href="?tab=companies&company_filter=deactivated<?php echo !empty($company_search)?'&company_search='.urlencode($company_search):''; ?>" class="btn <?php echo $company_filter === 'deactivated' ? 'btn-danger fw-bold' : 'btn-outline-secondary'; ?>"><i class="fa-solid fa-ban me-1"></i>Deactive 0 (<?php echo $deactive_companies; ?>)</a>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle" id="companiesTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Company Name</th>
                                    <th>Contact Person</th>
                                    <th>Email</th>
                                    <th>Industry</th>
                                    <th>Location</th>
                                    <th>Status (Active/Deactive)</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($companies)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-building-circle-xmark fs-2 mb-2 d-block text-secondary opacity-50"></i>
                                            No companies found under filter "<strong><?php echo htmlspecialchars($company_filter); ?></strong>"
                                            <?php if(!empty($company_search)) echo ' and search "<strong>'.htmlspecialchars($company_search).'</strong>"'; ?>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($companies as $comp): ?>
                                        <?php $isActive = empty($comp['deleted_at']); ?>
                                        <tr class="<?php echo !$isActive ? 'table-light opacity-75' : ''; ?>">
                                            <td class="ps-4">
                                                <a href="#" class="fw-bold text-dark text-decoration-none" data-bs-toggle="modal" data-bs-target="#compModal_<?php echo $comp['id']; ?>">
                                                    <i class="fa-solid fa-building me-1 text-success"></i><?php echo htmlspecialchars(!empty($comp['company_name']) ? $comp['company_name'] : $comp['name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($comp['name']); ?></td>
                                            <td><?php echo htmlspecialchars($comp['email']); ?></td>
                                            <td><?php echo htmlspecialchars($comp['industry'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($comp['location'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if($isActive): ?>
                                                    <span class="badge bg-success rounded-pill px-3 py-1"><i class="fa-solid fa-circle-check me-1"></i>Active (1)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger rounded-pill px-3 py-1"><i class="fa-solid fa-ban me-1"></i>Deactivated (0)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill me-1" data-bs-toggle="modal" data-bs-target="#compModal_<?php echo $comp['id']; ?>">
                                                    <i class="fa-solid fa-eye me-1"></i>View Info
                                                </button>

                                                <?php if($isActive): ?>
                                                    <a href="../actions/delete_user.php?id=<?php echo $comp['id']; ?>&role=company&action=deactivate" class="btn btn-sm btn-outline-danger rounded-pill" onclick="return confirm('Are you sure you want to deactivate (soft delete 0) this company?');">
                                                        <i class="fa-solid fa-ban me-1"></i>Deactivate (0)
                                                    </a>
                                                <?php else: ?>
                                                    <a href="../actions/delete_user.php?id=<?php echo $comp['id']; ?>&role=company&action=restore" class="btn btn-sm btn-success rounded-pill me-1" onclick="return confirm('Are you sure you want to reactivate (1) this company?');">
                                                        <i class="fa-solid fa-rotate-left me-1"></i>Activate (1)
                                                    </a>
                                                    <a href="../actions/delete_user.php?id=<?php echo $comp['id']; ?>&role=company&action=hard_delete" class="btn btn-sm btn-danger rounded-pill" onclick="return confirm('⚠️ PERMANENT DELETE WARNING: Are you sure you want to permanently delete this company from the database? All company data and jobs will be removed forever.');"><i class="fa-solid fa-trash-can me-1"></i>Hard Delete</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <!-- Company Full Information Modal -->
                                        <div class="modal fade" id="compModal_<?php echo $comp['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                                <div class="modal-content border-0 shadow-lg rounded-4">
                                                    <div class="modal-header gradient-success text-white">
                                                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-building me-2"></i>Company Details - <?php echo htmlspecialchars(!empty($comp['company_name']) ? $comp['company_name'] : $comp['name']); ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                                                            <div class="d-flex align-items-center">
                                                                <?php if (!empty($comp['logo_url'])): ?>
                                                                    <div class="me-3 p-1 border rounded bg-white shadow-sm d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                                                        <img src="<?php echo htmlspecialchars($comp['logo_url']); ?>" alt="Company Logo" class="rounded" style="max-width: 48px; max-height: 48px; object-fit: contain;">
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="avatar-circle me-3" style="background: linear-gradient(135deg, #00b894, #00cec9);">
                                                                        <i class="fa-solid fa-building"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div>
                                                                    <h4 class="fw-bold mb-0"><?php echo htmlspecialchars(!empty($comp['company_name']) ? $comp['company_name'] : $comp['name']); ?></h4>
                                                                    <span class="text-muted small">Contact Person: <?php echo htmlspecialchars($comp['name']); ?></span>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <?php if($isActive): ?>
                                                                    <span class="badge bg-success rounded-pill px-3 py-1.5"><i class="fa-solid fa-circle-check me-1"></i>Active (1)</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger rounded-pill px-3 py-1.5"><i class="fa-solid fa-ban me-1"></i>Deactivated (0)</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <div class="p-3 bg-light rounded-3">
                                                                    <label class="text-muted small fw-semibold">Email Address</label>
                                                                    <p class="mb-0 fw-bold"><?php echo htmlspecialchars($comp['email']); ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="p-3 bg-light rounded-3">
                                                                    <label class="text-muted small fw-semibold">Industry / Sector</label>
                                                                    <p class="mb-0 fw-bold"><?php echo !empty($comp['industry']) ? htmlspecialchars($comp['industry']) : 'N/A'; ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="p-3 bg-light rounded-3">
                                                                    <label class="text-muted small fw-semibold">Location / HQ</label>
                                                                    <p class="mb-0 fw-bold"><?php echo !empty($comp['location']) ? htmlspecialchars($comp['location']) : 'N/A'; ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="p-3 bg-light rounded-3">
                                                                    <label class="text-muted small fw-semibold">Company Website</label>
                                                                    <p class="mb-0">
                                                                        <?php if(!empty($comp['website'])): ?>
                                                                            <a href="<?php echo htmlspecialchars($comp['website']); ?>" target="_blank" class="fw-bold text-primary"><?php echo htmlspecialchars($comp['website']); ?></a>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">N/A</span>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="col-12">
                                                                <div class="p-3 bg-light rounded-3">
                                                                    <label class="text-muted small fw-semibold">About Company</label>
                                                                    <p class="mb-0"><?php echo !empty($comp['description']) ? nl2br(htmlspecialchars($comp['description'])) : 'No description provided.'; ?></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                                                        <?php if($isActive): ?>
                                                            <a href="../actions/delete_user.php?id=<?php echo $comp['id']; ?>&role=company&action=deactivate" class="btn btn-danger rounded-pill" onclick="return confirm('Deactivate this company?');"><i class="fa-solid fa-ban me-1"></i>Deactivate Company (0)</a>
                                                        <?php else: ?>
                                                            <a href="../actions/delete_user.php?id=<?php echo $comp['id']; ?>&role=company&action=restore" class="btn btn-success rounded-pill" onclick="return confirm('Activate this company?');"><i class="fa-solid fa-rotate-left me-1"></i>Activate Company (1)</a>
                                                            <a href="../actions/delete_user.php?id=<?php echo $comp['id']; ?>&role=company&action=hard_delete" class="btn btn-danger rounded-pill" onclick="return confirm('⚠️ PERMANENT DELETE WARNING: Are you sure you want to permanently delete this company from the database? All company data and jobs will be removed forever.');"><i class="fa-solid fa-trash-can me-1"></i>Hard Delete Permanent</a>
                                                        <?php endif; ?>
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

        <!-- ==================== 3. JOB DRIVES / POSTS MODULE PANEL ==================== -->
        <div class="tab-pane fade <?php echo $active_tab === 'jobs' ? 'show active' : ''; ?>" id="panel-jobs" role="tabpanel">
            <div class="section-card shadow-sm border-0 rounded-4">
                <div class="section-header p-3 bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-briefcase me-2 text-info"></i>Manage All Job Drives (<?php echo count($all_jobs); ?>)</h5>
                    </div>

                    <!-- Search Box & Filter Controls -->
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!-- Job Search Input -->
                        <form method="GET" class="d-flex align-items-center gap-1">
                            <input type="hidden" name="tab" value="jobs">
                            <input type="hidden" name="job_filter" value="<?php echo htmlspecialchars($job_filter); ?>">
                            <div class="input-group input-group-sm" style="min-width: 220px;">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                <input type="text" name="job_search" id="liveJobSearch" class="form-control border-start-0 ps-0" placeholder="Search title, company, location, salary..." value="<?php echo htmlspecialchars($job_search); ?>">
                                <?php if(!empty($job_search)): ?>
                                    <a href="?tab=jobs&job_filter=<?php echo $job_filter; ?>" class="btn btn-outline-secondary btn-sm" title="Clear Search"><i class="fa-solid fa-xmark"></i></a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="btn-group btn-group-sm" role="group">
                            <a href="?tab=jobs&job_filter=all<?php echo !empty($job_search)?'&job_search='.urlencode($job_search):''; ?>" class="btn <?php echo $job_filter === 'all' ? 'btn-primary fw-bold' : 'btn-outline-secondary'; ?>">All (<?php echo $total_jobs; ?>)</a>
                            <a href="?tab=jobs&job_filter=pending<?php echo !empty($job_search)?'&job_search='.urlencode($job_search):''; ?>" class="btn <?php echo $job_filter === 'pending' ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning text-dark'; ?>"><i class="fa-solid fa-clock me-1"></i>Pending (<?php echo $pending_jobs; ?>)</a>
                            <a href="?tab=jobs&job_filter=approved<?php echo !empty($job_search)?'&job_search='.urlencode($job_search):''; ?>" class="btn <?php echo $job_filter === 'approved' ? 'btn-success fw-bold' : 'btn-outline-success'; ?>">Approved</a>
                            <a href="?tab=jobs&job_filter=active<?php echo !empty($job_search)?'&job_search='.urlencode($job_search):''; ?>" class="btn <?php echo $job_filter === 'active' ? 'btn-info fw-bold text-white' : 'btn-outline-info'; ?>"><i class="fa-solid fa-circle-check me-1"></i>Active 1 (<?php echo $active_jobs; ?>)</a>
                            <a href="?tab=jobs&job_filter=deactivated<?php echo !empty($job_search)?'&job_search='.urlencode($job_search):''; ?>" class="btn <?php echo $job_filter === 'deactivated' ? 'btn-danger fw-bold' : 'btn-outline-danger'; ?>"><i class="fa-solid fa-ban me-1"></i>Deactive 0 (<?php echo $deactive_jobs; ?>)</a>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle" id="jobsTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Job Title</th>
                                    <th>Company</th>
                                    <th>Location</th>
                                    <th>Approval Status</th>
                                    <th>Lifecycle Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($all_jobs)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-inbox fs-2 mb-2 d-block text-secondary opacity-50"></i>
                                            No job drives found matching filter "<strong><?php echo htmlspecialchars($job_filter); ?></strong>"
                                            <?php if(!empty($job_search)) echo ' and search "<strong>'.htmlspecialchars($job_search).'</strong>"'; ?>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($all_jobs as $job): ?>
                                        <?php $isJobActive = empty($job['deleted_at']); ?>
                                        <tr class="<?php echo !$isJobActive ? 'table-light opacity-75' : ''; ?>">
                                            <td class="ps-4 fw-bold">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                                <span class="d-block small text-muted font-monospace"><i class="fa-solid fa-money-bill-wave me-1 text-success"></i><?php echo htmlspecialchars($job['salary']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                                            <td><?php echo htmlspecialchars($job['location']); ?></td>
                                            <td>
                                                <?php 
                                                    $appBadge = 'bg-warning text-dark';
                                                    if($job['approval_status'] == 'Approved') $appBadge = 'bg-success';
                                                    elseif($job['approval_status'] == 'Rejected') $appBadge = 'bg-danger';
                                                ?>
                                                <span class="badge <?php echo $appBadge; ?> rounded-pill px-2.5"><?php echo $job['approval_status']; ?></span>
                                            </td>
                                            <td>
                                                <?php if($isJobActive): ?>
                                                    <span class="badge bg-success rounded-pill px-3 py-1"><i class="fa-solid fa-circle-check me-1"></i>Active (1)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger rounded-pill px-3 py-1"><i class="fa-solid fa-ban me-1"></i>Deactivated (0)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <a href="view_applicants.php?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill me-1"><i class="fa-solid fa-users me-1"></i>Applicants</a>
                                                
                                                <?php if($job['approval_status'] === 'Pending'): ?>
                                                    <a href="../actions/approve_job.php?id=<?php echo $job['id']; ?>&status=Approved" class="btn btn-sm btn-success rounded-pill me-1"><i class="fa-solid fa-check me-1"></i>Approve</a>
                                                    <a href="../actions/approve_job.php?id=<?php echo $job['id']; ?>&status=Rejected" class="btn btn-sm btn-danger rounded-pill me-1"><i class="fa-solid fa-xmark me-1"></i>Reject</a>
                                                <?php endif; ?>

                                                <?php if($isJobActive): ?>
                                                    <a href="../actions/delete_job.php?id=<?php echo $job['id']; ?>&action=deactivate" class="btn btn-sm btn-outline-danger rounded-pill" onclick="return confirm('Deactivate (soft delete 0) this job?');"><i class="fa-solid fa-ban me-1"></i>Deactivate (0)</a>
                                                <?php else: ?>
                                                    <a href="../actions/delete_job.php?id=<?php echo $job['id']; ?>&action=restore" class="btn btn-sm btn-success rounded-pill me-1" onclick="return confirm('Activate (1) this job?');"><i class="fa-solid fa-rotate-left me-1"></i>Activate (1)</a>
                                                    <a href="../actions/delete_job.php?id=<?php echo $job['id']; ?>&action=hard_delete" class="btn btn-sm btn-danger rounded-pill" onclick="return confirm('⚠️ PERMANENT DELETE WARNING: Are you sure you want to permanently delete this job from the database?');"><i class="fa-solid fa-trash-can me-1"></i>Hard Delete</a>
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
        </div>

        <!-- ==================== 4. PENDING APPROVALS MODULE PANEL ==================== -->
        <div class="tab-pane fade <?php echo $active_tab === 'pending' ? 'show active' : ''; ?>" id="panel-pending" role="tabpanel">
            <div class="section-card border-warning border shadow-sm rounded-4">
                <div class="section-header bg-warning bg-opacity-10 p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0 text-dark fw-bold"><i class="fa-solid fa-clock me-2 text-warning"></i>Pending Job Moderation Queue (<?php echo count($pending_jobs_list); ?>)</h5>
                    <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill">Action Required Before Publishing</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Company</th>
                                    <th>Job Title</th>
                                    <th>Location</th>
                                    <th>Salary</th>
                                    <th>Eligibility</th>
                                    <th>Date Posted</th>
                                    <th class="text-end pe-4">Moderation Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($pending_jobs_list)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-circle-check fs-2 text-success mb-2 d-block"></i>
                                            All corporate job postings have been reviewed! Zero pending drives in queue.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($pending_jobs_list as $pJob): ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold text-primary"><?php echo htmlspecialchars($pJob['company_name']); ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($pJob['title']); ?></td>
                                            <td><?php echo htmlspecialchars($pJob['location']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($pJob['salary']); ?></span></td>
                                            <td><?php echo htmlspecialchars($pJob['eligibility']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($pJob['created_at'])); ?></td>
                                            <td class="text-end pe-4">
                                                <a href="../actions/approve_job.php?id=<?php echo $pJob['id']; ?>&status=Approved" class="btn btn-sm btn-success rounded-pill px-3 me-1" onclick="return confirm('Approve and publish this job drive to students?');">
                                                    <i class="fa-solid fa-check me-1"></i>Approve
                                                </a>
                                                <a href="../actions/approve_job.php?id=<?php echo $pJob['id']; ?>&status=Rejected" class="btn btn-sm btn-danger rounded-pill px-3" onclick="return confirm('Reject this job posting?');">
                                                    <i class="fa-solid fa-xmark me-1"></i>Reject
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
/* Active Ring on Stat Cards */
.cursor-pointer {
    cursor: pointer;
    transition: all 0.25s ease;
}
.cursor-pointer:hover {
    transform: translateY(-4px);
}
.ring-active {
    box-shadow: 0 0 0 4px #1a237e, 0 10px 25px rgba(0,0,0,0.15) !important;
}
.nav-pills .nav-link {
    color: #4b5563;
    background-color: #f3f4f6;
    transition: all 0.2s ease;
}
.nav-pills .nav-link:hover {
    background-color: #e5e7eb;
    color: #111827;
}
.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #1a237e, #0d47a1) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(26, 35, 126, 0.3);
}
</style>

<script>
// Switch Admin Tab smoothly and update active pill + hash
function switchAdminTab(tabName) {
    var triggerEl = document.getElementById('tab-btn-' + tabName);
    if (triggerEl) {
        var tab = new bootstrap.Tab(triggerEl);
        tab.show();
        updateTabUrl(tabName);
    }
}

function updateTabUrl(tabName) {
    // Update URL query/hash without full reload if supported
    if (history.pushState) {
        var newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + tabName;
        window.history.pushState({path:newurl}, '', newurl);
    }
    // Update active ring on cards
    document.querySelectorAll('.stat-card').forEach(function(card) {
        card.classList.remove('ring-active');
    });
    var activeCard = document.querySelector('[onclick="switchAdminTab(\'' + tabName + '\')"]');
    if (activeCard) {
        activeCard.classList.add('ring-active');
    }
}

// Live client-side instant search filter for tables
document.addEventListener('DOMContentLoaded', function() {
    function bindLiveFilter(inputId, tableId) {
        var inp = document.getElementById(inputId);
        var tbl = document.getElementById(tableId);
        if (!inp || !tbl) return;
        inp.addEventListener('keyup', function() {
            var filter = inp.value.toLowerCase();
            var rows = tbl.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                if (text.indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    bindLiveFilter('liveStudentSearch', 'studentsTable');
    bindLiveFilter('liveCompanySearch', 'companiesTable');
    bindLiveFilter('liveJobSearch', 'jobsTable');
});
</script>

<?php include '../includes/footer.php'; ?>
