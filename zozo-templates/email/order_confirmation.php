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
        // Levering/afhaalmelding voor in de mail (NL dagnaam, juiste tijdzone, altijd correct)
        $levermelding = '';
        if (!empty($order_row['verzendmethode']) && !empty($order_row['UNIX_bezorgmoment'])) {
            $dagen = [
                'Sunday' => 'zondag',
                'Monday' => 'maandag',
                'Tuesday' => 'dinsdag',
                'Wednesday' => 'woensdag',
                'Thursday' => 'donderdag',
                'Friday' => 'vrijdag',
                'Saturday' => 'zaterdag'
            ];
            $dt = new DateTime('@' . $order_row['UNIX_bezorgmoment']);
            $dt->setTimezone(new DateTimeZone('Europe/Brussels'));
            $enDay = $dt->format('l');
            $nlDay = $dagen[$enDay] ?? $enDay;
            $datum = $nlDay . $dt->format(' d-m-Y');
            $tijd = $dt->format('H:i');
            if (stripos($order_row['verzendmethode'], 'af') !== false) {
                $levermelding = 'Je bestelling kan afgehaald worden op ' . $datum . ' vanaf ' . $tijd;
            } else {
                $levermelding = 'Je bestelling wordt geleverd op ' . $datum . ' vanaf ' . $tijd;
            }
        } else {
            $levermelding = 'Je bestelling wordt binnenkort klaargemaakt.';
        }

        // Betaalbevestiging tonen indien online betaald
        $betaalbevestiging = '';
        $reeds_betaald = $order_row['reeds_betaald'] ?? '';
        if (!$reeds_betaald && isset($order_row['VOLDAAN'])) {
            // fallback: cash
            $reeds_betaald = ($order_row['VOLDAAN'] === 'ja') ? 'ja-cash' : 'nee-cash';
        }
        if (stripos($reeds_betaald, 'ja-online') === 0) {
            $betaalbevestiging = 'Deze bestelling is reeds online betaald';
        }
        ?>
        <p><?= htmlspecialchars($levermelding) ?></p>
        <?php if ($betaalbevestiging): ?>
            <p style="font-weight:bold; color:green; margin-top:0; margin-bottom:18px;">&#10003; <?= htmlspecialchars($betaalbevestiging) ?></p>
        <?php endif; ?>

        <p>Met vriendelijke groet,<br>
            <?php if (!empty($bedrijf_naam)): ?>
                <?= htmlspecialchars($bedrijf_naam) ?><br>
            <?php endif; ?>
            <?php if (!empty($bedrijf_adres)): ?>
                <?= nl2br(htmlspecialchars($bedrijf_adres)) ?><br>
            <?php endif; ?>
            <?php if (!empty($bedrijf_tel)): ?>
                Tel: <?= htmlspecialchars($bedrijf_tel) ?><br>
            <?php endif; ?>
            <?php if (!empty($bedrijf_email)): ?>
                Email: <a href="mailto:<?= htmlspecialchars($bedrijf_email) ?>"><?= htmlspecialchars($bedrijf_email) ?></a>
            <?php endif; ?>
        </p>

        <?php // thumbnails are shown inline in the items table above 
        ?>
    </div>
</body>

</html>