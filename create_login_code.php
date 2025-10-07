<?php
// create_login_code.php
// POST { email }
require __DIR__ . '/zozo-includes/DB_connectie.php';

function ip_rate_limit_ok($ttl = 8)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $f = sys_get_temp_dir() . '/magic_code_' . preg_replace('/[^a-z0-9]/i', '', $ip);
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

if (!ip_rate_limit_ok(5)) {
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

// lookup or create klant
$sel = $mysqli->prepare('SELECT klant_id FROM klanten WHERE email = ? LIMIT 1');
$sel->bind_param('s', $email);
$sel->execute();
$res = $sel->get_result();
$row = $res->fetch_assoc();
$klant_id = $row['klant_id'] ?? null;
if (!$klant_id) {
    $ins = $mysqli->prepare('INSERT INTO klanten (voornaam, achternaam, email, straat, huisnummer, postcode, plaats, land, aangemaakt_op, actief) VALUES (?, ?, ?, "", "", "", "", "België", NOW(), 1)');
    $dummyVoor = '';
    $dummyAcht = '';
    $ins->bind_param('sss', $dummyVoor, $dummyAcht, $email);
    if (!$ins->execute()) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to create klant']);
        exit;
    }
    $klant_id = $mysqli->insert_id;
}

// generate 4-digit code
$code = str_pad(strval(random_int(0, 9999)), 4, '0', STR_PAD_LEFT);
$hash = hash('sha256', $code);
$expires = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes

$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
$ins2 = $mysqli->prepare('INSERT INTO login_tokens (klant_id, token_hash, expires_at, ip, user_agent) VALUES (?, ?, ?, ?, ?)');
$ins2->bind_param('issss', $klant_id, $hash, $expires, $ip, $ua);
if (!$ins2->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save code']);
    exit;
}

$subject = 'Jouw inlogcode';
$plain = "Jouw inlogcode is: " . $code . "\nDeze code is 10 minuten geldig.\n\nAls je dit niet hebt aangevraagd, negeer deze e-mail.";
$html = '<p>Jouw inlogcode is: <strong>' . htmlspecialchars($code) . '</strong></p><p>Deze code is 10 minuten geldig.</p>';

$sent = false;
$graph_attempted = false;
$graph_error = null;
try {
    require_once __DIR__ . '/zozo-includes/mail_graph.php';
    $graph_attempted = true;
    $sent = send_mail_graph($email, $subject, $html, null, null);
    if ($sent === false) {
        $graph_error = 'send_mail_graph returned false';
    }
} catch (Throwable $e) {
    error_log('Graph mailer failed for code: ' . $e->getMessage());
    $graph_error = substr($e->getMessage(), 0, 1000);
    $sent = false;
}

// Do NOT fallback to PHP mail(); always use Graph only per request
echo json_encode(['ok' => true, 'sent' => (bool)$sent, 'graph_attempted' => (bool)$graph_attempted, 'graph_error' => $graph_error]);
