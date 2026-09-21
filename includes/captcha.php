<?php
// includes/captcha.php
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);

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

// Generate a random math question for captcha
$num1 = rand(1, 9);
$num2 = rand(1, 9);
$operator = rand(0, 1) ? '+' : '-';

if ($operator == '+') {
    $answer = $num1 + $num2;
} else {
    // Ensure positive result
    if ($num1 < $num2) {
        $temp = $num1;
        $num1 = $num2;
        $num2 = $temp;
    }
    $answer = $num1 - $num2;
}

$_SESSION['captcha_answer'] = $answer;
$captcha_text = "$num1 $operator $num2 = ?";

// Output SVG image (Does not require GD library)
if (ob_get_length()) ob_clean();

header('Content-Type: image/svg+xml');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
echo '<?xml version="1.0" encoding="utf-8"?>';
?>
<svg width="120" height="40" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="white" />
    <?php
    // Add some lines for obfuscation
    for($i = 0; $i < 5; $i++) {
        $x1 = rand(0, 120);
        $y1 = rand(0, 40);
        $x2 = rand(0, 120);
        $y2 = rand(0, 40);
        echo "<line x1=\"$x1\" y1=\"$y1\" x2=\"$x2\" y2=\"$y2\" stroke=\"gray\" stroke-width=\"1\" />\n";
    }
    ?>
    <text x="20" y="25" font-family="monospace" font-size="16" fill="black"><?php echo htmlspecialchars($captcha_text); ?></text>
</svg>
