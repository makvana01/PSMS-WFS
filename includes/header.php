<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Prevent Browser Caching for Protected Pages on Back/Forward Navigation -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Placement Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/placement-management-system/assets/css/style.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        // Immediately detect back-forward cache or back button navigation
        window.addEventListener('pageshow', function(event) {
            var navEntries = (window.performance && window.performance.getEntriesByType) ? window.performance.getEntriesByType('navigation') : null;
            var isBackNav = event.persisted || 
                (window.performance && window.performance.navigation && window.performance.navigation.type === 2) ||
                (navEntries && navEntries.length > 0 && navEntries[0].type === 'back_forward');
            if (isBackNav) {
                window.location.replace(window.location.href);
            }
        });
    </script>
</head>
<body class="d-flex flex-column min-vh-100">
<?php include 'navbar.php'; ?>
<main class="main-content flex-grow-1">
    <div class="container py-3 py-md-4">
