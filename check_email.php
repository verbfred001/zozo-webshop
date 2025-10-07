<?php
// check_email.php
// POST { email }
require __DIR__ . '/zozo-includes/DB_connectie.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email = trim($data['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid email']);
    exit;
}

// return whether klant exists and whether a password_hash is set
$sel = $mysqli->prepare('SELECT klant_id, password_hash FROM klanten WHERE email = ? LIMIT 1');
$sel->bind_param('s', $email);
$sel->execute();
$row = $sel->get_result()->fetch_assoc();

$exists = (bool)($row['klant_id'] ?? false);
$has_password = $exists && !empty($row['password_hash']);

echo json_encode(['ok' => true, 'exists' => $exists, 'has_password' => $has_password]);
exit;
