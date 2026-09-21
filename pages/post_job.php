<?php
$page_title = 'Post Job';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and their role is company
if (!isLoggedIn() || $_SESSION['user_role'] !== 'company') {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$company_status = getCompanyProfileStatus($pdo, $user_id);

$error = '';
$success = '';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify company profile is complete before allowing job posting
    if (!$company_status || !$company_status['is_complete']) {
        $missing_list = implode(', ', $company_status['missing_fields'] ?? []);
        $error = "You must complete all required company profile fields before posting a job. Missing: " . $missing_list;
    } else {
        // Clean all user inputs to prevent hacking
        $title = sanitize_input($_POST['title'] ?? '');
        $location = sanitize_input($_POST['location'] ?? '');
        $salary = sanitize_input($_POST['salary'] ?? '');
        $eligibility = sanitize_input($_POST['eligibility'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $last_date = sanitize_input($_POST['last_date'] ?? '');
        
        // Get company details from the current session / company data
        $company_id = $_SESSION['user_id'];
        $company_name = !empty($company_status['company']['company_name']) ? $company_status['company']['company_name'] : $_SESSION['user_name'];
        
        if (empty($title) || empty($location) || empty($salary) || empty($eligibility) || empty($description) || empty($last_date)) {
            $error = "All job fields are required.";
        } elseif (strtotime($last_date) < strtotime(date('Y-m-d'))) {
            $error = "Application deadline date cannot be in the past. Please select a valid future date.";
        } else {
            // Insert the new job into the database
            $insert_query = "INSERT INTO jobs (company_id, company_name, title, location, salary, eligibility, description, last_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($insert_query);
            
            // If execution is successful, show success message
            if ($stmt->execute([$company_id, $company_name, $title, $location, $salary, $eligibility, $description, $last_date])) {
                $success = "Job posted successfully and is pending admin approval.";
            } else {
                $error = "Failed to post job. Please try again.";
            }
        }
    }
}
include '../includes/header.php';
?>

<div class="py-4 fade-in">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="page-header">
                    <h2><i class="fa-solid fa-plus me-2"></i>Post a New Job</h2>
                </div>
                <a href="company_dashboard.php" class="btn btn-outline-secondary rounded-pill"><i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard</a>
            </div>

            <!-- Profile Completion Banner if incomplete -->
            <?php if (!$company_status || !$company_status['is_complete']): ?>
                <?php renderProfileCompletionBanner($company_status, 'edit_company_profile.php', 'Company Profile', 'You cannot post jobs until your company profile is 100% complete.'); ?>
            <?php endif; ?>
            
            <div class="card card-static shadow-sm">
                <div class="card-body p-4">
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fa-solid fa-circle-check me-2"></i><?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold"><i class="fa-solid fa-heading me-1"></i>Job Title</label>
                                <input type="text" name="title" class="form-control" maxlength="100" required placeholder="e.g. Software Developer">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold"><i class="fa-solid fa-location-dot me-1"></i>Location</label>
                                <input type="text" name="location" class="form-control" maxlength="150" required placeholder="e.g. Surat, Bangalore, Remote">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold"><i class="fa-solid fa-money-bill-wave me-1"></i>Salary Package</label>
                                <input type="text" name="salary" class="form-control" maxlength="50" required placeholder="e.g. 5-8 LPA">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold"><i class="fa-solid fa-calendar me-1"></i>Last Date to Apply</label>
                                <input type="date" name="last_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold"><i class="fa-solid fa-graduation-cap me-1"></i>Eligibility Criteria</label>
                            <input type="text" name="eligibility" class="form-control" maxlength="200" required placeholder="e.g. BCA/MCA with 60%+ or 6.5+ CGPA">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold"><i class="fa-solid fa-align-left me-1"></i>Job Description</label>
                            <textarea name="description" class="form-control" rows="5" required placeholder="Describe the job role, responsibilities, and requirements..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-2"><i class="fa-solid fa-paper-plane me-2"></i>Post Job</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
