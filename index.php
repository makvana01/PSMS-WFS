<?php
$page_title = 'Home';
require_once 'config/db.php';
require_once 'includes/functions.php';

include 'includes/header.php';
?>

<div class="hero-section fade-in">
    <div class="row align-items-center py-3 py-lg-4">
        <div class="col-lg-6 mb-4 mb-lg-0">
            <h1 class="display-5 fw-bold text-primary mb-3" style="line-height: 1.25;">
                Welcome to<br>Placement Management<br>System
            </h1>
            <p class="lead text-muted mb-4" style="font-size: 1.1rem; line-height: 1.7;">
                Connect top talent with leading companies. Streamline your placement process, manage applications, and
                discover career opportunities.
            </p>
            <?php if (!isLoggedIn()): ?>
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <a href="<?php echo app_url('auth/register.php'); ?>"
                        class="btn btn-primary btn-lg px-4 rounded-pill shadow-sm">
                        <i class="fa-solid fa-rocket me-2"></i>Get Started
                    </a>
                    <a href="<?php echo app_url('pages/jobs.php'); ?>" class="btn btn-outline-primary btn-lg px-4 rounded-pill">
                        <i class="fa-solid fa-briefcase me-2"></i>Explore Jobs
                    </a>
                    <a href="<?php echo app_url('auth/login.php'); ?>" class="btn btn-light text-primary btn-lg px-4 rounded-pill border shadow-sm">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Login
                    </a>
                </div>
            <?php else: ?>
                <?php
                $dash_target = 'pages/jobs.php';
                if (isset($_SESSION['user_role'])) {
                    if ($_SESSION['user_role'] === 'student')
                        $dash_target = 'pages/student_dashboard.php';
                    elseif ($_SESSION['user_role'] === 'company')
                        $dash_target = 'pages/company_dashboard.php';
                    elseif ($_SESSION['user_role'] === 'admin')
                        $dash_target = 'pages/admin_dashboard.php';
                }
                ?>
                <a href="<?php echo app_url($dash_target); ?>" class="btn btn-primary btn-lg px-4 rounded-pill shadow-sm">
                    <i class="fa-solid fa-gauge me-2"></i>Go to Dashboard
                </a>
            <?php endif; ?>
        </div>
        <div class="col-lg-6">
            <div class="hero-illustration text-center">
                <i class="fa-solid fa-briefcase mb-3"></i>
                <h3 class="fw-bold text-primary">Your Career Starts Here</h3>
                <p class="text-muted mb-4">Join thousands of students and companies.</p>
                <div class="row g-3 mt-2">
                    <div class="col-4">
                        <a href="<?php echo app_url('auth/register.php?role=student'); ?>"
                            class="text-decoration-none text-dark">
                            <div class="p-3 bg-white rounded-3 shadow-sm h-100 transition-hover">
                                <i class="fa-solid fa-user-graduate text-primary mb-2" style="font-size: 1.5rem;"></i>
                                <h6 class="fw-bold mb-0">Students</h6>
                            </div>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="<?php echo app_url('auth/register.php?role=company'); ?>"
                            class="text-decoration-none text-dark">
                            <div class="p-3 bg-white rounded-3 shadow-sm h-100 transition-hover">
                                <i class="fa-solid fa-building text-success mb-2" style="font-size: 1.5rem;"></i>
                                <h6 class="fw-bold mb-0">Companies</h6>
                            </div>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="<?php echo app_url('pages/jobs.php'); ?>" class="text-decoration-none text-dark">
                            <div class="p-3 bg-white rounded-3 shadow-sm h-100 transition-hover">
                                <i class="fa-solid fa-file-lines text-info mb-2" style="font-size: 1.5rem;"></i>
                                <h6 class="fw-bold mb-0">Jobs</h6>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Features Section -->
<div class="row g-4 mt-2 mb-4 slide-up">
    <div class="col-md-4">
        <a href="<?php echo app_url('pages/jobs.php'); ?>" class="text-decoration-none text-dark">
            <div class="feature-card card p-4 h-100">
                <i class="fa-solid fa-magnifying-glass"></i>
                <h5>Find Jobs</h5>
                <p class="text-muted">Browse through hundreds of job postings from top companies.</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?php echo app_url('auth/register.php'); ?>" class="text-decoration-none text-dark">
            <div class="feature-card card p-4 h-100">
                <i class="fa-solid fa-paper-plane"></i>
                <h5>Apply Easily</h5>
                <p class="text-muted">Apply to multiple positions with a single click.</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?php echo isLoggedIn() ? app_url('pages/student_dashboard.php') : app_url('auth/login.php'); ?>"
            class="text-decoration-none text-dark">
            <div class="feature-card card p-4 h-100">
                <i class="fa-solid fa-chart-line"></i>
                <h5>Track Status</h5>
                <p class="text-muted">Monitor your application status in real-time.</p>
            </div>
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>