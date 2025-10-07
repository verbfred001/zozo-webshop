<?php
// create_reset_token.php
// POST { email }
require __DIR__ . '/zozo-includes/DB_connectie.php';
require __DIR__ . '/zozo-includes/zozo-vertalingen.php';
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

// find klant
$sel = $mysqli->prepare('SELECT klant_id FROM klanten WHERE email = ? LIMIT 1');
$sel->bind_param('s', $email);
$sel->execute();
$res = $sel->get_result();
$row = $res->fetch_assoc();
$klant_id = $row['klant_id'] ?? null;
if (!$klant_id) {
    // For privacy, do not reveal that the email doesn't exist. Still return ok.
    echo json_encode(['ok' => true, 'sent' => false]);
    exit;
}

// generate secure random token (longer than code)
$token = bin2hex(random_bytes(24));
$hash = hash('sha256', $token);
$expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
$ins = $mysqli->prepare('INSERT INTO login_tokens (klant_id, token_hash, expires_at, ip, user_agent) VALUES (?, ?, ?, ?, ?)');
$ins->bind_param('issss', $klant_id, $hash, $expires, $ip, $ua);
if (!$ins->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save token']);
    exit;
}

$requestLang = trim(strtolower($data['lang'] ?? ($data['language'] ?? 'nl')));
$supported = ['nl', 'fr', 'en'];
if (!in_array($requestLang, $supported)) $requestLang = 'nl';

$resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/reset_password.php?token=' . $token . '&l=' . urlencode($requestLang);

$subject = $translations['Wachtwoord resetten'][$requestLang] ?? 'Wachtwoord resetten';
$plain = ($translations['Reset email intro'][$requestLang] ?? 'Je kunt je wachtwoord opnieuw instellen via de volgende link:') . " " . $resetUrl . "\n" . ($translations['Reset email expiry'][$requestLang] ?? 'De link is 1 uur geldig.');
$html = '<p>' . htmlspecialchars($translations['Reset email intro'][$requestLang] ?? 'Je kunt je wachtwoord opnieuw instellen via de volgende link:') . '</p>'
    . '<p><a href="' . htmlspecialchars($resetUrl) . '">' . htmlspecialchars($translations['Reset email link text'][$requestLang] ?? 'Wachtwoord opnieuw instellen') . '</a></p>'
    . '<p>' . htmlspecialchars($translations['Reset email expiry'][$requestLang] ?? 'De link is 1 uur geldig.') . '</p>';

$sent = false;
$graph_error = null;
$graph_attempted = false;
try {
    require_once __DIR__ . '/zozo-includes/mail_graph.php';
    $graph_attempted = true;
    $sent = send_mail_graph($email, $subject, $html, null, null);
    if ($sent === false) {
        // send_mail_graph returned false without exception; record a generic message
        $graph_error = 'send_mail_graph returned false';
    }
} catch (Throwable $e) {
    error_log('Graph mailer failed for reset: ' . $e->getMessage());
    $graph_error = substr($e->getMessage(), 0, 1000); // truncate to avoid huge responses
    $sent = false;
}

// NOTE: per request we do NOT fallback to PHP mail(). Return detailed debug info in JSON
echo json_encode([
    'ok' => true,
    'sent' => (bool)$sent,
    'graph_attempted' => (bool)$graph_attempted,
    'graph_error' => $graph_error
]);
exit;
