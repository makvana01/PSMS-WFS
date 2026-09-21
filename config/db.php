<?php
// config/db.php

$host = '127.0.0.1';
$db   = 'placement_management';
$user = 'root'; // Change if required
$pass = '';     // Change if required
$charset = 'utf8mb4';

// Database connection string
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Show errors if any
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as an associative array
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    // Create a new PDO instance (connect to the database)
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // If connection fails, throw an error
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Enforce strict session mode and cookie-only sessions
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);

// Auto-login from Remember Me cookie and secure session initialization
if (session_status() === PHP_SESSION_NONE) {
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Check if user is not logged in but has a remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $parts = explode(':', $_COOKIE['remember_me']);
    
    // If the cookie has two parts (user_id and token)
    if (count($parts) === 2) {
        $cookie_user_id = (int)$parts[0];
        $cookie_token = $parts[1];
        
        // Find the user in the database
        $query = "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$cookie_user_id]);
        $user = $stmt->fetch();
        
        // If user exists, token matches, and user is verified
        if ($user && !empty($user['remember_token']) && hash_equals($user['remember_token'], $cookie_token) && $user['is_verified']) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['last_activity'] = time();
        } else {
            // Invalid token or user, clear cookie securely
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
    }
}

// Prevent browser caching to protect sensitive data on 'Back', 'Forward', and 'Refresh' navigation
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0");
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
}
?>
