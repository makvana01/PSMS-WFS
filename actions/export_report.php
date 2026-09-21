<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../auth/login.php');
}

// Check if the report type is provided in the URL
if (!isset($_GET['type'])) {
    redirect('../pages/admin_dashboard.php');
}

$type = $_GET['type'];
// Create a unique filename with the current date and time
$filename = "report_" . $type . "_" . date('Ymd_His') . ".csv";

// Setup headers for CSV download so the browser knows it's a file to download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open the output stream to write CSV data directly to the browser
$output = fopen('php://output', 'w');

if ($type === 'students') {
    // Write column headers for students
    fputcsv($output, array('ID', 'Name', 'Email', 'Verified', 'Registered At'));
    $query_students = "SELECT id, name, email, is_verified, created_at FROM users WHERE role = 'student' AND is_verified = 1 AND deleted_at IS NULL ORDER BY created_at DESC";
    $stmt = $pdo->query($query_students);
    
    // Write data row by row
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['is_verified'] = $row['is_verified'] ? 'Yes' : 'No';
        fputcsv($output, $row);
    }
} elseif ($type === 'companies') {
    // Write column headers for companies
    fputcsv($output, array('ID', 'Company Name', 'Email', 'Verified', 'Registered At'));
    $query_companies = "SELECT id, name, email, is_verified, created_at FROM users WHERE role = 'company' AND is_verified = 1 AND deleted_at IS NULL ORDER BY created_at DESC";
    $stmt = $pdo->query($query_companies);
    
    // Write data row by row
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['is_verified'] = $row['is_verified'] ? 'Yes' : 'No';
        fputcsv($output, $row);
    }
} elseif ($type === 'jobs') {
    // Write column headers for jobs
    fputcsv($output, array('ID', 'Job Title', 'Company Name', 'Location', 'Salary', 'Status', 'Approval Status', 'Posted At'));
    $query_jobs = "SELECT id, title, company_name, location, salary, status, approval_status, created_at FROM jobs WHERE deleted_at IS NULL ORDER BY created_at DESC";
    $stmt = $pdo->query($query_jobs);
    
    // Write data row by row
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} else {
    // Invalid type provided, cancel the download and redirect back
    fclose($output);
    header("Location: ../pages/admin_dashboard.php");
    exit();
}

// Close the file output
fclose($output);
exit();
?>
