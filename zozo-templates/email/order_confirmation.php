<?php
// Minimal placeholder order confirmation template
// Keeps rendering simple to avoid template-related send failures.
// Expects (optionally): $order_id, $order_row, $baseUrl
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <?php /*<title>Bevestiging bestelling #<?= htmlspecialchars($order_id ?? '') ?></title>*/ ?>
    <title>Bevestiging bestelling</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
        }

        .container {
            max-width: 700px;
            margin: 18px auto;
            padding: 18px;
            background: #fff;
            border: 1px solid #eee;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php /*<h2>Bevestiging bestelling #<?= htmlspecialchars($order_id ?? '') ?></h2>*/ ?>
        <?php /*<p>Beste <?= htmlspecialchars($order_row['levernaam'] ?? 'klant') ?>,</p>*/ ?>
        <p><strong>dit is de inhoud template</strong></p>
        <p>Je ontvangt later een volledig overzicht van de bestelde artikelen.</p>
        <?php /*if (!empty($baseUrl)): ?>
            <p style="font-size:0.9rem;color:#666;">Logo: <img src="<?= htmlspecialchars($baseUrl . '/mail/header.webp') ?>" alt="logo" style="max-width:140px;vertical-align:middle;border:0;"></p>
        <?php endif; */ ?>
    </div>
</body>

</html>