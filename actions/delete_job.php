<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a company or admin
if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['company', 'admin'])) {
    redirect('../auth/login.php');
}

// Check if a job ID was provided in the URL
if (isset($_GET['id'])) {
    $job_id = (int)$_GET['id'];
    $action = isset($_GET['action']) ? sanitize_input($_GET['action']) : 'deactivate';
    $role = $_SESSION['user_role'];
    $user_id = $_SESSION['user_id'];
    
    try {
        $pdo->beginTransaction();

        if ($action === 'hard_delete') {
            // Permanent Hard Delete from database
            if ($role === 'admin') {
                $pdo->prepare("DELETE FROM applications WHERE job_id = ?")->execute([$job_id]);
                $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
                $stmt->execute([$job_id]);
            } else {
                $stmt_check = $pdo->prepare("SELECT id FROM jobs WHERE id = ? AND company_id = ?");
                $stmt_check->execute([$job_id, $user_id]);
                if ($stmt_check->fetch()) {
                    $pdo->prepare("DELETE FROM applications WHERE job_id = ?")->execute([$job_id]);
                    $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ? AND company_id = ?");
                    $stmt->execute([$job_id, $user_id]);
                }
            }

            $pdo->commit();
            $_SESSION['success_msg'] = 'Job permanently deleted (hard deleted) successfully.';
        } elseif ($action === 'restore') {
            // Activate / Restore (1)
            if ($role === 'admin') {
                $query = "UPDATE jobs SET deleted_at = NULL WHERE id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$job_id]);
            } else {
                $query = "UPDATE jobs SET deleted_at = NULL WHERE id = ? AND company_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$job_id, $user_id]);
            }

            if ($stmt->rowCount() > 0) {
                // Restore applications associated with this job
                $pdo->prepare("UPDATE applications SET deleted_at = NULL WHERE job_id = ?")->execute([$job_id]);
                $pdo->commit();
                $_SESSION['success_msg'] = 'Job and associated applications activated (1) successfully.';
            } else {
                $pdo->commit();
                $_SESSION['success_msg'] = 'Job restored or already active.';
            }
        } else {
            // Soft delete / Deactivate (0)
            if ($role === 'admin') {
                $query = "UPDATE jobs SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$job_id]);
            } else {
                $query = "UPDATE jobs SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND company_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$job_id, $user_id]);
            }

            if ($stmt->rowCount() > 0) {
                // Deactivate applications associated with this job
                $pdo->prepare("UPDATE applications SET deleted_at = CURRENT_TIMESTAMP WHERE job_id = ?")->execute([$job_id]);
                $pdo->commit();
                $_SESSION['success_msg'] = 'Job deactivated (0) successfully.';
            } else {
                $pdo->commit();
                $_SESSION['error_msg'] = 'Failed to deactivate job or job not found.';
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = 'Database transaction failed while updating job.';
    }
}

// Redirect safely to referring page if available, else appropriate dashboard
$default_redirect = ($role === 'admin') ? '../pages/admin_dashboard.php' : '../pages/company_dashboard.php';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
safe_redirect($referer, $default_redirect);
?>
