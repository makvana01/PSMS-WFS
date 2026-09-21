<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Invalidate persistent remember token in database
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Invalidate remember_me cookie in client browser
if (isset($_COOKIE['remember_me'])) {
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('remember_me', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Cleanly wipe session data, expire PHPSESSID cookie, and destroy session
destroyUserSession();

// Clear browser cache and storage for this origin on logout
if (!headers_sent()) {
    header('Clear-Site-Data: "cache", "storage"');
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0");
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
}

// Redirect to login page with logout confirmation
redirect('login.php?logout=1');
?>
