<?php
// register.php
// POST { email, password, voornaam?, achternaam?, straat?, postcode?, plaats?, telefoon? }
require __DIR__ . '/zozo-includes/DB_connectie.php';
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
// Early trace for debugging: record that the script was reached and basic request info
// (do not log the raw password). This helps determine network vs server-side failure.
try {
    $remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? 'register.php';
    $raw = file_get_contents('php://input');
    $len = is_string($raw) ? strlen($raw) : 0;
    _reg_log("ENTRY: {$remote} {$method} {$uri} input_len={$len}");
} catch (Throwable $e) {
    @error_log('register.php early log failed: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid input: provide a valid email and password (>=6 chars)']);
    exit;
}

$voor = trim($data['voornaam'] ?? '');
$ach = trim($data['achternaam'] ?? '');
$straat = trim($data['straat'] ?? '');
$postcode = trim($data['postcode'] ?? '');
$plaats = trim($data['plaats'] ?? '');
$telefoon = trim($data['telefoon'] ?? '');
$huisnummer = trim($data['huisnummer'] ?? '');

// helper to append debug info to a local log file (temporary)
function _reg_log($msg)
{
    $path = __DIR__ . DIRECTORY_SEPARATOR . 'register-debug.log';
    $ts = date('Y-m-d H:i:s');
    $entry = "[{$ts}] " . $msg . "\n";
    @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
    // Also write to PHP error log so system logs capture it when file writes fail
    @error_log($entry);
}

// check existing klant
$sel = $mysqli->prepare('SELECT klant_id, password_hash FROM klanten WHERE email = ? LIMIT 1');
$sel->bind_param('s', $email);
$sel->execute();
$row = $sel->get_result()->fetch_assoc();

if ($mysqli->error) {
    _reg_log("SELECT prepare/execute error: " . $mysqli->error);
}

if ($row) {
    // exists
    $klant_id = $row['klant_id'];
    if (!empty($row['password_hash'])) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Account already exists']);
        exit;
    }
    // fetch existing klant fields so we don't overwrite with empty inputs
    $q = $mysqli->prepare('SELECT voornaam, achternaam, straat, huisnummer, postcode, plaats, telefoon, bedrijfsnaam, btw_nummer FROM klanten WHERE klant_id = ? LIMIT 1');
    $q->bind_param('i', $klant_id);
    $q->execute();
    $existing = $q->get_result()->fetch_assoc() ?: [];

    // preserve existing values when the incoming value is empty
    $voor = $voor !== '' ? $voor : ($existing['voornaam'] ?? '');
    $ach = $ach !== '' ? $ach : ($existing['achternaam'] ?? '');
    $straat = $straat !== '' ? $straat : ($existing['straat'] ?? '');
    $huisnummer = $huisnummer !== '' ? $huisnummer : ($existing['huisnummer'] ?? '');
    $postcode = $postcode !== '' ? $postcode : ($existing['postcode'] ?? '');
    $plaats = $plaats !== '' ? $plaats : ($existing['plaats'] ?? '');
    $telefoon = $telefoon !== '' ? $telefoon : ($existing['telefoon'] ?? '');

    // update existing klant with password (preserving fields)
    $passHash = password_hash($password, PASSWORD_DEFAULT);
    $up = $mysqli->prepare('UPDATE klanten SET password_hash = ?, voornaam = ?, achternaam = ?, straat = ?, huisnummer = ?, postcode = ?, plaats = ?, telefoon = ? WHERE klant_id = ?');
    $up->bind_param('ssssssssi', $passHash, $voor, $ach, $straat, $huisnummer, $postcode, $plaats, $telefoon, $klant_id);
    if (!$up->execute()) {
        $err = $up->error ?: $mysqli->error;
        _reg_log("UPDATE klant failed: " . $err . " | params: " . json_encode([$klant_id, $email]));
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to update klant']);
        exit;
    }
    session_regenerate_id(true);
    $_SESSION['klant_id'] = $klant_id;

    // return klant data for client to prefill form
    $klant = [
        'klant_id' => $klant_id,
        'voornaam' => $voor,
        'achternaam' => $ach,
        'email' => $email,
        'telefoon' => $telefoon,
        'straat' => $straat,
        'huisnummer' => $huisnummer,
        'postcode' => $postcode,
        'plaats' => $plaats
    ];
    echo json_encode(['ok' => true, 'created' => false, 'klant' => $klant]);
    exit;
}

// create new klant
$ins = $mysqli->prepare('INSERT INTO klanten (voornaam, achternaam, email, telefoon, straat, huisnummer, postcode, plaats, land, aangemaakt_op, actief, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "België", NOW(), 1, ?)');
$passHash = password_hash($password, PASSWORD_DEFAULT);
$ins->bind_param('sssssssss', $voor, $ach, $email, $telefoon, $straat, $huisnummer, $postcode, $plaats, $passHash);
$ok = $ins->execute();
if (!$ok) {
    $err = $ins->error ?: $mysqli->error;
    _reg_log("INSERT klant failed: " . $err . " | params: " . json_encode([$email, $voor, $ach, $straat, $postcode, $plaats]));
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to create klant']);
    exit;
}
$klant_id = $mysqli->insert_id;
session_regenerate_id(true);
$_SESSION['klant_id'] = $klant_id;
// return klant data for client to prefill form
$klant = [
    'klant_id' => $klant_id,
    'voornaam' => $voor,
    'achternaam' => $ach,
    'email' => $email,
    'telefoon' => $telefoon,
    'straat' => $straat,
    'huisnummer' => $huisnummer,
    'postcode' => $postcode,
    'plaats' => $plaats
];
echo json_encode(['ok' => true, 'created' => true, 'klant' => $klant]);
exit;
