<?php
// zozo-bedankt.php
// Simple thank-you page showing order id when provided via ?order= or order in the path rewrite
include_once __DIR__ . '/..//zozo-includes/DB_connectie.php';

$order = (int)($_GET['order'] ?? 0);
// Zorg dat categorieën en render_menu beschikbaar zijn voor de navbar
@include_once __DIR__ . '/..//zozo-includes/zozo-categories.php';
// ensure session available to prevent duplicate sends on refresh
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
// capture last graph error for display (if any)
$last_graph_error = $_SESSION['last_graph_error'] ?? null;
?>
<!doctype html>
<html lang="nl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Bedankt voor je bestelling</title>
    <link rel="stylesheet" href="/zozo-assets/css/zozo-main.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-navbar.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-topbar.css">
</head>

<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-topbar.php"; ?>
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-navbar.php"; ?>
    <script>
        // Op de bedanktpagina willen we altijd duidelijk tonen dat de winkelwagen leeg is.
        document.addEventListener('DOMContentLoaded', function() {
            var badge = document.getElementById('cart-badge');
            if (badge) badge.textContent = '0';
        });
    </script>
    <?php
    // Try to fetch order details for personalization
    $order_row = null;
    if ($order) {
        // also fetch inhoud_bestelling (JSON) and bestelling_tebetalen so we can render the email here
        $q = $mysqli->prepare('SELECT b.bestelling_id, b.levernaam, b.leverplaats, b.leverstraat, b.UNIX_bezorgmoment, b.verzendmethode, b.inhoud_bestelling, b.bestelling_tebetalen, k.email FROM bestellingen b LEFT JOIN klanten k ON b.klant_id = k.klant_id WHERE b.bestelling_id = ? LIMIT 1');
        if ($q) {
            $q->bind_param('i', $order);
            $q->execute();
            $res = $q->get_result();
            $order_row = $res ? $res->fetch_assoc() : null;
        }
    }

    // determine shop inbox (MS_FROM_EMAIL) from local config or DB
    $ms_from_email = null;
    $cfgFile2 = __DIR__ . '/../zozo-includes/mail_config.php';
    if (file_exists($cfgFile2)) {
        include $cfgFile2; // may define $MS_FROM_EMAIL
        $ms_from_email = isset($MS_FROM_EMAIL) ? $MS_FROM_EMAIL : null;
    }
    if (empty($ms_from_email)) {
        $kq2 = $mysqli->query("SELECT waarde FROM instellingen WHERE naam = 'ms_from_email' LIMIT 1");
        $krow2 = $kq2 ? $kq2->fetch_assoc() : null;
        $ms_from_email = $krow2['waarde'] ?? null;
    }

    // Email sending removed from this page per developer request.
    // Instead of attempting to send mails here we show a simple placeholder message in the UI.
    // Ensure $mail_status_message is a string so later htmlspecialchars() calls don't error.
    $mail_status_message = '';
    $mail_placeholder = '';
    if ($order && $order_row) {
        $mail_placeholder = 'mail functionality here';
    }
    ?>

    <main style="max-width:800px;margin:24px auto;padding:16px;text-align:center;">
        <h1 style="margin-bottom:8px;">Bedankt voor je bestelling</h1>

        <div id="thank-card" style="max-width:640px;margin:0 auto;padding:18px;border:1px solid #eee;border-radius:8px;background:#fff;">
            <div id="spinner" style="margin:14px auto 18px;display:flex;align-items:center;justify-content:center;">
                <div style="width:72px;height:72px;border-radius:50%;border:8px solid #f3f4f6;border-top-color:#0b3d91;animation:spin 1s linear infinite;"></div>
            </div>
            <!-- two empty lines above the staged messages -->
            <div style="height:14px;"></div>
            <div style="height:14px;"></div>
            <div id="messages" style="font-size:1rem;color:#111;">
                <div id="msg-order" class="staged" style="opacity:0;transform:translateY(6px);transition:all .35s ease;"><strong>Bestelling geplaatst</strong></div>
                <div id="msg-mail" class="staged" style="opacity:0;transform:translateY(6px);transition:all .35s ease;margin-top:8px;"></div>
                <div id="msg-pickup" class="staged" style="opacity:0;transform:translateY(6px);transition:all .35s ease;margin-top:8px;"></div>
            </div>

            <!-- two empty lines after the pickup message -->
            <div style="height:14px;"></div>
            <div style="height:14px;"></div>
            <div style="margin-top:18px;">
                <a id="back-link" href="/" style="font-weight:700;color:#0b3d91;text-decoration:none;">&lt; Terug naar de winkel</a>
            </div>
            <?php
            // Only show the server-side mail status if it indicates a failure; hide positive test/success messages
            $show_mail_status = false;
            if (!empty($mail_status_message)) {
                $lower = mb_strtolower($mail_status_message);
                if (strpos($lower, 'mislukt') !== false || strpos($lower, 'faalde') !== false || strpos($lower, 'niet') !== false) {
                    $show_mail_status = true;
                }
            }
            if ($show_mail_status): ?>
                <div style="margin-top:12px;padding:12px;border-radius:8px;background:#fff6f6;color:#7a1a1a;border:1px solid #f2c2c2;max-width:640px;margin-left:auto;margin-right:auto;">
                    <?= htmlspecialchars($mail_status_message) ?>
                    <?php if (!empty($last_graph_error)): ?>
                        <div style="margin-top:10px;padding:10px;border-radius:6px;background:#fff;color:#000;border:1px solid #eee;font-family:monospace;font-size:0.9rem;">
                            <?= htmlspecialchars($last_graph_error) ?>
                        </div>
                    <?php endif; ?>
                    <?php
                    // If Graph saved a short error message in session, show it for debugging (non-secret, truncated)
                    if (session_status() !== PHP_SESSION_ACTIVE) {
                        @session_start();
                    }
                    if (!empty($_SESSION['last_graph_error'])): ?>
                        <div style="margin-top:10px;padding:8px;border-radius:6px;background:#fff; color:#000;border:1px solid #eee; font-family:monospace; font-size:0.9rem;">
                            <?= htmlspecialchars($_SESSION['last_graph_error']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <!-- server-side diagnostics removed for production -->
        </div>
    </main>

    <style>
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* staged messages layout: left-aligned, spaced and with a checkbox icon */
        #messages .staged {
            display: block;
            text-align: left;
            padding-left: 28px;
            margin-bottom: 14px;
            color: #111;
        }

        #messages .staged .check {
            display: inline-block;
            width: 20px;
            margin-left: -28px;
            margin-right: 8px;
            color: #0b3d91;
            font-weight: 700;
        }

        .staged.show {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
    </style>

    <script>
        (function() {
            var orderId = <?= json_encode($order) ?>;
            var orderRow = <?= json_encode($order_row ?: null) ?>;

            // ensure cart badge 0
            document.addEventListener('DOMContentLoaded', function() {
                var badge = document.getElementById('cart-badge');
                if (badge) badge.textContent = '0';
            });

            // After 5s of spinner, reveal the staged messages with 1s interval
            function showMessage(el, text) {
                if (!el) return;
                if (text) el.innerHTML = text;
                el.classList.add('show');
            }

            var spinner = document.getElementById('spinner');
            var msgOrder = document.getElementById('msg-order');
            var msgMail = document.getElementById('msg-mail');
            var msgPickup = document.getElementById('msg-pickup');

            // construct dynamic texts
            var custEmail = orderRow && orderRow.email ? orderRow.email : '';
            var fromInfo = '';
            // Use server-side MS_FROM_EMAIL if available (injected via PHP below)
            var msFrom = <?= json_encode($ms_from_email ?: null) ?>;
            if (msFrom) fromInfo = msFrom;

            // Email functionality has been removed from this page. Show a neutral placeholder text.
            var mailPlaceholder = <?= json_encode($mail_placeholder) ?>;
            var mailText = mailPlaceholder ? ('<strong>' + (custEmail || 'je e‑mail') + ':</strong> ' + mailPlaceholder) : ('Er werd geprobeerd een bevestigingsmail te versturen naar <strong>' + (custEmail || 'je e‑mail') + '</strong>');

            var pickupText = '';
            if (orderRow) {
                if (orderRow.verzendmethode && orderRow.verzendmethode.indexOf('af') !== -1) {
                    // Afhaling
                    var d = orderRow.UNIX_bezorgmoment ? new Date(orderRow.UNIX_bezorgmoment * 1000) : null;
                    var when = '';
                    if (d) {
                        var datePart = d.toLocaleDateString('nl-NL', {
                            weekday: 'long',
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric'
                        });
                        var timePart = d.toLocaleTimeString('nl-NL', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        when = datePart + ' vanaf ' + timePart;
                    }
                    if (when) {
                        pickupText = 'Je bestelling kan afgehaald worden op ' + when;
                    } else {
                        pickupText = 'Je bestelling wordt binnenkort klaargemaakt.';
                    }
                } else {
                    var d2 = orderRow.UNIX_bezorgmoment ? new Date(orderRow.UNIX_bezorgmoment * 1000) : null;
                    var when2 = d2 ? d2.toLocaleString('nl-NL', {
                        weekday: 'long',
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : '';
                    pickupText = 'Je bestelling wordt geleverd op ' + when2;
                }
            } else {
                pickupText = 'Je bestelling wordt binnenkort klaargemaakt.';
            }

            setTimeout(function() {
                // stop spinner
                if (spinner) spinner.style.display = 'none';
                // show first message
                showMessage(msgOrder, '<span class="check">✔</span><strong>Bestelling werd succesvol doorgestuurd</strong>');
                setTimeout(function() {
                    showMessage(msgMail, '<span class="check">✔</span>' + mailText);
                }, 1000);
                setTimeout(function() {
                    showMessage(msgPickup, '<span class="check">✔</span>' + pickupText);
                }, 2000);
            }, 5000);
            // Server-side checks: the PHP below will inject console logs that report
            // - whether Graph/Kiota classes could be instantiated
            // - whether a token context could be created (basic connectivity check)
            // - last relevant PHP error_log lines matching mail/Graph keywords
            // This avoids adding extra files and surfaces diagnostics directly in the browser console.

            <?php
            // server-side diagnostics removed for production
            ?>
            <?php if (!empty($last_graph_error)): ?>
                console.warn('GRAPH_SESSION_ERROR: ', <?= json_encode($last_graph_error, JSON_UNESCAPED_UNICODE) ?>);
            <?php endif; ?>
        })();
    </script>
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-footer.php"; ?>
</body>

</html>