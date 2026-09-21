<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is company or admin
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['company', 'admin'])) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    $new_status = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';
    $allowed_statuses = ['Pending', 'Reviewed', 'Shortlisted', 'Rejected', 'Selected'];

    if ($application_id > 0 && in_array($new_status, $allowed_statuses)) {
        // If company, verify the application belongs to a job posted by this company
        if ($user_role === 'company') {
            $check_query = "
                SELECT a.id, a.job_id 
                FROM applications a 
                JOIN jobs j ON a.job_id = j.id 
                WHERE a.id = ? AND j.company_id = ?
            ";
            $stmt = $pdo->prepare($check_query);
            $stmt->execute([$application_id, $user_id]);
            $app = $stmt->fetch();
        } else {
            // Admin can update any
            $stmt = $pdo->prepare("SELECT id, job_id FROM applications WHERE id = ?");
            $stmt->execute([$application_id]);
            $app = $stmt->fetch();
        }

        if ($app) {
            $update = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
            if ($update->execute([$new_status, $application_id])) {
                $_SESSION['success_msg'] = "Application status updated to '$new_status' successfully!";
            } else {
                $_SESSION['error_msg'] = "Failed to update application status.";
            }
        } else {
            $_SESSION['error_msg'] = "Application not found or unauthorized.";
        }
    } else {
        $_SESSION['error_msg'] = "Invalid status selected.";
    }
}

// Handle GET request for quick soft delete / restore
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $application_id = (int)$_GET['id'];
    $action = isset($_GET['action']) ? sanitize_input($_GET['action']) : '';

    // Verify ownership if user is a company
    if ($user_role === 'company') {
        $check_query = "
            SELECT a.id, a.job_id 
            FROM applications a 
            JOIN jobs j ON a.job_id = j.id 
            WHERE a.id = ? AND j.company_id = ?
        ";
        $stmt_chk = $pdo->prepare($check_query);
        $stmt_chk->execute([$application_id, $user_id]);
        $app = $stmt_chk->fetch();
    } else {
        // Admin
        $stmt_chk = $pdo->prepare("SELECT id FROM applications WHERE id = ?");
        $stmt_chk->execute([$application_id]);
        $app = $stmt_chk->fetch();
    }

    if ($app) {
        if ($action === 'deactivate') {
            $stmt = $pdo->prepare("UPDATE applications SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$application_id]);
            $_SESSION['success_msg'] = "Application deactivated (0) successfully.";
        } elseif ($action === 'restore') {
            $stmt = $pdo->prepare("UPDATE applications SET deleted_at = NULL WHERE id = ?");
            $stmt->execute([$application_id]);
            $_SESSION['success_msg'] = "Application activated (1) successfully.";
        }
    } else {
        $_SESSION['error_msg'] = "Application not found or unauthorized.";
    }
}

// Redirect safely to referring page or default dashboard
$default_redirect = ($user_role === 'admin') ? '../pages/admin_dashboard.php' : '../pages/company_dashboard.php';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
safe_redirect($referer, $default_redirect);
?>
