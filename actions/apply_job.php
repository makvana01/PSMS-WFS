<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    redirect('../auth/login.php');
}

// Check if the form was submitted with a job ID
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['job_id'])) {
    $job_id = (int)$_POST['job_id'];
    $student_id = $_SESSION['user_id'];
    
    // Check if the student's profile is 100% complete and verified
    $profile_status = getStudentProfileStatus($pdo, $student_id);
    
    if (!$profile_status || empty($profile_status['user']) || !$profile_status['user']['is_verified']) {
        $_SESSION['verify_email'] = $profile_status['user']['email'] ?? '';
        $_SESSION['error_msg'] = 'Please verify your email address before applying for jobs.';
        redirect('../auth/verify_otp.php');
    }
    
    if (!$profile_status['is_complete']) {
        $missing_list = implode(', ', $profile_status['missing_fields'] ?? []);
        $_SESSION['error_msg'] = 'You must complete all required profile fields before applying for a job. Missing: ' . $missing_list;
        redirect('../pages/edit_profile.php');
    }
    
    // Verify that the job exists, is Approved, is Open, is not deleted, and deadline has not passed
    $query_job = "
        SELECT j.*, u.deleted_at as company_deleted 
        FROM jobs j 
        JOIN users u ON j.company_id = u.id 
        WHERE j.id = ? AND j.deleted_at IS NULL AND u.deleted_at IS NULL
    ";
    $stmt_job = $pdo->prepare($query_job);
    $stmt_job->execute([$job_id]);
    $job = $stmt_job->fetch();

    if (!$job) {
        $_SESSION['error_msg'] = 'Job not found or is no longer available.';
        redirect('../pages/jobs.php');
    }

    if ($job['approval_status'] !== 'Approved' || $job['status'] !== 'Open') {
        $_SESSION['error_msg'] = 'This job is not currently open for new applications.';
        redirect('../pages/jobs.php');
    }

    if (strtotime($job['last_date']) < strtotime(date('Y-m-d'))) {
        $_SESSION['error_msg'] = 'The application deadline for this job has expired (' . date('M d, Y', strtotime($job['last_date'])) . ').';
        redirect('../pages/jobs.php');
    }

    // Check if the student has already applied for this job
    $query_check = "SELECT id, deleted_at FROM applications WHERE job_id = ? AND student_id = ?";
    $stmt = $pdo->prepare($query_check);
    $stmt->execute([$job_id, $student_id]);
    $existing_app = $stmt->fetch();

    if ($existing_app) {
        if (empty($existing_app['deleted_at'])) {
            $_SESSION['error_msg'] = 'You have already applied for this job.';
        } else {
            // Reactivate previous application
            $stmt_reactivate = $pdo->prepare("UPDATE applications SET deleted_at = NULL, status = 'Pending', applied_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($stmt_reactivate->execute([$existing_app['id']])) {
                $_SESSION['success_msg'] = 'Application re-submitted successfully!';
            } else {
                $_SESSION['error_msg'] = 'Failed to submit application. Please try again.';
            }
        }
    } else {
        // Otherwise, insert a new application record
        $query_insert = "INSERT INTO applications (job_id, student_id) VALUES (?, ?)";
        $insert = $pdo->prepare($query_insert);
        
        if ($insert->execute([$job_id, $student_id])) {
            $_SESSION['success_msg'] = 'Application submitted successfully!';
        } else {
            $_SESSION['error_msg'] = 'Failed to submit application. Please try again.';
        }
    }
}

// Redirect back to the jobs page
redirect('../pages/jobs.php');
?>
