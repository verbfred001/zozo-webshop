<?php
// verify_login_code.php
// POST { email, code }
require __DIR__ . '/zozo-includes/DB_connectie.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email = trim($data['email'] ?? '');
$code = trim($data['code'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{4}$/', $code)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

// lookup klant
$sel = $mysqli->prepare('SELECT klant_id FROM klanten WHERE email = ? LIMIT 1');
$sel->bind_param('s', $email);
$sel->execute();
$res = $sel->get_result();
$row = $res->fetch_assoc();
$klant_id = $row['klant_id'] ?? null;
if (!$klant_id) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Klant not found']);
    exit;
}

$hash = hash('sha256', $code);
// find latest matching unused token for this klant
$q = $mysqli->prepare('SELECT token_id, expires_at, used_at FROM login_tokens WHERE klant_id = ? AND token_hash = ? ORDER BY created_at DESC LIMIT 1');
$q->bind_param('is', $klant_id, $hash);
$q->execute();
$r = $q->get_result()->fetch_assoc();

if (!$r) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Code niet gevonden of incorrect']);
    exit;
}

if ($r['used_at'] !== null) {
    echo json_encode(['ok' => false, 'error' => 'Code al gebruikt']);
    exit;
}

if (strtotime($r['expires_at']) < time()) {
    echo json_encode(['ok' => false, 'error' => 'Code verlopen']);
    exit;
}

// mark used and create session
$upd = $mysqli->prepare('UPDATE login_tokens SET used_at = NOW() WHERE token_id = ?');
$upd->bind_param('i', $r['token_id']);
$upd->execute();

session_regenerate_id(true);
$_SESSION['klant_id'] = $klant_id;
// fetch klant data
$qk = $mysqli->prepare('SELECT voornaam, achternaam, email, telefoon, straat, huisnummer, postcode, plaats, bedrijfsnaam, btw_nummer FROM klanten WHERE klant_id = ? LIMIT 1');
$qk->bind_param('i', $klant_id);
$qk->execute();
$kdata = $qk->get_result()->fetch_assoc() ?: [];

// return success and klant data
$return = $data['return'] ?? '/mijn-account.php';
echo json_encode(['ok' => true, 'return' => $return, 'klant' => $kdata]);
exit;
