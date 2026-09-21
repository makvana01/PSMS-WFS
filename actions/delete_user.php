<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../auth/login.php');
}

// Check if user ID and role are provided in the URL
if (isset($_GET['id']) && isset($_GET['role'])) {
    $user_id = (int)$_GET['id'];
    $role = sanitize_input($_GET['role']);
    $action = isset($_GET['action']) ? sanitize_input($_GET['action']) : 'deactivate';
    
    // Prevent admin from deactivating or permanently deleting their own account
    if (in_array($action, ['deactivate', 'hard_delete']) && $user_id === (int)$_SESSION['user_id']) {
        $_SESSION['error_msg'] = "Security Violation: You cannot deactivate or permanently delete your own logged-in admin account.";
        redirect('../pages/admin_dashboard.php');
    }
    
    try {
        $pdo->beginTransaction();

        if ($action === 'hard_delete') {
            // Permanent Hard Delete from database and file system
            if ($role === 'student') {
                // Remove uploaded student files if they exist
                $stmt_files = $pdo->prepare("SELECT resume_url, id_card_url, profile_pic_url FROM students WHERE user_id = ?");
                $stmt_files->execute([$user_id]);
                $st_files = $stmt_files->fetch(PDO::FETCH_ASSOC);
                if ($st_files) {
                    foreach (['resume_url', 'id_card_url', 'profile_pic_url'] as $fkey) {
                        if (!empty($st_files[$fkey])) {
                            $fpath = $st_files[$fkey];
                            if (file_exists($fpath)) {
                                @unlink($fpath);
                            } elseif (file_exists('../' . ltrim($fpath, './'))) {
                                @unlink('../' . ltrim($fpath, './'));
                            }
                        }
                    }
                }

                // Delete applications and student profile
                $pdo->prepare("DELETE FROM applications WHERE student_id = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM students WHERE user_id = ?")->execute([$user_id]);
            } elseif ($role === 'company') {
                // Remove uploaded company logo if it exists
                $stmt_files = $pdo->prepare("SELECT logo_url FROM companies WHERE user_id = ?");
                $stmt_files->execute([$user_id]);
                $comp_files = $stmt_files->fetch(PDO::FETCH_ASSOC);
                if ($comp_files && !empty($comp_files['logo_url'])) {
                    $fpath = $comp_files['logo_url'];
                    if (file_exists($fpath)) {
                        @unlink($fpath);
                    } elseif (file_exists('../' . ltrim($fpath, './'))) {
                        @unlink('../' . ltrim($fpath, './'));
                    }
                }

                // Delete applications for all company jobs
                $stmt_j = $pdo->prepare("SELECT id FROM jobs WHERE company_id = ?");
                $stmt_j->execute([$user_id]);
                $comp_job_ids = $stmt_j->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($comp_job_ids)) {
                    $placeholders = implode(',', array_fill(0, count($comp_job_ids), '?'));
                    $pdo->prepare("DELETE FROM applications WHERE job_id IN ($placeholders)")->execute($comp_job_ids);
                }

                $pdo->prepare("DELETE FROM jobs WHERE company_id = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM companies WHERE user_id = ?")->execute([$user_id]);
            }

            // Finally, permanently delete from users table
            $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt_del->execute([$user_id]);

            $pdo->commit();
            $_SESSION['success_msg'] = ucfirst($role) . ' permanently deleted (hard deleted) successfully.';
        } elseif ($action === 'restore') {
            // Restore / Activate (1)
            $query_user = "UPDATE users SET deleted_at = NULL WHERE id = ?";
            $stmt = $pdo->prepare($query_user);
            $stmt->execute([$user_id]);

            if ($role === 'student') {
                $query_student = "UPDATE students SET deleted_at = NULL WHERE user_id = ?";
                $stmt = $pdo->prepare($query_student);
                $stmt->execute([$user_id]);

                $query_applications = "UPDATE applications SET deleted_at = NULL WHERE student_id = ?";
                $stmt = $pdo->prepare($query_applications);
                $stmt->execute([$user_id]);
            } elseif ($role === 'company') {
                $query_company = "UPDATE companies SET deleted_at = NULL WHERE user_id = ?";
                $stmt = $pdo->prepare($query_company);
                $stmt->execute([$user_id]);

                $query_jobs = "SELECT id FROM jobs WHERE company_id = ?";
                $stmt = $pdo->prepare($query_jobs);
                $stmt->execute([$user_id]);
                $jobs = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($jobs)) {
                    $placeholders = implode(',', array_fill(0, count($jobs), '?'));
                    $query_apps = "UPDATE applications SET deleted_at = NULL WHERE job_id IN ($placeholders)";
                    $stmt = $pdo->prepare($query_apps);
                    $stmt->execute($jobs);
                }

                $query_restore_jobs = "UPDATE jobs SET deleted_at = NULL WHERE company_id = ?";
                $stmt = $pdo->prepare($query_restore_jobs);
                $stmt->execute([$user_id]);
            }

            $pdo->commit();
            $_SESSION['success_msg'] = ucfirst($role) . ' activated (1) successfully.';
        } else {
            // Soft delete / Deactivate (0)
            $query_user = "UPDATE users SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $pdo->prepare($query_user);
            $stmt->execute([$user_id]);

            if ($role === 'student') {
                $query_student = "UPDATE students SET deleted_at = CURRENT_TIMESTAMP WHERE user_id = ?";
                $stmt = $pdo->prepare($query_student);
                $stmt->execute([$user_id]);

                $query_applications = "UPDATE applications SET deleted_at = CURRENT_TIMESTAMP WHERE student_id = ?";
                $stmt = $pdo->prepare($query_applications);
                $stmt->execute([$user_id]);
            } elseif ($role === 'company') {
                $query_company = "UPDATE companies SET deleted_at = CURRENT_TIMESTAMP WHERE user_id = ?";
                $stmt = $pdo->prepare($query_company);
                $stmt->execute([$user_id]);

                $query_jobs = "SELECT id FROM jobs WHERE company_id = ?";
                $stmt = $pdo->prepare($query_jobs);
                $stmt->execute([$user_id]);
                $jobs = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($jobs)) {
                    $placeholders = implode(',', array_fill(0, count($jobs), '?'));
                    $query_apps = "UPDATE applications SET deleted_at = CURRENT_TIMESTAMP WHERE job_id IN ($placeholders)";
                    $stmt = $pdo->prepare($query_apps);
                    $stmt->execute($jobs);
                }

                $query_hide_jobs = "UPDATE jobs SET deleted_at = CURRENT_TIMESTAMP WHERE company_id = ?";
                $stmt = $pdo->prepare($query_hide_jobs);
                $stmt->execute([$user_id]);
            }

            $pdo->commit();
            $_SESSION['success_msg'] = ucfirst($role) . ' deactivated (0) successfully.';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = 'Failed to ' . $action . ' ' . $role . '. Please try again.';
    }
}

// Redirect safely back to admin dashboard or previous filter tab
$referer = $_SERVER['HTTP_REFERER'] ?? '';
safe_redirect($referer, '../pages/admin_dashboard.php');
?>
