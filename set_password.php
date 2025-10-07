<?php
// set_password.php
// Usage (CLI only): php set_password.php email@example.com NewPassword123

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from CLI\n";
    exit(1);
}

if ($argc < 3) {
    echo "Usage: php set_password.php <email> <password>\n";
    exit(1);
}

$email = $argv[1];
$password = $argv[2];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email\n";
    exit(1);
}

if (strlen($password) < 6) {
    echo "Password must be at least 6 characters\n";
    exit(1);
}

require __DIR__ . '/zozo-includes/DB_connectie.php';

$hash = password_hash($password, PASSWORD_DEFAULT);

$upd = $mysqli->prepare('UPDATE klanten SET password_hash = ?, last_login = NULL WHERE email = ?');
$upd->bind_param('ss', $hash, $email);
if (!$upd->execute()) {
    echo "Failed to update password: " . $mysqli->error . "\n";
    exit(1);
}

if ($upd->affected_rows === 0) {
    echo "No klant found with that email.\n";
    exit(1);
}

echo "Password updated for $email\n";
exit(0);
