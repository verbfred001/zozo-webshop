<?php
// Order confirmation email template
// Expects (optionally): $order_id, $order_row (with keys levernaam, email, inhoud_bestelling (JSON array or newline list)), $baseUrl
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
        <div style="text-align:center;margin-bottom:12px;">
            <?php if (!empty($baseUrl)): ?>
                <img src="<?= htmlspecialchars($baseUrl . '/mail/header.webp') ?>" alt="logo" style="max-width:220px;border:0;">
            <?php else: ?>
                <h2>Bevestiging bestelling</h2>
            <?php endif; ?>
        </div>

        <h3 style="margin-top:0;">Bevestiging bestelling #<?= htmlspecialchars($order_id ?? '') ?></h3>
        <p>Beste <?= htmlspecialchars($order_row['levernaam'] ?? 'klant') ?>,</p>
        <p>Dank je — we hebben je bestelling goed ontvangen. Hieronder vind je een overzicht van je bestelling.</p>

        <?php
        // Parse inhoud_bestelling: can be JSON array of items or newline/CSV text.
        $items = [];
        if (!empty($order_row['inhoud_bestelling'])) {
            $raw = trim($order_row['inhoud_bestelling']);
            // Try JSON first
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                // fallback: each line is tab/pipe/comma separated: title\tqty\tunit\tprice
                $lines = preg_split('/[\r\n]+/', $raw);
                foreach ($lines as $ln) {
                    $ln = trim($ln);
                    if ($ln === '') continue;
                    // try pipe, tab or comma
                    $parts = preg_split('/\|\t|\t|\|\||\|,|,|\|/', $ln);
                    // normalise to at least [title, qty, unit, price]
                    $title = $parts[0] ?? $ln;
                    $qty = $parts[1] ?? '1';
                    $unit = $parts[2] ?? '';
                    $price = $parts[3] ?? '';
                    $items[] = ['title' => $title, 'qty' => $qty, 'unit' => $unit, 'price' => $price];
                }
            }
        }
        ?>

        <?php if (!empty($items)): ?>
            <table style="width:100%;border-collapse:collapse;margin-top:10px;">
                <thead>
                    <tr>
                        <th style="width:64px;text-align:left;border-bottom:1px solid #eee;padding:6px;"></th>
                        <th style="text-align:left;border-bottom:1px solid #eee;padding:6px;">Artikel</th>
                        <th style="text-align:center;border-bottom:1px solid #eee;padding:6px;width:80px;">Aantal</th>
                        <th style="text-align:right;border-bottom:1px solid #eee;padding:6px;">Prijs (incl.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it):
                        // normalize item fields from the stored format (checkout saves 'omschrijving','aantal','kostprijs','BTWtarief','afbeelding')
                        $title = '';
                        $qty = 1;
                        $kostprijs = null; // excl. BTW
                        $btw = null;
                        $img = '';
                        if (is_array($it)) {
                            $title = $it['omschrijving'] ?? $it['title'] ?? $it['name'] ?? '';
                            $qty = isset($it['aantal']) ? $it['aantal'] : ($it['qty'] ?? 1);
                            $kostprijs = isset($it['kostprijs']) ? $it['kostprijs'] : (isset($it['price']) ? $it['price'] : null);
                            $btw = isset($it['BTWtarief']) ? $it['BTWtarief'] : (isset($it['btw']) ? $it['btw'] : null);
                            $img = $it['afbeelding'] ?? ($it['art'] ?? ($it['image'] ?? ''));
                        } else {
                            // fallback when item is a string
                            $title = (string)$it;
                        }
                        // build thumbnail URL
                        $thumb = '';
                        if (!empty($img)) {
                            // if image looks like a filename, assume it's in /upload/
                            if (!preg_match('#^https?://#', $img)) {
                                $thumb = rtrim($baseUrl ?? '', '/') . '/upload/' . ltrim($img, '/');
                            } else {
                                $thumb = $img;
                            }
                        }

                        // compute price incl. BTW when kostprijs + BTWtarief provided
                        $priceFormatted = '';
                        if ($kostprijs !== null && $kostprijs !== '') {
                            $kp = floatval($kostprijs);
                            $btwPercent = is_null($btw) || $btw === '' ? 0.0 : floatval($btw);
                            $priceIncl = $kp * (1 + ($btwPercent / 100.0));
                            $priceFormatted = '€' . number_format($priceIncl, 2, ',', '.');
                        } else {
                            $priceFormatted = '';
                        }
                    ?>
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid #f6f6f6;vertical-align:middle;">
                                <?php if ($thumb): ?>
                                    <img src="<?= htmlspecialchars($thumb) ?>" alt="" style="width:56px;height:56px;object-fit:cover;border:1px solid #eee;display:block;">
                                <?php else: ?>
                                    <div style="width:56px;height:56px;background:#f4f4f4;border:1px solid #eee;display:inline-block;"></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:8px;border-bottom:1px solid #f6f6f6;vertical-align:middle;"><?= htmlspecialchars($title) ?></td>
                            <td style="text-align:center;padding:8px;border-bottom:1px solid #f6f6f6;vertical-align:middle;"><?= htmlspecialchars($qty) ?></td>
                            <td style="text-align:right;padding:8px;border-bottom:1px solid #f6f6f6;vertical-align:middle;"><?= $priceFormatted ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Er werden geen artikels gevonden in de bestelling.</p>
        <?php endif; ?>

        <?php if (!empty($order_row['bestelling_tebetalen'])):
            // sanitize and format total to 2 decimals (Dutch style)
            $totalRaw = $order_row['bestelling_tebetalen'];
            $totalFloat = floatval(preg_replace('/[^0-9\.,\-]/', '', (string)$totalRaw));
            $totalFormatted = '€' . number_format($totalFloat, 2, ',', '.');
        ?>
            <p style="text-align:right;font-weight:700;margin-top:10px;">Totaal: <?= htmlspecialchars($totalFormatted) ?></p>
        <?php endif; ?>

        <?php
        // Minimal change: use configurable from-email and show shop contact from instellingen
        $contactEmail = $MS_FROM_EMAIL ?? ($ms_from_email ?? null);
        // attempt to read instellingen email if not available
        if (empty($contactEmail) && isset($mysqli) && $mysqli instanceof mysqli) {
            $kq = $mysqli->query("SELECT waarde FROM instellingen WHERE naam = 'email' LIMIT 1");
            $krow = $kq ? $kq->fetch_assoc() : null;
            $contactEmail = $krow['waarde'] ?? $contactEmail;
        }
        if (empty($contactEmail)) $contactEmail = 'info@zozo.be';
        ?>

        <p>Als je vragen hebt, reageer op deze e-mail of stuur een bericht naar <a href="mailto:<?= htmlspecialchars($contactEmail) ?>"><?= htmlspecialchars($contactEmail) ?></a>.</p>

        <?php
        // show bedrijfsgegevens from instellingen if available
        $shop_info = [];
        if (isset($mysqli) && $mysqli instanceof mysqli) {
            $sres = $mysqli->query("SELECT naam, waarde FROM instellingen WHERE naam IN ('bedrijfsnaam','adres','telefoon','email')");
            if ($sres) {
                while ($sr = $sres->fetch_assoc()) {
                    $shop_info[$sr['naam']] = $sr['waarde'];
                }
            }
        }
        if (!empty($shop_info)):
        ?>
            <div style="margin-top:12px;font-size:0.95rem;color:#333;border-top:1px solid #eee;padding-top:8px;">
                <?php if (!empty($shop_info['bedrijfsnaam'])): ?><strong><?= htmlspecialchars($shop_info['bedrijfsnaam']) ?></strong><br><?php endif; ?>
                <?php if (!empty($shop_info['adres'])): ?><?= nl2br(htmlspecialchars($shop_info['adres'])) ?><br><?php endif; ?>
                <?php if (!empty($shop_info['telefoon'])): ?>Tel: <?= htmlspecialchars($shop_info['telefoon']) ?><br><?php endif; ?>
                <?php if (!empty($shop_info['email'])): ?><a href="mailto:<?= htmlspecialchars($shop_info['email']) ?>"><?= htmlspecialchars($shop_info['email']) ?></a><?php endif; ?>
            </div>
        <?php else: ?>
            <p>Met vriendelijke groet,<br>Het team</p>
        <?php endif; ?>

        <?php // thumbnails are shown inline in the items table above 
        ?>
    </div>
</body>

</html>