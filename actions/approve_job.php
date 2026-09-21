<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../auth/login.php');
}

// Check if a job ID and a status were provided in the URL
if (isset($_GET['id']) && isset($_GET['status'])) {
    $job_id = (int)$_GET['id'];
    
    // Ensure status is strictly 'Approved' or 'Rejected'
    if ($_GET['status'] == 'Approved') {
        $status = 'Approved';
    } else {
        $status = 'Rejected';
    }
    
    // Update the job status in the database
    $query_update = "UPDATE jobs SET approval_status = ? WHERE id = ?";
    $stmt = $pdo->prepare($query_update);
    
    if ($stmt->execute([$status, $job_id])) {
        // strtolower converts 'Approved' to 'approved' for the message
        $_SESSION['success_msg'] = 'Job ' . strtolower($status) . ' successfully.';
    } else {
        $_SESSION['error_msg'] = 'Failed to update job status.';
    }
}

// Redirect safely back to admin dashboard or previous page
$referer = $_SERVER['HTTP_REFERER'] ?? '';
safe_redirect($referer, '../pages/admin_dashboard.php');
?>
