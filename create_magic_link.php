<?php
// create_magic_link.php
// POST { email }
require __DIR__ . '/zozo-includes/DB_connectie.php';
// simple rate-limit helper (very small): allow one request per 10 seconds per IP using file cache
function ip_rate_limit_ok($ttl = 10)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $f = sys_get_temp_dir() . '/magic_' . preg_replace('/[^a-z0-9]/i', '', $ip);
    if (file_exists($f) && (time() - filemtime($f)) < $ttl) return false;
    @file_put_contents($f, time());
    return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

if (!ip_rate_limit_ok(8)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many requests']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email = trim($data['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid email']);
    exit;
}

// lookup klant
$sel = $mysqli->prepare('SELECT klant_id, voornaam, achternaam FROM klanten WHERE email = ? LIMIT 1');
$sel->bind_param('s', $email);
$sel->execute();
$res = $sel->get_result();
$row = $res->fetch_assoc();
$klant_id = $row['klant_id'] ?? null;

if (!$klant_id) {
    // create minimal klant record so orders can be linked
    $ins = $mysqli->prepare('INSERT INTO klanten (voornaam, achternaam, email, straat, huisnummer, postcode, plaats, land, aangemaakt_op, actief) VALUES (?, ?, ?, "", "", "", "", "België", NOW(), 1)');
    $dummyVoor = $data['voornaam'] ?? '';
    $dummyAcht = $data['achternaam'] ?? '';
    $ins->bind_param('sss', $dummyVoor, $dummyAcht, $email);
    if (!$ins->execute()) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to create klant']);
        exit;
    }
    $klant_id = $mysqli->insert_id;
}

// generate token
try {
    $raw = bin2hex(random_bytes(32));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Token generation failed']);
    exit;
}
$hash = hash('sha256', $raw);
$expires = date('Y-m-d H:i:s', time() + (15 * 60)); // 15 minutes

$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
$ins2 = $mysqli->prepare('INSERT INTO login_tokens (klant_id, token_hash, expires_at, ip, user_agent) VALUES (?, ?, ?, ?, ?)');
$ins2->bind_param('issss', $klant_id, $hash, $expires, $ip, $ua);
if (!$ins2->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save token']);
    exit;
}

$site = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$return = '';
if (!empty($data['return'])) {
    // allow only relative paths to avoid open redirect
    $r = $data['return'];
    if (strpos($r, '/') === 0 && strpos($r, '//') !== 0) {
        $return = '&r=' . urlencode($r);
    }
}
$link = $site . '/magic-login.php?token=' . $raw . $return;

// send mail - use mail() by default; recommend replacing with PHPMailer / transactional provider in production
$subject = 'Jouw inloglink';
$htmlMessage = '<p>Klik op de link om in te loggen (15 minuten geldig):</p>' .
    '<p><a href="' . htmlspecialchars($link) . '">Open inloglink</a></p>' .
    '<p>Als je dit niet hebt aangevraagd kun je dit negeren.</p>';

// try Graph mailer first
$sent = false;
try {
    require_once __DIR__ . '/zozo-includes/mail_graph.php';
    $sent = send_mail_graph($email, $subject, $htmlMessage, null, null);
} catch (Throwable $e) {
    error_log('Graph mailer failed: ' . $e->getMessage());
    $sent = false;
}

// fallback to mail() if Graph failed
if (!$sent) {
    $plain = "Klik op de link om in te loggen (15 minuten geldig):\n\n" . $link . "\n\nAls je dit niet hebt aangevraagd kun je dit negeren.";
    $headers = "From: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'example.com') . "\r\n";
    @$sent = mail($email, $subject, $plain, $headers);
}

echo json_encode(['ok' => true, 'sent' => (bool)$sent, 'message' => 'Magic link created']);
