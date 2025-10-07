<?php
// Simple page to request a password reset
include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/zozo-vertalingen.php";
include_once $_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/lang.php';

// Determine language (fallback to nl)
if (!isset($lang)) {
    if (preg_match('#^/(nl|fr|en)(/|$)#', $_SERVER['REQUEST_URI'], $m)) {
        $lang = $m[1];
    } else {
        $lang = 'nl';
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($translations['Wachtwoord vergeten'][$lang] ?? 'Wachtwoord vergeten') ?></title>
    <link rel="stylesheet" href="/zozo-assets/css/zozo-main.css">
</head>

<body>
    <main style="max-width:640px;margin:24px auto;padding:18px">
        <h1><?= htmlspecialchars($translations['Wachtwoord vergeten'][$lang] ?? 'Wachtwoord vergeten') ?></h1>
        <p><?= htmlspecialchars($translations['Vul je e-mail in om door te gaan'][$lang] ?? 'Vul je e‑mail in. Je ontvangt een e‑mail met instructies om je wachtwoord opnieuw in te stellen.') ?></p>
        <form id="forgot-form">
            <label>Email <input id="forgot-email" type="email" required class="form-control"></label>
            <div style="margin-top:12px">
                <button id="forgot-submit" class="btn-primary">Verstuur reset-link</button>
            </div>
        </form>
        <div id="forgot-status" style="margin-top:12px"></div>

        <script>
            document.getElementById('forgot-form').addEventListener('submit', async function(ev) {
                ev.preventDefault();
                const email = document.getElementById('forgot-email').value.trim();
                if (!email) return;
                const status = document.getElementById('forgot-status');
                status.innerText = <?= json_encode($translations['Reset_sending'][$lang] ?? 'Versturen...') ?>;
                try {
                    const res = await fetch('/create_reset_token.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            email,
                            lang: document.documentElement.lang || 'nl'
                        })
                    });
                    const j = await res.json();
                    console.log('create_reset_token response:', j);
                    if (j.ok) {
                        if (j.sent) {
                            status.innerText = <?= json_encode($translations['Reset_sent'][$lang] ?? 'Als het e‑mailadres bij ons bekend is, ontvang je een resetlink.') ?>;
                        } else {
                            status.innerText = <?= json_encode($translations['Reset_not_sent'][$lang] ?? 'Resetlink niet verzonden via Graph. Controleer console voor details.') ?>;
                        }
                    } else {
                        status.innerText = <?= json_encode($translations['Error_prefix'][$lang] ?? 'Fout: ') ?> + (j.error || 'Onbekend');
                    }
                } catch (e) {
                    status.innerText = <?= json_encode($translations['Netwerkfout'][$lang] ?? 'Netwerkfout') ?>;
                }
            });
        </script>
    </main>
</body>

</html>