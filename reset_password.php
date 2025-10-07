<?php
require __DIR__ . '/zozo-includes/DB_connectie.php';
include __DIR__ . '/zozo-includes/zozo-vertalingen.php';
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// language from query param (l) or POST param or default to nl
$lang = isset($_REQUEST['l']) ? substr($_REQUEST['l'], 0, 2) : ($lang ?? 'nl');
if (!in_array($lang, ['nl', 'fr', 'en'])) $lang = 'nl';

// POST handler: set new password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 6 || !$token) {
        $err = 'Ongeldige aanvraag of wachtwoord te kort (min 6 tekens).';
    } else {
        $hash = hash('sha256', $token);
        // login_tokens schema uses token_id as primary key and has fields: token_id, klant_id, expires_at, used_at
        $q = $mysqli->prepare('SELECT token_id, klant_id, expires_at, used_at FROM login_tokens WHERE token_hash = ? LIMIT 1');
        $q->bind_param('s', $hash);
        $q->execute();
        $r = $q->get_result();
        $row = $r ? $r->fetch_assoc() : null;
        if (!$row) {
            $err = 'Ongeldige of verlopen link.';
        } else {
            // check used_at to prevent reuse
            if (!empty($row['used_at'])) {
                $err = 'Deze link is al gebruikt.';
            }
            $expires = strtotime($row['expires_at']);
            if ($expires < time()) {
                $err = 'De link is verlopen.';
            } else {
                $klant_id = (int)$row['klant_id'];
                $passHash = password_hash($password, PASSWORD_DEFAULT);
                $up = $mysqli->prepare('UPDATE klanten SET password_hash = ? WHERE klant_id = ?');
                $up->bind_param('si', $passHash, $klant_id);
                if ($up->execute()) {
                    // mark token as used (set used_at)
                    $mark = $mysqli->prepare('UPDATE login_tokens SET used_at = NOW() WHERE token_id = ?');
                    if ($mark) {
                        $mark->bind_param('i', $row['token_id']);
                        $mark->execute();
                    }
                    session_regenerate_id(true);
                    $_SESSION['klant_id'] = $klant_id;
                    // redirect to language-specific cart page (e.g. /nl/cart, /fr/cart)
                    header('Location: /' . rawurlencode($lang) . '/cart');
                    exit;
                } else {
                    $err = 'Kon wachtwoord niet opslaan.';
                }
            }
        }
    }
}

$tokenParam = $_GET['token'] ?? ($_POST['token'] ?? '');
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($translations['Wachtwoord resetten'][$lang] ?? 'Wachtwoord resetten') ?></title>
    <link rel="stylesheet" href="/zozo-assets/css/zozo-main.css">
</head>

<body>
    <main style="max-width:640px;margin:24px auto;padding:18px">
        <h1><?= htmlspecialchars($translations['Reset email link text'][$lang] ?? 'Wachtwoord opnieuw instellen') ?></h1>
        <?php if (!empty($err)): ?>
            <div style="background:#fee2e2;color:#7f1d1d;padding:12px;border-radius:8px;margin-bottom:12px"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <?php if (!$tokenParam): ?>
            <p><?= htmlspecialchars($translations['Ongeldige link controleer'][$lang] ?? 'Ongeldige link. Controleer de e‑maillink of vraag opnieuw een reset aan via de website.') ?></p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="token" value="<?= htmlspecialchars($tokenParam) ?>">
                <input type="hidden" name="l" value="<?= htmlspecialchars($lang) ?>">
                <label><?= htmlspecialchars($translations['Nieuw wachtwoord'][$lang] ?? 'Nieuw wachtwoord') ?> <input type="password" name="password" required style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px"></label>
                <div style="margin-top:12px">
                    <button type="submit" class="btn-primary"><?= htmlspecialchars($translations['Wachtwoord instellen'][$lang] ?? 'Wachtwoord instellen') ?></button>
                </div>
            </form>
        <?php endif; ?>
    </main>
</body>

</html>