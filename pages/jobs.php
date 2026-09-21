<?php
$page_title = 'Jobs';
require_once '../config/db.php';
require_once '../includes/functions.php';
include '../includes/header.php';

// --- Pagination and Search Logic ---

// Get search keyword and location from URL (GET parameters)
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';

// Get current page number, default to 1 if not provided
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 6; // Number of jobs to show per page
$offset = ($page - 1) * $limit; // Calculate where to start fetching from database

// Basic conditions: job must be approved, open, and not deleted
$where_clauses = ["approval_status = 'Approved'", "status = 'Open'", "deleted_at IS NULL"];
$params = [];

// If user searched for a keyword, add it to conditions
if ($keyword !== '') {
    $where_clauses[] = "(title LIKE ? OR company_name LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

// If user searched for a location, add it to conditions
if ($location !== '') {
    $where_clauses[] = "location LIKE ?";
    $params[] = "%$location%";
}

// Combine all conditions with ' AND '
$where_sql = implode(' AND ', $where_clauses);

// --- Get Total Jobs for Pagination ---
$query_count = "SELECT COUNT(*) FROM jobs WHERE $where_sql";
$count_stmt = $pdo->prepare($query_count);
$count_stmt->execute($params);
$total_jobs = $count_stmt->fetchColumn();

// Calculate total pages needed
$total_pages = ceil($total_jobs / $limit);

// --- Fetch Jobs for Current Page ---
$query_jobs = "SELECT * FROM jobs WHERE $where_sql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query_jobs);
$stmt->execute($params);
$jobs = $stmt->fetchAll();
?>

<div class="py-4 fade-in">
    <div class="page-header mb-4">
        <h2><i class="fa-solid fa-briefcase me-2"></i>Available Jobs</h2>
    </div>

    <!-- Search Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="jobs.php" class="row g-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" name="keyword" class="form-control" placeholder="Job Title or Company" value="<?php echo htmlspecialchars($keyword); ?>">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-location-dot text-muted"></i></span>
                        <input type="text" name="location" class="form-control" placeholder="Location" value="<?php echo htmlspecialchars($location); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                    <?php if($keyword !== '' || $location !== ''): ?>
                        <a href="jobs.php" class="btn btn-link w-100 text-decoration-none mt-1" style="font-size: 0.85rem;">Clear Filters</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if(isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if(empty($jobs)): ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <h5>No jobs available right now</h5>
                    <p class="text-muted">Check back later for new opportunities!</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach($jobs as $job): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card job-card h-100">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title fw-bold text-primary mb-0"><?php echo htmlspecialchars($job['title']); ?></h5>
                                <span class="badge bg-success rounded-pill">Open</span>
                            </div>
                            <h6 class="card-subtitle mb-3 text-muted"><i class="fa-solid fa-building me-2"></i><?php echo htmlspecialchars($job['company_name']); ?></h6>
                            
                            <div class="mb-3 flex-grow-1">
                                <p class="mb-2"><i class="fa-solid fa-location-dot me-2 text-muted"></i><?php echo htmlspecialchars($job['location']); ?></p>
                                <p class="mb-2"><i class="fa-solid fa-money-bill-wave me-2 text-muted"></i><?php echo htmlspecialchars($job['salary']); ?></p>
                                <p class="mb-0"><i class="fa-solid fa-calendar-alt me-2 text-muted"></i>Last Date: <?php echo date('M d, Y', strtotime($job['last_date'])); ?></p>
                            </div>
                            
                            <?php if(isLoggedIn() && $_SESSION['user_role'] == 'student'): ?>
                                <?php
                                    // Check if student has already applied to this job
                                    $query_app = "SELECT id FROM applications WHERE job_id = ? AND student_id = ? AND deleted_at IS NULL";
                                    $app_check = $pdo->prepare($query_app);
                                    $app_check->execute([$job['id'], $_SESSION['user_id']]);
                                    $applied = $app_check->fetch();
                                ?>
                                <?php if($applied): ?>
                                    <button class="btn btn-secondary w-100 rounded-pill" disabled><i class="fa-solid fa-check me-2"></i>Applied</button>
                                <?php else: ?>
                                    <form method="POST" action="../actions/apply_job.php">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" class="btn btn-primary w-100 rounded-pill"><i class="fa-solid fa-paper-plane me-2"></i>Apply Now</button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif(!isLoggedIn()): ?>
                                <a href="../auth/login.php" class="btn btn-outline-primary w-100 rounded-pill"><i class="fa-solid fa-right-to-bracket me-2"></i>Login to Apply</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation" class="mt-5">
        <ul class="pagination justify-content-center">
            <?php 
                $query_string = "";
                if ($keyword !== '') $query_string .= "&keyword=" . urlencode($keyword);
                if ($location !== '') $query_string .= "&location=" . urlencode($location);
            ?>
            
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $query_string; ?>">Previous</a>
            </li>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $query_string; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $query_string; ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
