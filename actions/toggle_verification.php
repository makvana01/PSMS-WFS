<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../auth/login.php');
}

// Check if user ID and target status are provided
if (isset($_GET['id']) && isset($_GET['status'])) {
    $user_id = (int)$_GET['id'];
    $new_status = ((int)$_GET['status'] === 1) ? 1 : 0;
    $role = isset($_GET['role']) ? sanitize_input($_GET['role']) : 'student';

    // Prevent admin from modifying their own account verification
    if ($user_id === (int)$_SESSION['user_id']) {
        $_SESSION['error_msg'] = "Security Violation: You cannot alter your own admin verification state.";
        redirect('../pages/admin_dashboard.php');
    }

    try {
        // Fetch student name for clear feedback
        $stmt_user = $pdo->prepare("SELECT name, email, role FROM users WHERE id = ?");
        $stmt_user->execute([$user_id]);
        $target_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if (!$target_user) {
            $_SESSION['error_msg'] = "Error: User record not found.";
            redirect('../pages/admin_dashboard.php');
        }

        // Update student document verification status in students table
        $update_stmt = $pdo->prepare("UPDATE students SET is_verified = ? WHERE user_id = ?");
        $update_stmt->execute([$new_status, $user_id]);

        if ($new_status === 1) {
            $_SESSION['success_msg'] = "✅ Verification Confirmed: Student <strong>" . htmlspecialchars($target_user['name']) . "</strong>'s I-Card & Resume have been verified successfully.";
        } else {
            $_SESSION['warning_msg'] = "⚠️ Status Updated: Student <strong>" . htmlspecialchars($target_user['name']) . "</strong> has been marked as Unverified.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database Error: " . $e->getMessage();
    }
} else {
    $_SESSION['error_msg'] = "Invalid verification request parameters.";
}

redirect('../pages/admin_dashboard.php');
