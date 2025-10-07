<?php
// login_password.php
// POST { email, password }
require __DIR__ . '/zozo-includes/DB_connectie.php';
session_start();

// Load translations so we can return localized error messages
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/zozo-vertalingen.php')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/zozo-vertalingen.php';
}

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$requestLang = trim(strtolower($data['lang'] ?? ($data['language'] ?? 'nl')));
$supported = ['nl', 'fr', 'en'];
if (!in_array($requestLang, $supported)) $requestLang = 'nl';
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

$sel = $mysqli->prepare('SELECT klant_id, password_hash FROM klanten WHERE email = ? LIMIT 1');
$sel->bind_param('s', $email);
$sel->execute();
$row = $sel->get_result()->fetch_assoc();
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Klant niet gevonden']);
    exit;
}

$klant_id = $row['klant_id'];
$hash = $row['password_hash'];

if (empty($hash)) {
    // no password set yet — tell client to register or use magic link
    echo json_encode(['ok' => false, 'error' => 'Geen wachtwoord ingesteld', 'action' => 'register']);
    exit;
}

if (!password_verify($password, $hash)) {
    http_response_code(401);
    // use translations if available
    $errorMsg = (isset($translations['Onjuist wachtwoord'][$requestLang]) ? $translations['Onjuist wachtwoord'][$requestLang] : 'Onjuist wachtwoord');
    echo json_encode(['ok' => false, 'error' => $errorMsg]);
    exit;
}

// success
session_regenerate_id(true);
$_SESSION['klant_id'] = $klant_id;
$upd = $mysqli->prepare('UPDATE klanten SET last_login = NOW() WHERE klant_id = ?');
$upd->bind_param('i', $klant_id);
$upd->execute();
// fetch klant data
$q = $mysqli->prepare('SELECT voornaam, achternaam, email, telefoon, straat, huisnummer, postcode, plaats, bedrijfsnaam, btw_nummer FROM klanten WHERE klant_id = ? LIMIT 1');
$q->bind_param('i', $klant_id);
$q->execute();
$kdata = $q->get_result()->fetch_assoc() ?: [];

echo json_encode(['ok' => true, 'klant' => $kdata]);
exit;
