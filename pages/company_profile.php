<?php
$page_title = 'Company Profile';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and their role is company
if (!isLoggedIn() || $_SESSION['user_role'] !== 'company') {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get company profile status
$company_status = getCompanyProfileStatus($pdo, $user_id);
$user = $company_status['user'] ?? null;
$company = $company_status['company'] ?? null;

// Count total jobs posted by this company
$query_jobs = "SELECT COUNT(*) FROM jobs WHERE company_id = ? AND deleted_at IS NULL";
$stmt = $pdo->prepare($query_jobs);
$stmt->execute([$user_id]);
$total_jobs = $stmt->fetchColumn();

// Count total applicants for this company's jobs
$query_applicants = "
    SELECT COUNT(a.id) 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE j.company_id = ? AND a.deleted_at IS NULL AND j.deleted_at IS NULL
";
$stmt = $pdo->prepare($query_applicants);
$stmt->execute([$user_id]);
$total_applicants = $stmt->fetchColumn();

include '../includes/header.php';
?>

<div class="py-4 fade-in">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="page-header">
                    <h2><i class="fa-solid fa-building me-2"></i>Company Profile</h2>
                </div>
                <div>
                    <a href="company_dashboard.php" class="btn btn-outline-secondary rounded-pill me-2"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
                    <a href="edit_company_profile.php" class="btn btn-primary rounded-pill"><i class="fa-solid fa-pen me-2"></i>Edit Profile</a>
                </div>
            </div>

            <?php if(isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Completion Banner -->
            <?php renderProfileCompletionBanner($company_status, 'edit_company_profile.php', 'Company Profile', 'All company profile details are required to attract top candidates and post jobs.'); ?>

            <div class="card card-static mb-4 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <?php if (!empty($company['logo_url'])): ?>
                            <div class="me-3 p-1 border rounded bg-white shadow-sm d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                                <img src="<?php echo htmlspecialchars($company['logo_url']); ?>" alt="Company Logo" class="rounded" style="max-width: 56px; max-height: 56px; object-fit: contain;">
                            </div>
                        <?php else: ?>
                            <div class="avatar-circle me-3">
                                <i class="fa-solid fa-building"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h4 class="mb-0 fw-bold"><?php echo htmlspecialchars(!empty($company['company_name']) ? $company['company_name'] : $user['name']); ?></h4>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <span class="badge bg-success rounded-pill px-3">Company</span>
                                <span class="text-muted small"><i class="fa-solid fa-user me-1"></i>Contact: <?php echo htmlspecialchars($user['name']); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="stat-card gradient-primary text-white h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-label">Posted Jobs</div>
                                        <div class="stat-value"><?php echo $total_jobs; ?></div>
                                    </div>
                                    <i class="fa-solid fa-briefcase stat-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-card gradient-success text-white h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-label">Total Applicants</div>
                                        <div class="stat-value"><?php echo $total_applicants; ?></div>
                                    </div>
                                    <i class="fa-solid fa-users stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($company): ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Email Address</label>
                                <p class="fw-semibold mb-0"><i class="fa-solid fa-envelope me-2 text-primary"></i><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Industry / Sector</label>
                                <p class="fw-semibold mb-0">
                                    <i class="fa-solid fa-industry me-2 text-primary"></i>
                                    <?php echo !empty($company['industry']) ? htmlspecialchars($company['industry']) : '<span class="text-danger small fst-italic">Missing (Required)</span>'; ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Company Website</label>
                                <p class="fw-semibold mb-0">
                                    <i class="fa-solid fa-globe me-2 text-primary"></i>
                                    <?php if(!empty($company['website'])): ?>
                                        <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank" class="text-primary text-decoration-underline"><?php echo htmlspecialchars($company['website']); ?></a>
                                    <?php else: ?>
                                        <span class="text-danger small fst-italic">Missing (Required)</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">Location / City</label>
                                <p class="fw-semibold mb-0">
                                    <i class="fa-solid fa-location-dot me-2 text-primary"></i>
                                    <?php echo !empty($company['location']) ? htmlspecialchars($company['location']) : '<span class="text-danger small fst-italic">Missing (Required)</span>'; ?>
                                </p>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="text-muted small">About Company</label>
                                <div class="p-3 bg-light rounded-3 mt-1">
                                    <p class="mb-0 text-dark">
                                        <?php echo !empty($company['description']) ? nl2br(htmlspecialchars($company['description'])) : '<span class="text-danger small fst-italic">No description provided (Required) - <a href="edit_company_profile.php">Add description</a></span>'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info"><i class="fa-solid fa-info-circle me-2"></i>Complete your company profile to attract more applicants.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>