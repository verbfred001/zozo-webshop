<?php
// Checkout page + simple JSON API handler to create an order and update stock
// Uses existing DB connection from zozo-includes/DB_connectie.php

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// includes and helpers
include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/zozo-vertalingen.php";
include_once $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php"; // provides $mysqli
include_once $_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/lang.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/zozo-categories.php';

// Determine a base time used by timeslot generation/validation.
// Priority: session override (per-browser) then admin setting `instellingen.tijd_override`.
// Admin field is managed in the webshop settings in the admin UI. If neither is set we use time().
// session override takes highest priority (set when admin saves in their browser)
$override_now = null;
$override_source = null; // 'session' | 'admin' | 'get'

// 0) session override (per-browser, set by admin save)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!empty($_SESSION['tijd_override'])) {
    $t = trim((string)$_SESSION['tijd_override']);
    $dt = DateTime::createFromFormat('Y-m-d H:i', $t);
    if (!$dt) $dt = DateTime::createFromFormat('d/m/Y-H:i', $t);
    if ($dt instanceof DateTime) {
        $override_now = $dt->getTimestamp();
        $override_source = 'session';
    }
}

// NOTE: admin-persisted override (instellingen.tijd_override) is intentionally ignored here.
// The testing override is session-scoped only: it must be set via the "Tijd instellen" button
// on the admin page which stores the override in this browser's session ($_SESSION['tijd_override']).

// base time used by timeslot generation/validation
$base_time = $override_now ?? time();

// If override present, prepare a small label to show in title/H1 so testers are aware
$fake_time_label = '';
if ($override_now) {
    // Build weekday in Dutch (date('N') => 1 (Mon) .. 7 (Sun))
    $weekdayNl = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'];
    $dow = (int)date('N', $override_now);
    $weekday = $weekdayNl[max(0, $dow - 1)];
    $formatted = date('d/m/Y - H:i', $override_now);
    // Keep the explicit FAKE TIME marker and include weekday, e.g. FAKE TIME: vrijdag 03/10/2025 - 18:00
    $fake_time_label = 'FAKE TIME: ' . $weekday . ' ' . $formatted;
}

// Handler: JSON POST to create order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $customer = $data['customer'] ?? [];
    $meta = $data['meta'] ?? [];
    $cart = $data['cart'] ?? [];

    if (empty($cart)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Empty cart']);
        exit;
    }

    // minimal server-side validation
    $verzendmethode = ($meta['verzendmethode'] ?? 'afhalen');
    $betaalmethode = ($meta['betaalmethode'] ?? 'terplaatse');

    // Server-side validation: if delivery is requested, require straat/postcode/plaats
    $verzendmethode = ($meta['verzendmethode'] ?? 'afhalen');
    if ($verzendmethode === 'levering') {
        $straat = trim((string)($customer['straat'] ?? ''));
        $postcode = trim((string)($customer['postcode'] ?? ''));
        $plaats = trim((string)($customer['plaats'] ?? ''));
        if ($straat === '' || $postcode === '' || $plaats === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Vul straat, postcode en plaats in voor levering.']);
            exit;
        }
    }

    try {
        $mysqli->begin_transaction();

        // Check voorraadbeheer setting
        $voorraadbeheer = 1;
        $stmt_vb = $mysqli->prepare("SELECT voorraadbeheer FROM instellingen LIMIT 1");
        if ($stmt_vb) {
            $stmt_vb->execute();
            $res_vb = $stmt_vb->get_result();
            if ($res_vb && $row_vb = $res_vb->fetch_assoc()) {
                $voorraadbeheer = (int)$row_vb['voorraadbeheer'];
            }
            $stmt_vb->close();
        }

        // If timeslot management is enabled, validate chosen slot BEFORE reserving stock
        $tijdsloten_beheer = 0;
        $stmt_tb = $mysqli->prepare("SELECT tijdsloten_beheer FROM instellingen LIMIT 1");
        if ($stmt_tb) {
            $stmt_tb->execute();
            $res_tb = $stmt_tb->get_result();
            if ($res_tb && ($row_tb = $res_tb->fetch_assoc())) {
                $tijdsloten_beheer = (int)$row_tb['tijdsloten_beheer'];
            }
            $stmt_tb->close();
        }

        if ($tijdsloten_beheer === 1) {
            $slot_id = isset($meta['bezorg_slot_id']) ? (int)$meta['bezorg_slot_id'] : 0;
            $bez_unix = isset($meta['bezorg_unix']) ? (int)$meta['bezorg_unix'] : 0;
            if ($slot_id <= 0 || $bez_unix <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Kies een geldig tijdslot.']);
                exit;
            }

            $s = $mysqli->prepare("SELECT id, start_time, day_of_week, preparation_minutes, capacity FROM timeslot_fixed_ranges WHERE id = ? LIMIT 1 FOR UPDATE");
            if (!$s) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'Server error (timeslot lookup).']);
                exit;
            }
            $s->bind_param('i', $slot_id);
            $s->execute();
            $sr = $s->get_result();
            if (!($slot = $sr->fetch_assoc())) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Gekozen tijdslot bestaat niet.']);
                exit;
            }

            // Compute expected slot start unix timestamp using provided bezorg_unix date
            $slot_start_unix = strtotime(date('Y-m-d', $bez_unix) . ' ' . $slot['start_time']);
            // Allow small tolerance (2 minutes) for mismatch
            if (abs($slot_start_unix - $bez_unix) > 120) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Gekozen tijdslot komt niet overeen met de geselecteerde datum/tijd.']);
                exit;
            }

            $prep = isset($slot['preparation_minutes']) && $slot['preparation_minutes'] !== null ? (int)$slot['preparation_minutes'] : 0;
            // use $base_time (which may be overridden via session override for testing)
            $now = $base_time;
            // cutoff: if preparation_minutes set, require booking before (slot_start - prep); otherwise, require booking before slot start
            $cutoff = $slot_start_unix - ($prep * 60);
            if ($prep <= 0) $cutoff = $slot_start_unix;
            if ($now >= $cutoff) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Dit tijdslot kan niet meer worden gekozen (verlopen).']);
                exit;
            }

            // Optional: check capacity > 0
            if (isset($slot['capacity']) && $slot['capacity'] !== null) {
                if ((int)$slot['capacity'] <= 0) {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'error' => 'Dit tijdslot is vol.']);
                    exit;
                }
            }

            // sanitize meta values for later use
            $meta['bezorg_unix'] = $slot_start_unix;
            $meta['bezorg_slot_id'] = $slot_id;

            // Reservation flow: ensure capacity considering existing orders and reservations
            $reservation_id = null;
            // count existing orders for this exact unix bezorgmoment (exclude canceled/mispaid)
            $cntOrdersStmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM bestellingen WHERE UNIX_bezorgmoment = ? AND STATUS_BESTELLING NOT IN ('geannuleerd','betaal_mislukt')");
            if ($cntOrdersStmt) {
                $cntOrdersStmt->bind_param('i', $slot_start_unix);
                $cntOrdersStmt->execute();
                $cr = $cntOrdersStmt->get_result();
                $ordersCount = ($cr && ($rr = $cr->fetch_assoc())) ? (int)$rr['cnt'] : 0;
            } else {
                $ordersCount = 0;
            }

            // count existing reservations (only pending 'reserved' ones). Confirmed reservations correspond to orders and are counted in ordersCount.
            $cntResStmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM timeslot_reservations WHERE timeslot_id = ? AND status = 'reserved'");
            if ($cntResStmt) {
                $cntResStmt->bind_param('i', $slot_id);
                $cntResStmt->execute();
                $cr2 = $cntResStmt->get_result();
                $resCount = ($cr2 && ($rr2 = $cr2->fetch_assoc())) ? (int)$rr2['cnt'] : 0;
            } else {
                $resCount = 0;
            }

            $totalTaken = $ordersCount + $resCount;
            $capacity = isset($slot['capacity']) && $slot['capacity'] !== null ? (int)$slot['capacity'] : null;
            if ($capacity !== null && $totalTaken >= $capacity) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Dit tijdslot is vol.']);
                exit;
            }

            // insert reservation row (status reserved)
            $token = bin2hex(random_bytes(8));
            $insRes = $mysqli->prepare("INSERT INTO timeslot_reservations (timeslot_id, reserved_unix, status, client_token, reserved_at) VALUES (?, ?, 'reserved', ?, NOW())");
            if ($insRes) {
                $insRes->bind_param('iis', $slot_id, $slot_start_unix, $token);
                $insRes->execute();
                $reservation_id = $mysqli->insert_id;
            }
        }

        // 1) Reserve/Reduce stock per item (only if voorraadbeheer is enabled)
        if ($voorraadbeheer == 1) {
            foreach ($cart as $item) {
                $art_id = (int)($item['product_id'] ?? 0);
                $qty = (int)($item['qty'] ?? 0);
                if ($art_id <= 0 || $qty <= 0) throw new Exception('Invalid cart item');

                // If this cart item is a backorder (or has an expected_date), skip stock reservation
                $is_backorder = (!empty($item['backorder']) || !empty($item['expected_date']));
                if ($is_backorder) {
                    // debug log for diagnosis
                    error_log("checkout: skipping stock reservation for backorder item art_id={$art_id} qty={$qty} expected_date=" . (isset($item['expected_date']) ? $item['expected_date'] : ''));
                    // do not attempt to lock/update voorraad for backorder items
                    continue;
                }

                // build opties string like group:value|...
                $opties = '';
                if (!empty($item['options']) && is_array($item['options'])) {
                    $pairs = [];
                    foreach ($item['options'] as $o) {
                        if (!empty($o['affects_stock']) && ($o['affects_stock'] == 1 || $o['affects_stock'] === true)) {
                            $val = $o['option_id'] ?? ($o['value'] ?? '');
                            $pairs[] = ($o['group_name'] ?? '') . ':' . $val;
                        }
                    }
                    sort($pairs);
                    $opties = implode('|', $pairs);
                }

                // Fetch product levertijd (non-zero betekent backorder/lead time)
                $art_levertijd = 0;
                $ltStmt = $mysqli->prepare("SELECT art_levertijd FROM products WHERE art_id = ? LIMIT 1");
                if ($ltStmt) {
                    $ltStmt->bind_param('i', $art_id);
                    $ltStmt->execute();
                    $ltRes = $ltStmt->get_result();
                    if ($ltRes && ($ltRow = $ltRes->fetch_assoc())) {
                        $art_levertijd = (int)$ltRow['art_levertijd'];
                    }
                    $ltStmt->close();
                }

                // Try options-level voorraad first
                $sel = $mysqli->prepare("SELECT voorraad_id, stock FROM voorraad WHERE art_id = ? AND opties = ? FOR UPDATE");
                if (!$sel) throw new Exception('Prepare failed: ' . $mysqli->error);
                $sel->bind_param('is', $art_id, $opties);
                $sel->execute();
                $res = $sel->get_result();

                if ($res && $row = $res->fetch_assoc()) {
                    // If product has levertijd set (non-zero), allow negative stock by updating without throwing
                    if ((int)$row['stock'] < $qty) {
                        if ($art_levertijd !== 0) {
                            // allow negative stock; update anyway
                            error_log("checkout: variant stock low but levertijd set, updating anyway for art_id={$art_id} needed={$qty} available={$row['stock']}");
                        } else {
                            error_log("checkout: insufficient variant stock for art_id={$art_id} needed={$qty} available={$row['stock']} backorder=" . ($is_backorder ? '1' : '0'));
                            throw new Exception('Niet genoeg voorraad voor product ' . $art_id);
                        }
                    }
                    $upd = $mysqli->prepare("UPDATE voorraad SET stock = stock - ? WHERE voorraad_id = ?");
                    if (!$upd) throw new Exception('Prepare failed: ' . $mysqli->error);
                    $upd->bind_param('ii', $qty, $row['voorraad_id']);
                    $upd->execute();
                    // Log stock movement for variant
                    $newQ = null;
                    $qget = $mysqli->prepare("SELECT stock FROM voorraad WHERE voorraad_id = ? LIMIT 1");
                    if ($qget) {
                        $qget->bind_param('i', $row['voorraad_id']);
                        $qget->execute();
                        $rnew = $qget->get_result();
                        if ($rnew && ($rn = $rnew->fetch_assoc())) $newQ = (int)$rn['stock'];
                        $qget->close();
                    }
                    $reason = 'Bestelling';
                    $ins = $mysqli->prepare("INSERT INTO stock_movements (product_id, quantity_change, new_quantity, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
                    if ($ins) {
                        $neg = -1 * (int)$qty;
                        $ins->bind_param('iiis', $art_id, $neg, $newQ, $reason);
                        $ins->execute();
                        $ins->close();
                    }
                } else {
                    // fallback to products.art_aantal
                    $ps = $mysqli->prepare("SELECT art_aantal FROM products WHERE art_id = ? FOR UPDATE");
                    if (!$ps) throw new Exception('Prepare failed: ' . $mysqli->error);
                    $ps->bind_param('i', $art_id);
                    $ps->execute();
                    $pres = $ps->get_result();
                    if (!($p = $pres->fetch_assoc())) {
                        error_log("checkout: product not found art_id={$art_id}");
                        throw new Exception('Product niet gevonden ' . $art_id);
                    }
                    if ((int)$p['art_aantal'] < $qty) {
                        if ($art_levertijd !== 0) {
                            // product has levertijd: allow negative stock and update anyway
                            error_log("checkout: product stock low but levertijd set, updating anyway for art_id={$art_id} needed={$qty} available={$p['art_aantal']}");
                        } else {
                            error_log("checkout: insufficient product stock for art_id={$art_id} needed={$qty} available={$p['art_aantal']} backorder=" . ($is_backorder ? '1' : '0'));
                            throw new Exception('Niet genoeg voorraad voor product ' . $art_id);
                        }
                    }
                    $upd2 = $mysqli->prepare("UPDATE products SET art_aantal = art_aantal - ? WHERE art_id = ?");
                    if (!$upd2) throw new Exception('Prepare failed: ' . $mysqli->error);
                    $upd2->bind_param('ii', $qty, $art_id);
                    $upd2->execute();
                    // Log stock movement for product-level stock
                    $newQ2 = null;
                    $qget2 = $mysqli->prepare("SELECT art_aantal FROM products WHERE art_id = ? LIMIT 1");
                    if ($qget2) {
                        $qget2->bind_param('i', $art_id);
                        $qget2->execute();
                        $rnew2 = $qget2->get_result();
                        if ($rnew2 && ($rn2 = $rnew2->fetch_assoc())) $newQ2 = (int)$rn2['art_aantal'];
                        $qget2->close();
                    }
                    $reason2 = 'Bestelling';
                    $ins2 = $mysqli->prepare("INSERT INTO stock_movements (product_id, quantity_change, new_quantity, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
                    if ($ins2) {
                        $neg2 = -1 * (int)$qty;
                        $ins2->bind_param('iiis', $art_id, $neg2, $newQ2, $reason2);
                        $ins2->execute();
                        $ins2->close();
                    }
                }
            }
        }

        // 2) Bereken totals: frontend stuurt prijs INCLUSIEF BTW. Zet om naar exclusief BTW
        //    - kostprijs (en bedrag_excl_btw) moeten exclusief BTW zijn
        //    - saldo_6/12/21 moeten de BTW-bedragen per tarief bevatten
        //    - bestelling_tebetalen is de som van (prijs_incl * qty) + verzendkosten
        $bedrag_excl = 0.0;
        $btw6 = $btw12 = $btw21 = 0.0;
        $totaal_incl_items = 0.0; // sum of client-provided inclusive prices
        foreach ($cart as $item) {
            $price_incl = (float)($item['price'] ?? 0);
            $qty = (int)($item['qty'] ?? 0);
            $btw = isset($item['btw']) ? (int)$item['btw'] : 21;

            // protect against zero-division if BTW is 0
            if ($btw === 0) {
                $price_excl = $price_incl;
            } else {
                $price_excl = $price_incl / (1 + ($btw / 100.0));
            }

            $line_excl = $price_excl * $qty;
            $bedrag_excl += $line_excl;

            // BTW-bedrag is berekend op basis van het excl. bedrag
            $btw_amount = $line_excl * ($btw / 100.0);
            if ($btw === 6) $btw6 += $btw_amount;
            elseif ($btw === 12) $btw12 += $btw_amount;
            else $btw21 += $btw_amount;

            $totaal_incl_items += $price_incl * $qty;
        }

        $verzendkosten = number_format((float)($meta['verzendkosten'] ?? 0.0), 8, '.', '');
        $totaal = $bedrag_excl + $btw6 + $btw12 + $btw21 + (float)$verzendkosten;

        // 3) Insert bestelling
        $unix_bestelling = time();
        $unix_bezorg = (int)($meta['bezorg_unix'] ?? 0);

        // Map cart to simplified format for storing in bestellingen.inhoud_bestelling
        $saved_items = [];
        // prepare image lookup (first image)
        $imgStmt = $mysqli->prepare("SELECT image_name FROM product_images WHERE product_id = ? AND image_order = 1 LIMIT 1");
        foreach ($cart as $item) {
            $product_id = isset($item['product_id']) ? (int)$item['product_id'] : null;
            // id: prefer a stable JS identifier when present (cart item id), else fall back to product_id or generated id
            $id = isset($item['js-identifier']) && $item['js-identifier'] ? (string)$item['js-identifier'] : ($product_id ? (string)$product_id : (isset($item['id']) ? (string)$item['id'] : uniqid()));
            // accept either 'omschrijving' or 'name' from the frontend payload
            $omschrijving = isset($item['omschrijving']) && $item['omschrijving'] !== '' ? $item['omschrijving'] : ($item['name'] ?? '');
            // kostprijs: frontend sends price INCLUDING BTW. Store kostprijs EXCL. BTW
            $price_incl = isset($item['price']) ? (float)$item['price'] : 0.0;
            $BTWtarief = isset($item['btw']) ? (int)$item['btw'] : 21;
            if ($BTWtarief === 0) {
                $kostprijs = $price_incl;
            } else {
                $kostprijs = $price_incl / (1 + ($BTWtarief / 100.0));
            }
            $aantal = isset($item['qty']) ? (int)$item['qty'] : 1;
            $js_identifier = $item['js-identifier'] ?? ('cart_' . uniqid());
            $afbeelding = '';
            if ($product_id && $imgStmt) {
                $imgStmt->bind_param('i', $product_id);
                $imgStmt->execute();
                $ires = $imgStmt->get_result();
                if ($ires && ($ir = $ires->fetch_assoc())) {
                    $afbeelding = $ir['image_name'];
                }
            }
            $saved_items[] = [
                'id' => $id,
                'omschrijving' => $omschrijving,
                'kostprijs' => $kostprijs,
                'BTWtarief' => $BTWtarief,
                'aantal' => $aantal,
                'js-identifier' => $js_identifier,
                'afbeelding' => $afbeelding,
                'backorder' => !empty($item['backorder']) ? 1 : 0,
                'expected_date' => isset($item['expected_date']) ? $item['expected_date'] : null,
            ];
        }
        if ($imgStmt) $imgStmt->close();

        // Use UNESCAPED_SLASHES to keep the JSON human-friendly (no escaped '/') and UNESCAPED_UNICODE for UTF-8 text
        // Do NOT call real_escape_string when using prepared statements — storing the raw JSON avoids double-escaping (backslashes)
        $inhoud = json_encode($saved_items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $gebruikers_id = 1;
        // Respect explicit guest flag from client (set when user clicked continue-as-guest)
        $is_guest = !empty($meta['guest']) ? true : false;

        // Determine klant_id: ONLY trust the server-side session klant_id.
        // Do NOT accept a client-supplied klant_id for updates - guests must not be able to modify klanten.
        // If this is a guest checkout we explicitly ignore any session klant_id and use NULL.
        $klant_id = (!empty($_SESSION['klant_id']) && !$is_guest) ? (int)$_SESSION['klant_id'] : null;

        // If this is a guest checkout, create a guest_info row and capture the guest_id
        $guest_id = null;
        if ($is_guest) {
            // Use the customer payload where available; missing fields default to empty string
            $g_voor = trim((string)($customer['voornaam'] ?? ''));
            $g_ach = trim((string)($customer['achternaam'] ?? ''));
            $g_email = trim((string)($customer['email'] ?? ''));
            $g_tel = trim((string)($customer['telefoon'] ?? ''));
            $g_bedrijf = trim((string)($customer['bedrijfsnaam'] ?? ''));
            $g_straat = trim((string)($customer['straat'] ?? ''));
            $g_huis = trim((string)($customer['huisnummer'] ?? ''));
            $g_postcode = trim((string)($customer['postcode'] ?? ''));
            $g_plaats = trim((string)($customer['plaats'] ?? ''));
            $g_land = trim((string)($customer['land'] ?? 'België'));
            $g_btw = trim((string)($customer['btw_nummer'] ?? ''));

            $insGuest = $mysqli->prepare("INSERT INTO guest_info (voornaam, achternaam, email, telefoon, bedrijfsnaam, straat, huisnummer, postcode, plaats, land, btw_nummer, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if ($insGuest) {
                $insGuest->bind_param('sssssssssss', $g_voor, $g_ach, $g_email, $g_tel, $g_bedrijf, $g_straat, $g_huis, $g_postcode, $g_plaats, $g_land, $g_btw);
                if ($insGuest->execute()) {
                    $guest_id = $mysqli->insert_id ?: null;
                } else {
                    error_log('checkout: failed to insert guest_info: ' . $insGuest->error);
                    // proceed without guest_id — order will still be created but guest_id remains NULL
                    $guest_id = null;
                }
                $insGuest->close();
            } else {
                error_log('checkout: prepare guest_info insert failed: ' . $mysqli->error);
            }
        }

        // Only update klanten when the user is authenticated (session klant_id present) AND not a guest
        if ($klant_id > 0 && !$is_guest) {
            $qk = $mysqli->prepare("SELECT voornaam, achternaam, email, telefoon, straat, postcode, plaats, land, bedrijfsnaam, btw_nummer FROM klanten WHERE klant_id = ? FOR UPDATE");
            if ($qk) {
                $qk->bind_param('i', $klant_id);
                $qk->execute();
                $rk = $qk->get_result();
                if ($rk && ($old = $rk->fetch_assoc())) {
                    // choose new values only when non-empty, otherwise preserve existing
                    $new_voor = trim((string)($customer['voornaam'] ?? ''));
                    $new_ach = trim((string)($customer['achternaam'] ?? ''));
                    $new_email = trim((string)($customer['email'] ?? ''));
                    $new_tel = trim((string)($customer['telefoon'] ?? ''));
                    $new_straat = trim((string)($customer['straat'] ?? ''));
                    $new_postcode = trim((string)($customer['postcode'] ?? ''));
                    $new_plaats = trim((string)($customer['plaats'] ?? ''));
                    $new_land = trim((string)($customer['land'] ?? ''));
                    $new_bedrijf = trim((string)($customer['bedrijfsnaam'] ?? ''));
                    $new_btw = trim((string)($customer['btw_nummer'] ?? ''));

                    $upd_voor = ($new_voor !== '') ? $new_voor : $old['voornaam'];
                    $upd_ach = ($new_ach !== '') ? $new_ach : $old['achternaam'];
                    // Only update email if it's non-empty and not taken by another klant
                    $upd_email = $old['email'];
                    if ($new_email !== '' && $new_email !== $old['email']) {
                        $check = $mysqli->prepare("SELECT klant_id FROM klanten WHERE email = ? AND klant_id <> ? LIMIT 1");
                        if ($check) {
                            $check->bind_param('si', $new_email, $klant_id);
                            $check->execute();
                            $cres = $check->get_result();
                            if ($cres && !$cres->fetch_assoc()) {
                                $upd_email = $new_email;
                            } else {
                                // email already used by another klant: skip updating email to avoid conflicts
                                error_log("checkout: attempted to update klant $klant_id email to already-used $new_email");
                            }
                        }
                    }

                    $upd_tel = ($new_tel !== '') ? $new_tel : $old['telefoon'];
                    $upd_straat = ($new_straat !== '') ? $new_straat : $old['straat'];
                    $upd_postcode = ($new_postcode !== '') ? $new_postcode : $old['postcode'];
                    $upd_plaats = ($new_plaats !== '') ? $new_plaats : $old['plaats'];
                    $upd_land = ($new_land !== '') ? $new_land : $old['land'];
                    $upd_bedrijf = ($new_bedrijf !== '') ? $new_bedrijf : ($old['bedrijfsnaam'] ?? '');
                    $upd_btw = ($new_btw !== '') ? $new_btw : ($old['btw_nummer'] ?? '');

                    $up = $mysqli->prepare("UPDATE klanten SET voornaam = ?, achternaam = ?, email = ?, telefoon = ?, straat = ?, postcode = ?, plaats = ?, land = ?, bedrijfsnaam = ?, btw_nummer = ? WHERE klant_id = ?");
                    if ($up) {
                        $up->bind_param('ssssssssssi', $upd_voor, $upd_ach, $upd_email, $upd_tel, $upd_straat, $upd_postcode, $upd_plaats, $upd_land, $upd_bedrijf, $upd_btw, $klant_id);
                        $up->execute();
                    }
                }
            }
        }

        $leverbedrijfsnaam = $customer['bedrijfsnaam'] ?? '';
        $levernaam = trim(($customer['voornaam'] ?? '') . ' ' . ($customer['achternaam'] ?? ''));
        $leverstraat = $customer['straat'] ?? '';
        $leverpostcode = $customer['postcode'] ?? '';
        $leverplaats = $customer['plaats'] ?? '';
        $leverland = $customer['land'] ?? 'Belgie';
        $status = ($betaalmethode === 'online') ? 'wacht_op_betaling' : 'in_behandeling';
        $reeds_betaald = ($betaalmethode === 'terplaatse') ? 'nee-cash' : 'niet-betaald';

        // Parse BTW number into country code and number for bestelling fields
        $bestelling_memberstate = '';
        $bestelling_btw_nummer = '';
        $cust_btw = trim((string)($customer['btw_nummer'] ?? ''));
        if ($cust_btw !== '') {
            $bw = strtoupper(preg_replace('/\s+/', '', $cust_btw));
            if (preg_match('/^([A-Z]{2})(.+)$/', $bw, $m)) {
                $bestelling_memberstate = $m[1];
                $bestelling_btw_nummer = preg_replace('/[^0-9A-Z]/', '', $m[2]);
            } else {
                // no country prefix, try to store as-is in nummer
                $bestelling_btw_nummer = preg_replace('/[^0-9A-Z]/', '', $bw);
            }
        }

        $stmt = $mysqli->prepare("INSERT INTO bestellingen
            (gebruikers_id, klant_id, guest_id, bestelling_datum, UNIX_bestelling, UNIX_bezorgmoment,
             leverbedrijfsnaam, levernaam, leverstraat, leverpostcode, leverplaats, leverland,
             STATUS_BESTELLING, inhoud_bestelling, verzendmethode, verzendkosten,
             bestelling_memberstate, bestelling_btw_nummer,
             bedrag_excl_btw, saldo_6, saldo_12, saldo_21, bestelling_tebetalen, reeds_betaald)
            VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);

        $params = [
            $gebruikers_id,
            $klant_id,
            $guest_id,
            $unix_bestelling,
            $unix_bezorg,
            $leverbedrijfsnaam,
            $levernaam,
            $leverstraat,
            $leverpostcode,
            $leverplaats,
            $leverland,
            $status,
            $inhoud,
            $verzendmethode,
            $verzendkosten,
            $bestelling_memberstate,
            $bestelling_btw_nummer,
            number_format($bedrag_excl, 10, '.', ''),
            number_format($btw6, 10, '.', ''),
            number_format($btw12, 10, '.', ''),
            number_format($btw21, 10, '.', ''),
            number_format($totaal, 10, '.', ''),
            $reeds_betaald
        ];

        // Compute types string dynamically: first three ints (gebruikers_id, klant_id, guest_id) then rest strings
        $types = 'iii' . str_repeat('s', count($params) - 3);
        $stmt_bind = array_merge([$types], $params);
        // bind_param requires references
        $tmp = [];
        foreach ($stmt_bind as $k => $v) $tmp[$k] = &$stmt_bind[$k];
        call_user_func_array([$stmt, 'bind_param'], $tmp);

        $stmt->execute();
        $bestelling_id = $mysqli->insert_id;

        // If we created a reservation, confirm it by linking to bestelling
        if (!empty($reservation_id)) {
            $updRes = $mysqli->prepare("UPDATE timeslot_reservations SET bestelling_id = ?, status = 'confirmed' WHERE id = ?");
            if ($updRes) {
                $updRes->bind_param('ii', $bestelling_id, $reservation_id);
                $updRes->execute();
            }
        }

        $mysqli->commit();

        // Send confirmation emails via Microsoft Graph (no fallback)
        try {
            require_once __DIR__ . '/../zozo-includes/mail_graph.php';

            // determine customer email
            $cust_email = $customer['email'] ?? null;
            if (empty($cust_email) && !empty($klant_id)) {
                $q = $mysqli->prepare('SELECT email FROM klanten WHERE klant_id = ? LIMIT 1');
                if ($q) {
                    $q->bind_param('i', $klant_id);
                    $q->execute();
                    $r = $q->get_result();
                    $row = $r ? $r->fetch_assoc() : null;
                    $cust_email = $row['email'] ?? null;
                }
            }

            // determine MS_FROM_EMAIL from local config or DB
            $ms_from_email = null;
            $cfgFile = __DIR__ . '/../zozo-includes/mail_config.php';
            if (file_exists($cfgFile)) {
                // mail_config.php defines $MS_FROM_EMAIL
                include $cfgFile;
                $ms_from_email = $MS_FROM_EMAIL ?? null;
            }
            if (empty($ms_from_email)) {
                $kq = $mysqli->query("SELECT waarde FROM instellingen WHERE naam = 'ms_from_email' LIMIT 1");
                $krow = $kq ? $kq->fetch_assoc() : null;
                $ms_from_email = $krow['waarde'] ?? null;
            }

            // Render HTML email using template with item list
            try {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

                // Prepare data for template
                $order_info = [
                    'levernaam' => $levernaam,
                    'leverstraat' => $leverstraat,
                    'leverplaats' => $leverplaats,
                    'UNIX_bezorgmoment' => $unix_bezorg,
                    'verzendmethode' => $verzendmethode,
                    'email' => $cust_email
                ];
                $items = $saved_items;
                $total_amount = $totaal;

                // Ensure $MS_FROM_EMAIL variable available inside template
                if (!isset($MS_FROM_EMAIL)) $MS_FROM_EMAIL = $ms_from_email ?? null;

                ob_start();
                $tplPath = __DIR__ . '/../zozo-templates/email/order_confirmation.php';
                if (file_exists($tplPath)) {
                    // fetch shop info from instellingen
                    $shop_info = [];
                    $srows = $mysqli->query("SELECT naam, waarde FROM instellingen WHERE naam IN ('bedrijfsnaam','adres','telefoon','email')");
                    if ($srows) {
                        while ($sr = $srows->fetch_assoc()) {
                            $k = $sr['naam'];
                            $shop_info[$k] = $sr['waarde'];
                        }
                    }
                    // expose variables expected by template: $order_id, $order_row, $items, $total, $baseUrl, $MS_FROM_EMAIL
                    $order_id = $bestelling_id;
                    $order_row = $order_info;
                    $items = $saved_items;
                    $total = $total_amount;
                    $baseUrl = $baseUrl;
                    $shop_info = $shop_info;
                    include $tplPath;
                    $html = ob_get_clean();
                } else {
                    ob_end_clean();
                    $html = '<p>Bedankt voor je bestelling. Ordernummer: <strong>' . htmlspecialchars($bestelling_id) . '</strong></p>';
                    $html .= '<p>Bedrag: &euro;' . htmlspecialchars(number_format((float)$totaal, 2, '.', ',')) . '</p>';
                }

                $subject = 'Bevestiging bestelling #' . $bestelling_id;

                // Mail sending moved to bedanktpagina: do not call send_mail_graph here.
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    @session_start();
                }
                if (!empty($cust_email)) {
                    $_SESSION['mail_stub'][] = 'checkout skipped mail to customer ' . $cust_email . ' for order ' . $bestelling_id;
                    error_log('Order mail to customer SKIPPED in checkout for ' . $cust_email);
                }
                if (!empty($ms_from_email)) {
                    $_SESSION['mail_stub'][] = 'checkout skipped mail to shop ' . $ms_from_email . ' for order ' . $bestelling_id;
                    error_log('Order mail to shop SKIPPED in checkout for ' . $ms_from_email);
                }
            } catch (Throwable $e) {
                error_log('Order template/render failed: ' . $e->getMessage());
            }
        } catch (Throwable $e) {
            error_log('Order mailer initialization failed: ' . $e->getMessage());
        }

        $response = ['ok' => true, 'bestelling_id' => $bestelling_id];

        // Online betaling: create Mollie payment using SDK
        if ($betaalmethode === 'online') {
            $kq = $mysqli->query("SELECT Mollie_API_key FROM instellingen LIMIT 1");
            $krow = $kq ? $kq->fetch_assoc() : null;
            $mollieKey = $krow['Mollie_API_key'] ?? '';

            if (empty($mollieKey)) {
                $mysqli->query("UPDATE bestellingen SET STATUS_BESTELLING = 'betaal_mislukt' WHERE bestelling_id = " . intval($bestelling_id));
                // rollback reserved stock (same logic as above)
                foreach ($cart as $item) {
                    $art_id = (int)($item['product_id'] ?? 0);
                    $qty = (int)($item['qty'] ?? 0);
                    $opties = '';
                    if (!empty($item['options']) && is_array($item['options'])) {
                        $pairs = [];
                        foreach ($item['options'] as $o) {
                            if (!empty($o['affects_stock']) && ($o['affects_stock'] == 1 || $o['affects_stock'] === true)) {
                                $val = $o['option_id'] ?? ($o['value'] ?? '');
                                $pairs[] = ($o['group_name'] ?? '') . ':' . $val;
                            }
                        }
                        sort($pairs);
                        $opties = implode('|', $pairs);
                    }
                    $sel = $mysqli->prepare("SELECT voorraad_id FROM voorraad WHERE art_id = ? AND opties = ?");
                    $sel->bind_param('is', $art_id, $opties);
                    $sel->execute();
                    $r = $sel->get_result();
                    if ($r && $rr = $r->fetch_assoc()) {
                        $upd = $mysqli->prepare("UPDATE voorraad SET stock = stock + ? WHERE voorraad_id = ?");
                        $upd->bind_param('ii', $qty, $rr['voorraad_id']);
                        $upd->execute();
                        // Log stock movement (rollback)
                        $newQ = null;
                        $qget = $mysqli->prepare("SELECT stock FROM voorraad WHERE voorraad_id = ? LIMIT 1");
                        if ($qget) {
                            $qget->bind_param('i', $rr['voorraad_id']);
                            $qget->execute();
                            $rnew = $qget->get_result();
                            if ($rnew && ($rn = $rnew->fetch_assoc())) $newQ = (int)$rn['stock'];
                            $qget->close();
                        }
                        $reason = 'Order rollback';
                        $ins = $mysqli->prepare("INSERT INTO stock_movements (product_id, quantity_change, new_quantity, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
                        if ($ins) {
                            $ins->bind_param('iiis', $art_id, $qty, $newQ, $reason);
                            $ins->execute();
                            $ins->close();
                        }
                    } else {
                        $upd2 = $mysqli->prepare("UPDATE products SET art_aantal = art_aantal + ? WHERE art_id = ?");
                        $upd2->bind_param('ii', $qty, $art_id);
                        $upd2->execute();
                        // Log stock movement (rollback) for product-level stock
                        $newQ2 = null;
                        $qget2 = $mysqli->prepare("SELECT art_aantal FROM products WHERE art_id = ? LIMIT 1");
                        if ($qget2) {
                            $qget2->bind_param('i', $art_id);
                            $qget2->execute();
                            $rnew2 = $qget2->get_result();
                            if ($rnew2 && ($rn2 = $rnew2->fetch_assoc())) $newQ2 = (int)$rn2['art_aantal'];
                            $qget2->close();
                        }
                        $reason2 = 'Order rollback';
                        $ins2 = $mysqli->prepare("INSERT INTO stock_movements (product_id, quantity_change, new_quantity, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
                        if ($ins2) {
                            $ins2->bind_param('iiis', $art_id, $qty, $newQ2, $reason2);
                            $ins2->execute();
                            $ins2->close();
                        }
                    }
                }

                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'Mollie API key not configured']);
                exit;
            }

            // Prepare URLs
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
            $webhookUrl = $baseUrl . '/zozo-pages/webhook_mollie.php';
            $redirectUrl = $baseUrl . '/bedankt?order=' . $bestelling_id;

            // Create payment using Mollie SDK
            require_once __DIR__ . '/../vendor/autoload.php';
            try {
                $mollie = new \Mollie\Api\MollieApiClient();
                $mollie->setApiKey($mollieKey);

                $payment = $mollie->payments->create([
                    'amount' => ['currency' => 'EUR', 'value' => number_format((float)$totaal, 2, '.', '')],
                    'description' => 'Bestelling #' . $bestelling_id,
                    'redirectUrl' => $redirectUrl,
                    'webhookUrl' => $webhookUrl,
                    'metadata' => ['order_id' => $bestelling_id]
                ]);

                $mollieId = $payment->id;
                $checkoutUrl = $payment->_links->checkout->href ?? null;
                $u = $mysqli->prepare("UPDATE bestellingen SET Mollie_betaal_id = ?, STATUS_BESTELLING = ? WHERE bestelling_id = ?");
                $snew = 'wacht_op_betaling';
                $u->bind_param('ssi', $mollieId, $snew, $bestelling_id);
                $u->execute();

                $response['online_payment'] = true;
                $response['payment_redirect'] = $checkoutUrl ?: $redirectUrl;
            } catch (\Exception $ex) {
                // restore stock on failure and mark order
                $mysqli->query("UPDATE bestellingen SET STATUS_BESTELLING = 'betaal_mislukt' WHERE bestelling_id = " . intval($bestelling_id));
                foreach ($cart as $item) {
                    $art_id = (int)($item['product_id'] ?? 0);
                    $qty = (int)($item['qty'] ?? 0);
                    $opties = '';
                    if (!empty($item['options']) && is_array($item['options'])) {
                        $pairs = [];
                        foreach ($item['options'] as $o) {
                            if (!empty($o['affects_stock']) && ($o['affects_stock'] == 1 || $o['affects_stock'] === true)) {
                                $val = $o['option_id'] ?? ($o['value'] ?? '');
                                $pairs[] = ($o['group_name'] ?? '') . ':' . $val;
                            }
                        }
                        sort($pairs);
                        $opties = implode('|', $pairs);
                    }
                    $sel = $mysqli->prepare("SELECT voorraad_id FROM voorraad WHERE art_id = ? AND opties = ?");
                    $sel->bind_param('is', $art_id, $opties);
                    $sel->execute();
                    $r = $sel->get_result();
                    if ($r && $rr = $r->fetch_assoc()) {
                        $upd = $mysqli->prepare("UPDATE voorraad SET stock = stock + ? WHERE voorraad_id = ?");
                        $upd->bind_param('ii', $qty, $rr['voorraad_id']);
                        $upd->execute();
                        // Log stock movement (rollback)
                        $newQ = null;
                        $qget = $mysqli->prepare("SELECT stock FROM voorraad WHERE voorraad_id = ? LIMIT 1");
                        if ($qget) {
                            $qget->bind_param('i', $rr['voorraad_id']);
                            $qget->execute();
                            $rnew = $qget->get_result();
                            if ($rnew && ($rn = $rnew->fetch_assoc())) $newQ = (int)$rn['stock'];
                            $qget->close();
                        }
                        $reason = 'Order rollback';
                        $ins = $mysqli->prepare("INSERT INTO stock_movements (product_id, quantity_change, new_quantity, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
                        if ($ins) {
                            $ins->bind_param('iiis', $art_id, $qty, $newQ, $reason);
                            $ins->execute();
                            $ins->close();
                        }
                    } else {
                        $upd2 = $mysqli->prepare("UPDATE products SET art_aantal = art_aantal + ? WHERE art_id = ?");
                        $upd2->bind_param('ii', $qty, $art_id);
                        $upd2->execute();
                        // Log stock movement (rollback) for product-level stock
                        $newQ2 = null;
                        $qget2 = $mysqli->prepare("SELECT art_aantal FROM products WHERE art_id = ? LIMIT 1");
                        if ($qget2) {
                            $qget2->bind_param('i', $art_id);
                            $qget2->execute();
                            $rnew2 = $qget2->get_result();
                            if ($rnew2 && ($rn2 = $rnew2->fetch_assoc())) $newQ2 = (int)$rn2['art_aantal'];
                            $qget2->close();
                        }
                        $reason2 = 'Order rollback';
                        $ins2 = $mysqli->prepare("INSERT INTO stock_movements (product_id, quantity_change, new_quantity, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
                        if ($ins2) {
                            $ins2->bind_param('iiis', $art_id, $qty, $newQ2, $reason2);
                            $ins2->execute();
                            $ins2->close();
                        }
                    }
                }

                http_response_code(502);
                echo json_encode(['ok' => false, 'error' => 'Failed to create Mollie payment', 'exception' => $ex->getMessage()]);
                exit;
            }
        }

        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        http_response_code(500);
        // If we created a reservation but failed later, mark it cancelled
        if (!empty($reservation_id)) {
            $canc = $mysqli->prepare("UPDATE timeslot_reservations SET status = 'cancelled' WHERE id = ?");
            if ($canc) {
                $canc->bind_param('i', $reservation_id);
                $canc->execute();
            }
        }
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Otherwise: render checkout form (simple)
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'nl') ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(($fake_time_label ?? '') . ($translations['Afrekenen'][$lang] ?? 'Afrekenen')) ?></title>
    <link rel="stylesheet" href="/zozo-assets/css/zozo-main.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-navbar.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-topbar.css">
    <style>
        /* Keep only input/label styles so form structure/layout remains untouched.
           Inputs use a subtle bottom-border to preserve the mobile "lines" look. */

        .col {
            max-width: 800px;
            margin: 0 auto;
            padding: 16px;
        }

        .hide {
            display: none;
        }

        #checkout-form label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.95rem;
            color: #0b1220;
        }

        /* Inputs styled as single-line fields with bottom border (lines look on mobile)
           and full-width on their container. */
        #checkout-form input[type="text"],
        #checkout-form input[type="email"],
        #checkout-form input[type="tel"],
        #checkout-form input[type="datetime-local"],
        #checkout-form input[type="number"],
        #checkout-form input,
        #checkout-form select,
        #checkout-form textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 8px 0;
            border: none;
            border-bottom: 1px solid #d1d5db;
            background: transparent;
            font-size: 0.98rem;
            color: #0b1220;
            transition: border-bottom-color .15s ease;
        }

        #checkout-form input:focus,
        #checkout-form select:focus,
        #checkout-form textarea:focus {
            outline: none;
            border-bottom-color: #1f2937;
        }

        /* keep default layout for rows and buttons (no overrides here) */
        .fake-time-label {
            display: inline-block;
            background: #f97316;
            /* orange */
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            padding: 6px 10px;
            border-radius: 999px;
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.05);
            white-space: nowrap;
        }

        /* On small screens stack title and badge */
        @media (max-width: 640px) {
            h1.page-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }

            .fake-time-label {
                font-size: 0.92rem;
                padding: 5px 8px;
            }
        }

        h1.page-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 0 0 8px 0;
        }

        h1.page-title .page-title-main {
            font-size: 1.6rem;
            margin: 0;
        }
    </style>
</head>

<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-topbar.php";
    include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-navbar.php"; ?>
    <main class="col">
        <h1 class="page-title">
            <span class="page-title-main"><?= htmlspecialchars($translations['Afrekenen'][$lang] ?? 'Afrekenen') ?></span>
            <?php if (!empty($fake_time_label)): ?>
                <span class="fake-time-label"><?= htmlspecialchars(trim($fake_time_label)) ?></span>
            <?php endif; ?>
        </h1>
        <?php
        // Localized back link (minimal translations)
        $curr_lang = isset($lang) ? $lang : 'nl';
        $back_label = $translations['Terug'][$curr_lang] ?? '< terug';
        $back_url = '/' . $curr_lang . '/cart';
        ?>
        <p style="margin-top:6px;margin-bottom:18px;"><a href="<?= htmlspecialchars($back_url) ?>" style="color:#1f2937;text-decoration:none;font-weight:600;"><?= htmlspecialchars($back_label) ?></a></p>
        <div id="msg"></div>

        <!-- Stepper: 1) Email 2) Auth options 3) Checkout form -->
        <div id="step-email">
            <h2><?= htmlspecialchars($translations['Je e-mail'][$lang] ?? 'Je e‑mail') ?></h2>
            <p><?= htmlspecialchars($translations['Vul je e-mail in om door te gaan'][$lang] ?? 'Vul je e‑mail in om door te gaan') ?></p>
            <form id="step-email-form">
                <input type="email" id="step-email-input" placeholder="<?= htmlspecialchars($translations['email_placeholder'][$lang] ?? 'email@voorbeeld.nl') ?>" required style="padding:10px;border:1px solid #ddd;border-radius:6px;width:100%;max-width:100%">
                <div style="margin-top:12px">
                    <button type="submit" id="step-email-continue" class="btn-primary" style="display:block;width:100%;padding:12px;border-radius:8px;border:none;font-weight:700"><?= htmlspecialchars($translations['Doorgaan'][$lang] ?? 'Doorgaan') ?></button>
                </div>
            </form>
        </div>

        <div id="step-auth" class="hide">
            <h2><?= htmlspecialchars($translations['Inloggen'][$lang] ?? 'Inloggen') ?></h2>
            <div>
                <input type="hidden" id="auth-email-hidden">
                <label id="auth-password-label"><?= htmlspecialchars($translations['Je wachtwoord'][$lang] ?? 'Je wachtwoord') ?></label>
                <input type="password" id="auth-password" placeholder="<?= htmlspecialchars($translations['Je wachtwoord'][$lang] ?? 'Je wachtwoord') ?>" style="padding:10px;border:1px solid #ddd;border-radius:6px;width:100%;box-sizing:border-box;margin-bottom:12px">
                <div id="confirm-password-row" class="hide">
                    <label><?= htmlspecialchars($translations['Herhaal je wachtwoord'][$lang] ?? 'Herhaal je wachtwoord') ?></label>
                    <input type="password" id="auth-password-confirm" placeholder="<?= htmlspecialchars($translations['Herhaal je wachtwoord'][$lang] ?? 'Herhaal je wachtwoord') ?>" style="padding:10px;border:1px solid #ddd;border-radius:6px;width:100%;box-sizing:border-box;margin-bottom:12px">
                </div>

                <div id="auth-forgot-row" class="hide" style="margin-bottom:8px"><a href="/wachtwoord_vergeten.php"><?= htmlspecialchars($translations['Wachtwoord vergeten?'][$lang] ?? 'Wachtwoord vergeten?') ?></a></div>

                <div>
                    <button id="auth-login-btn" class="btn-primary" style="display:block;width:100%;padding:12px;border-radius:8px;border:none;font-weight:700;margin-bottom:10px"><?= htmlspecialchars($translations['Inloggen'][$lang] ?? 'Inloggen') ?></button>
                    <button id="auth-magic-btn" class="hide" style="display:block;width:100%;background:#f3f4f6;color:#111;padding:12px;border-radius:8px;border:1px solid #e5e7eb;font-weight:600"><?= htmlspecialchars($translations['Inloggen zonder wachtwoord (code via mail)'][$lang] ?? 'Inloggen zonder wachtwoord (code via mail)') ?></button>
                </div>

                <!-- Code entry UI (hidden until a code is sent) -->
                <div id="code-entry" class="hide" style="margin-top:12px">
                    <label><?= htmlspecialchars($translations['Voer de 4-cijferige code in'][$lang] ?? 'Voer de 4-cijferige code in') ?></label>
                    <input id="login-code-input" placeholder="0000" maxlength="4" style="padding:10px;border:1px solid #ddd;border-radius:6px;width:100%;box-sizing:border-box;margin-bottom:8px;font-size:1.2rem;letter-spacing:6px;text-align:center">
                    <div style="display:flex;gap:8px;">
                        <button id="verify-code-btn" class="btn-primary" style="flex:1;padding:10px;border-radius:8px;border:none;font-weight:700"><?= htmlspecialchars($translations['Verifiëren'][$lang] ?? 'Verifiëren') ?></button>
                        <button id="resend-code-btn" style="flex:1;background:#f3f4f6;color:#111;padding:10px;border-radius:8px;border:1px solid #e5e7eb;font-weight:600"><?= htmlspecialchars($translations['Opnieuw verzenden'][$lang] ?? 'Opnieuw verzenden') ?></button>
                    </div>
                    <div id="code-status" style="margin-top:8px;color:#064e3b"></div>
                </div>

                <!--
                <div style="margin-top:8px;text-align:center;"><a href="#" id="auth-guest-link"><?= htmlspecialchars($translations['> doorgaan zonder inloggen'][$lang] ?? '&gt; doorgaan zonder inloggen') ?></a></div>
                -->
            </div>
            <div id="auth-status" style="margin-top:8px;color:#064e3b"></div>
        </div>

        <!-- Inline register UI (hidden) -->
        <div id="inline-register" class="hide" style="margin-top:12px">
            <h3><?= htmlspecialchars($translations['Account aanmaken'][$lang] ?? 'Account aanmaken') ?></h3>
            <label><?= htmlspecialchars($translations['Voornaam'][$lang] ?? 'Voornaam') ?> <input id="reg-voornaam"></label>
            <label><?= htmlspecialchars($translations['Achternaam'][$lang] ?? 'Achternaam') ?> <input id="reg-achternaam"></label>
            <label><?= htmlspecialchars($translations['Je wachtwoord'][$lang] ?? 'Wachtwoord') ?> <input id="reg-password" type="password" placeholder="minimaal 6 tekens"></label>
            <div style="margin-top:8px">
                <button id="reg-submit" class="btn-primary" style="display:block;width:100%;padding:10px;border-radius:8px;border:none;font-weight:700">Account aanmaken</button>
            </div>
            <div id="reg-status" style="margin-top:8px;color:#064e3b"></div>
        </div>

        <form id="checkout-form" class="hide">
            <fieldset>
                <legend><?= htmlspecialchars($translations['Contact'][$lang] ?? 'Contact') ?></legend>
                <div class="tabs" role="tablist" aria-label="Klanttype">
                    <button type="button" id="tab-particulier" class="active" data-type="particulier"><?= htmlspecialchars($translations['Particulier'][$lang] ?? 'Particulier') ?></button>
                    <button type="button" id="tab-zakelijk" data-type="zakelijk"><?= htmlspecialchars($translations['Zakelijk'][$lang] ?? 'Zakelijk') ?></button>
                </div>

                <!-- Zakelijk fields first (hidden unless Zakelijk selected) -->
                <div id="zakelijk-fields" class="zakelijk-container hide">
                    <label><?= htmlspecialchars($translations['Bedrijfsnaam'][$lang] ?? 'Bedrijfsnaam') ?> <input class="form-control" name="bedrijfsnaam"></label>
                    <label><?= htmlspecialchars($translations['BTW_nummer'][$lang] ?? 'BTW‑nummer') ?> <input class="form-control" name="btw_nummer"></label>
                </div>

                <!-- Compact contact rows: firstname+lastname and email+phone -->
                <div class="form-row" style="margin-bottom:8px">
                    <div class="form-col"><label><?= htmlspecialchars($translations['Voornaam'][$lang] ?? 'Voornaam') ?> <input class="form-control" name="voornaam" required></label></div>
                    <div class="form-col"><label><?= htmlspecialchars($translations['Achternaam'][$lang] ?? 'Achternaam') ?> <input class="form-control" name="achternaam" required></label></div>
                </div>

                <div class="form-row" style="margin-bottom:8px">
                    <div class="form-col"><label><?= htmlspecialchars($translations['Email'][$lang] ?? 'Email') ?> <input class="form-control" type="email" name="email" required readonly></label></div>
                    <div class="form-col"><label><?= htmlspecialchars($translations['Telefoon'][$lang] ?? 'Telefoon') ?> <input class="form-control" name="telefoon"></label></div>
                </div>
            </fieldset>

            <fieldset>
                <legend><?= htmlspecialchars($translations['Aflevering'][$lang] ?? 'Aflevering') ?></legend>
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px">
                    <label style="margin:0"><input type="radio" name="verzendmethode" value="afhalen" checked> <?= htmlspecialchars($translations['Afhalen'][$lang] ?? 'Afhalen') ?></label>
                    <label style="margin:0"><input type="radio" name="verzendmethode" value="levering"> <?= htmlspecialchars($translations['Leveren'][$lang] ?? 'Leveren') ?></label>
                </div>

                <div id="delivery-address" class="hide">
                    <label><?= htmlspecialchars($translations['Straat'][$lang] ?? 'Straat') ?> <input class="form-control" name="straat"></label>
                    <label><?= htmlspecialchars($translations['Postcode'][$lang] ?? 'Postcode') ?> <input class="form-control" name="postcode"></label>
                    <label><?= htmlspecialchars($translations['Plaats'][$lang] ?? 'Plaats') ?> <input class="form-control" name="plaats"></label>
                    <label><?= htmlspecialchars($translations['Land'][$lang] ?? 'Land') ?> <input class="form-control" name="land" value="Belgie"></label>
                </div>

                <?php
                // Check instelling: tijdsloten_beheer (0/1)
                $tijdsloten_beheer = 0;
                $stmt_tb = $mysqli->prepare("SELECT tijdsloten_beheer FROM instellingen LIMIT 1");
                if ($stmt_tb) {
                    $stmt_tb->execute();
                    $res_tb = $stmt_tb->get_result();
                    if ($res_tb && ($row_tb = $res_tb->fetch_assoc())) {
                        $tijdsloten_beheer = (int)$row_tb['tijdsloten_beheer'];
                    }
                    $stmt_tb->close();
                }

                if ($tijdsloten_beheer === 1) {
                    // Build available timeslots for the next N days (configurable via instellingen.tijdsloten_dagen)
                    $timeslots_by_date = [];

                    // Read configured horizon from instellingen (fallback to 14)
                    $max_days = 14;
                    $tds = $mysqli->prepare("SELECT tijdsloten_dagen FROM instellingen LIMIT 1");
                    if ($tds) {
                        $tds->execute();
                        $rtd = $tds->get_result();
                        if ($rtd && ($rowtd = $rtd->fetch_assoc())) {
                            $v = intval($rowtd['tijdsloten_dagen']);
                            if ($v > 0) $max_days = $v;
                        }
                        $tds->close();
                    }
                    // Clamp to reasonable bounds
                    if ($max_days < 1) $max_days = 1;
                    if ($max_days > 365) $max_days = 365;

                    // Load holiday periods and convert to unix ranges
                    $holiday_ranges = [];
                    $hq = $mysqli->query("SELECT start_date, start_time, end_date, end_time FROM timeslot_holidays");
                    if ($hq) {
                        while ($hr = $hq->fetch_assoc()) {
                            $hs = strtotime(($hr['start_date'] ?? '') . ' ' . ($hr['start_time'] ?? ''));
                            $he = strtotime(($hr['end_date'] ?? '') . ' ' . ($hr['end_time'] ?? ''));
                            if ($hs && $he && $he >= $hs) {
                                $holiday_ranges[] = ['start' => $hs, 'end' => $he];
                            }
                        }
                    }
                    for ($i = 0; $i < $max_days; $i++) {
                        // Use $base_time so we can override 'now' for testing
                        $date = date('Y-m-d', $base_time + ($i * 86400));
                        $dow = (int)date('N', strtotime($date)); // 1..7
                        $q = $mysqli->prepare("SELECT id, start_time, end_time, capacity, type, preparation_minutes FROM timeslot_fixed_ranges WHERE day_of_week = ? ORDER BY start_time");
                        if ($q) {
                            $q->bind_param('i', $dow);
                            $q->execute();
                            $r = $q->get_result();
                            $arr = [];
                            while ($row = $r->fetch_assoc()) {
                                // compute slot start unix for this date + start_time
                                $slot_start_unix = strtotime($date . ' ' . $row['start_time']);

                                // Skip slot if it falls inside any holiday range
                                $skip_slot = false;
                                if (!empty($holiday_ranges) && $slot_start_unix) {
                                    foreach ($holiday_ranges as $hrange) {
                                        if ($slot_start_unix >= $hrange['start'] && $slot_start_unix <= $hrange['end']) {
                                            $skip_slot = true;
                                            break;
                                        }
                                    }
                                }
                                if ($skip_slot) continue;

                                // determine cutoff for this slot: if preparation_minutes set, cutoff = slot_start - prep, else cutoff = slot_start
                                $prep_minutes = isset($row['preparation_minutes']) && $row['preparation_minutes'] !== null ? (int)$row['preparation_minutes'] : 0;
                                $slot_cutoff = $slot_start_unix - ($prep_minutes * 60);
                                if ($prep_minutes <= 0) $slot_cutoff = $slot_start_unix;
                                // if the cutoff is already passed relative to $base_time, skip including this slot
                                if ($base_time >= $slot_cutoff) continue;

                                // count confirmed orders for this unix bezorgmoment
                                $ordersCount = 0;
                                $cntOrdersStmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM bestellingen WHERE UNIX_bezorgmoment = ? AND STATUS_BESTELLING NOT IN ('geannuleerd','betaal_mislukt')");
                                if ($cntOrdersStmt) {
                                    $cntOrdersStmt->bind_param('i', $slot_start_unix);
                                    $cntOrdersStmt->execute();
                                    $cr = $cntOrdersStmt->get_result();
                                    $ordersCount = ($cr && ($rr = $cr->fetch_assoc())) ? (int)$rr['cnt'] : 0;
                                }

                                // count pending reservations for this timeslot_id
                                $resCount = 0;
                                $cntResStmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM timeslot_reservations WHERE timeslot_id = ? AND status = 'reserved'");
                                if ($cntResStmt) {
                                    $cntResStmt->bind_param('i', $row['id']);
                                    $cntResStmt->execute();
                                    $cr2 = $cntResStmt->get_result();
                                    $resCount = ($cr2 && ($rr2 = $cr2->fetch_assoc())) ? (int)$rr2['cnt'] : 0;
                                }

                                $capacity = isset($row['capacity']) && $row['capacity'] !== null ? (int)$row['capacity'] : null;
                                $totalTaken = $ordersCount + $resCount;
                                $remaining = $capacity === null ? null : max(0, $capacity - $totalTaken);

                                // only include slot if capacity is unlimited (null) or remaining > 0
                                if ($capacity === null || $remaining > 0) {
                                    $row['remaining'] = $remaining;
                                    $arr[] = $row;
                                }
                            }
                            $q->close();
                        } else {
                            $arr = [];
                        }
                        if (!empty($arr)) $timeslots_by_date[$date] = $arr;
                    }
                ?>
                    <div id="slot-picker">
                        <label>Datum
                            <select id="slot_date" class="form-control"></select>
                        </label>
                        <label>Tijd
                            <select id="slot_time" class="form-control"></select>
                        </label>
                        <input type="hidden" id="bezorg_unix" name="bezorg_unix" value="">
                        <input type="hidden" id="bezorg_slot_id" name="bezorg_slot_id" value="">
                    </div>
                    <script>
                        window.tijdsloten_enabled = true;
                        window.availableTimeslots = <?= json_encode($timeslots_by_date) ?>;
                        // Expose server base time for debugging: Unix ts and human readable
                        window.serverBaseTime = <?= json_encode($base_time) ?>;
                        window.serverBaseTimeHuman = <?= json_encode(date('Y-m-d H:i', $base_time)) ?>;
                        // for quick console check: console.log('serverBaseTimeHuman', window.serverBaseTimeHuman);
                    </script>
                <?php
                } else {
                    // fallback: keep original datetime-local input
                ?>
                    <label>Gewenste datum en tijd <input class="form-control" type="datetime-local" name="bezorg_moment"></label>
                <?php
                }
                ?>
            </fieldset>

            <fieldset>
                <legend><?= htmlspecialchars($translations['Betaling'][$lang] ?? 'Betaling') ?></legend>
                <div style="display:flex;gap:12px;align-items:center">
                    <label style="margin:0"><input type="radio" name="betaalmethode" value="terplaatse" checked> <?= htmlspecialchars($translations['Betalen ter plaatse'][$lang] ?? 'Betalen ter plaatse') ?></label>
                    <label style="margin:0"><input type="radio" name="betaalmethode" value="online"> <?= htmlspecialchars($translations['Online betalen'][$lang] ?? 'Online betalen') ?></label>
                </div>
            </fieldset>

            <div style="margin-top:12px">
                <button type="submit" class="btn-primary"><?= htmlspecialchars($translations['Bestelling plaatsen'][$lang] ?? 'Bestelling plaatsen') ?></button>
            </div>
        </form>

        <script>
            // Small client-side translations for dynamic text used by the auth flow
            window.checkoutTranslations = {
                inloggen: <?= json_encode($translations['Inloggen'][$lang] ?? 'Inloggen') ?>,
                registreren: <?= json_encode($translations['Registreren'][$lang] ?? 'Registreren') ?>,
                wachtwoord_label: <?= json_encode($translations['Je wachtwoord'][$lang] ?? 'Je wachtwoord') ?>,
                kies_wachtwoord: <?= json_encode($translations['Kies een wachtwoord'][$lang] ?? 'Kies een wachtwoord') ?>,
                kies_een_datum: <?= json_encode($translations['Kies een datum'][$lang] ?? 'Kies een datum') ?>,
                kies_een_tijdslot: <?= json_encode($translations['Kies een tijdslot'][$lang] ?? 'Kies een tijdslot') ?>,
                reset_sending: <?= json_encode($translations['Reset_sending'][$lang] ?? 'Resetlink versturen...') ?>,
                no_email_found: <?= json_encode($translations['Geen e-mail gevonden'][$lang] ?? 'Geen e‑mail gevonden') ?>,
                sending: <?= json_encode($translations['Versturen...'][$lang] ?? 'Versturen...') ?>,
                code_sent_check_email: <?= json_encode($translations['Code verzonden. Controleer je e‑mail.'][$lang] ?? 'Code verzonden. Controleer je e‑mail.') ?>,
                error_prefix: <?= json_encode($translations['Error_prefix'][$lang] ?? 'Fout: ') ?>,
                network_error: <?= json_encode($translations['Netwerkfout'][$lang] ?? 'Netwerkfout') ?>,
                password_min_len: <?= json_encode($translations['Wachtwoord minimaal 6 tekens'][$lang] ?? 'Wachtwoord minimaal 6 tekens') ?>,
                passwords_not_match: <?= json_encode($translations['Wachtwoorden komen niet overeen'][$lang] ?? 'Wachtwoorden komen niet overeen') ?>,
                registering: <?= json_encode($translations['Versturen...'][$lang] ?? 'Versturen...') ?>,
                account_created_logged_in: <?= json_encode($translations['Account aangemaakt en ingelogd'][$lang] ?? 'Account aangemaakt en ingelogd') ?>,
                logging_in: <?= json_encode($translations['Inloggen'][$lang] ?? 'Inloggen') ?>,
                login_successful: <?= json_encode($translations['Inloggen geslaagd'][$lang] ?? 'Inloggen geslaagd') ?>,
                account_created: <?= json_encode($translations['Account aangemaakt'][$lang] ?? 'Account aangemaakt') ?>,
                logged_in: <?= json_encode($translations['Ingelogd'][$lang] ?? 'Ingelogd') ?>,
                creating_account: <?= json_encode($translations['Versturen...'][$lang] ?? 'Versturen...') ?>,
                code_4_digits: <?= json_encode($translations['Voer de 4-cijferige code in'][$lang] ?? 'Voer een 4-cijferige code in') ?>,
                checking: <?= json_encode($translations['Controleren...'][$lang] ?? 'Controleren...') ?>,
                resending: <?= json_encode($translations['Opnieuw versturen...'][$lang] ?? 'Opnieuw versturen...') ?>,
                code_resent: <?= json_encode($translations['Code opnieuw verzonden.'][$lang] ?? 'Code opnieuw verzonden.') ?>,
                enter_valid_email: <?= json_encode($translations['Voer een geldig e‑mailadres in'][$lang] ?? 'Voer een geldig e‑mailadres in') ?>,
                login_link_sent: <?= json_encode($translations['Inloglink verzonden. Controleer je e‑mail.'][$lang] ?? 'Inloglink verzonden. Controleer je e‑mail.') ?>,
                network_error_try_later: <?= json_encode($translations['Netwerkfout, probeer later opnieuw'][$lang] ?? 'Netwerkfout, probeer later opnieuw') ?>
            };

            function showMsg(t, c) {
                document.getElementById('msg').innerText = t;
            }

            // track if the user explicitly chose to continue as guest
            let checkout_is_guest = false;

            // Fill checkout form fields from klant data object
            function fillCustomer(k) {
                if (!k) return;
                try {
                    const set = (name, val) => {
                        const el = document.querySelector('[name="' + name + '"]');
                        if (el) el.value = val || '';
                    };
                    set('voornaam', k.voornaam || k.first_name || '');
                    set('achternaam', k.achternaam || k.last_name || '');
                    set('email', k.email || '');
                    set('telefoon', k.telefoon || k.phone || '');
                    set('straat', k.straat || '');
                    set('postcode', k.postcode || '');
                    set('plaats', k.plaats || '');
                    set('land', k.land || k.leverland || 'Belgie');
                    // If address present, show delivery address section only when 'levering' is selected
                    try {
                        const hasAddress = (k.straat && k.straat.length) || (k.postcode && k.postcode.length);
                        const deliverySelected = (document.querySelector('input[name="verzendmethode"]:checked') || {}).value === 'levering';
                        const deliveryEl = document.getElementById('delivery-address');
                        if (deliveryEl) {
                            deliveryEl.classList.toggle('hide', !(hasAddress && deliverySelected));
                        }
                    } catch (e) {
                        // ignore
                    }
                    // If klant has btw_nummer, preselect Zakelijk and show fields
                    if (k.btw_nummer && k.btw_nummer.length) {
                        selectTab('zakelijk');
                        const bf = document.querySelector('input[name="bedrijfsnaam"]');
                        if (bf) bf.value = k.bedrijfsnaam || '';
                        const bn = document.querySelector('input[name="btw_nummer"]');
                        if (bn) bn.value = k.btw_nummer || '';
                    }
                } catch (e) {
                    console.warn('fillCustomer error', e);
                }
            }

            function selectTab(which) {
                const part = document.getElementById('tab-particulier');
                const zak = document.getElementById('tab-zakelijk');
                const zfields = document.getElementById('zakelijk-fields');
                if (which === 'zakelijk') {
                    zak.classList.add('active');
                    part.classList.remove('active');
                    if (zfields) zfields.classList.remove('hide');
                    zak.setAttribute('aria-pressed', 'true');
                    part.setAttribute('aria-pressed', 'false');
                } else {
                    part.classList.add('active');
                    zak.classList.remove('active');
                    if (zfields) zfields.classList.add('hide');
                    zak.setAttribute('aria-pressed', 'false');
                    part.setAttribute('aria-pressed', 'true');
                }
            }

            // Stepper handlers
            const stepEmail = document.getElementById('step-email');
            const stepAuth = document.getElementById('step-auth');
            const checkoutForm = document.getElementById('checkout-form');

            document.getElementById('step-email-form').addEventListener('submit', function(ev) {
                ev.preventDefault();
                const email = document.getElementById('step-email-input').value.trim();
                if (!email) return;
                // prefill auth email display and hidden input, and checkout form
                const authDisplay = document.getElementById('auth-email-display');
                const authHidden = document.getElementById('auth-email-hidden');
                if (authDisplay) authDisplay.innerText = email;
                if (authHidden) authHidden.value = email;
                const emailFields = document.querySelectorAll('input[name=email]');
                emailFields.forEach(f => f.value = email);
                // check if email exists to show login or register
                (async function() {
                    try {
                        const res = await fetch('/check_email.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                email
                            })
                        });
                        const j = await res.json();
                        if (j.ok) {
                            const forgotRow = document.getElementById('auth-forgot-row');
                            const magicBtn = document.getElementById('auth-magic-btn');
                            // if not exists OR exists but no password -> show register
                            if (!j.exists || (j.exists && j.has_password === false)) {
                                document.getElementById('confirm-password-row').classList.remove('hide');
                                document.getElementById('auth-login-btn').innerText = window.checkoutTranslations.registreren;
                                document.getElementById('auth-password-label').innerText = window.checkoutTranslations.kies_wachtwoord;
                                // hide forgot-password and magic-login; no password exists
                                if (forgotRow) forgotRow.classList.add('hide');
                                if (magicBtn) magicBtn.classList.add('hide');
                            } else {
                                // exists and has password -> login
                                document.getElementById('confirm-password-row').classList.add('hide');
                                document.getElementById('auth-login-btn').innerText = window.checkoutTranslations.inloggen;
                                document.getElementById('auth-password-label').innerText = window.checkoutTranslations.wachtwoord_label;
                                // show forgot-password and magic-login
                                if (forgotRow) forgotRow.classList.remove('hide');
                                if (magicBtn) magicBtn.classList.remove('hide');
                            }
                        }
                    } catch (err) {
                        // fallback: show login and hide forgot/magic buttons (safe default)
                        document.getElementById('confirm-password-row').classList.add('hide');
                        document.getElementById('auth-login-btn').innerText = window.checkoutTranslations.inloggen;
                        const forgotRow = document.getElementById('auth-forgot-row');
                        const magicBtn = document.getElementById('auth-magic-btn');
                        if (forgotRow) forgotRow.classList.add('hide');
                        if (magicBtn) magicBtn.classList.add('hide');
                    } finally {
                        // move to auth step
                        stepEmail.classList.add('hide');
                        stepAuth.classList.remove('hide');
                    }
                })();
            });

            // Guest link: mark as guest, skip auth and show checkout
            // The guest link may be commented out in the HTML. Guard the call so
            // we don't call addEventListener on null (which throws a TypeError).
            const guestLink = document.getElementById('auth-guest-link');
            if (guestLink) {
                guestLink.addEventListener('click', function(ev) {
                    ev.preventDefault();
                    checkout_is_guest = true;
                    stepAuth.classList.add('hide');
                    checkoutForm.classList.remove('hide');
                });
            }

            // Tabs: Particulier / Zakelijk
            document.getElementById('tab-particulier').addEventListener('click', function() {
                selectTab('particulier');
            });
            document.getElementById('tab-zakelijk').addEventListener('click', function() {
                selectTab('zakelijk');
            });

            // Auth magic button: trigger existing magic link flow using auth-email
            document.getElementById('auth-magic-btn').addEventListener('click', async function(ev) {
                ev.preventDefault();
                const email = document.getElementById('auth-email-hidden').value.trim();
                const status = document.getElementById('auth-status');
                if (!email) {
                    status.innerText = window.checkoutTranslations.no_email_found || <?= json_encode($translations['Geen e-mail gevonden'][$lang] ?? 'Geen e‑mail gevonden') ?>;
                    return;
                }
                status.innerText = window.checkoutTranslations.sending || <?= json_encode($translations['Versturen...'][$lang] ?? 'Versturen...') ?>;
                try {
                    const res = await fetch('/create_login_code.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            email: email,
                            lang: document.documentElement.lang || 'nl'
                        })
                    });
                    const j = await res.json();
                    if (j.ok) {
                        status.innerText = window.checkoutTranslations.code_sent_check_email || <?= json_encode($translations['Code verzonden. Controleer je e‑mail.'][$lang] ?? 'Code verzonden. Controleer je e‑mail.') ?>;
                        document.getElementById('code-entry').classList.remove('hide');
                        document.getElementById('login-code-input').focus();
                    } else {
                        status.innerText = (window.checkoutTranslations.error_prefix || 'Fout: ') + (j.error || j.message || 'Onbekend');
                    }
                } catch (err) {
                    status.innerText = window.checkoutTranslations.network_error || <?= json_encode($translations['Netwerkfout'][$lang] ?? 'Netwerkfout') ?>;
                }
            });

            // 'Wachtwoord vergeten?' immediate flow: use entered email and send reset token
            const forgotLink = document.querySelector('a[href="/wachtwoord_vergeten.php"]');
            if (forgotLink) {
                forgotLink.addEventListener('click', async function(ev) {
                    ev.preventDefault();
                    const email = document.getElementById('auth-email-hidden').value.trim();
                    const status = document.getElementById('auth-status');
                    if (!email) {
                        // no email known yet; redirect to full page
                        window.location.href = '/wachtwoord_vergeten.php';
                        return;
                    }
                    status.innerText = <?= json_encode($translations['Reset_sending'][$lang] ?? 'Resetlink versturen...') ?>;
                    try {
                        const res = await fetch('/create_reset_token.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                email: email,
                                lang: document.documentElement.lang || 'nl'
                            })
                        });
                        const j = await res.json();
                        if (j.ok) {
                            if (j.sent) {
                                status.innerText = <?= json_encode($translations['Reset_sent'][$lang] ?? 'Reset link sent. Check your email.') ?>;
                            } else {
                                status.innerText = <?= json_encode($translations['Reset_not_sent'][$lang] ?? 'Reset link not sent via Graph. Check console for details.') ?>;
                            }
                        } else {
                            status.innerText = (window.checkoutTranslations.error_prefix || 'Fout: ') + (j.error || 'Onbekend');
                        }
                    } catch (err) {
                        status.innerText = window.checkoutTranslations.network_error || <?= json_encode($translations['Netwerkfout'][$lang] ?? 'Netwerkfout') ?>;
                    }
                });
            }

            // Auth login button: perform login or inline registration depending on button label
            document.getElementById('auth-login-btn').addEventListener('click', async function(ev) {
                ev.preventDefault();
                const email = document.getElementById('auth-email-hidden').value.trim();
                const pass = document.getElementById('auth-password').value || '';
                const confirmEl = document.getElementById('auth-password-confirm');
                const confirm = confirmEl ? confirmEl.value || '' : '';
                const status = document.getElementById('auth-status');

                if (!email) {
                    status.innerText = window.checkoutTranslations.no_email_found || <?= json_encode($translations['Geen e-mail gevonden'][$lang] ?? 'Geen e‑mail gevonden') ?>;
                    return;
                }
                if (!pass) {
                    status.innerText = window.checkoutTranslations.enter_password || <?= json_encode($translations['Kies een wachtwoord'][$lang] ?? 'Voer je wachtwoord in') ?>;
                    return;
                }

                const isRegister = (this.innerText || '').trim().toLowerCase() === 'registreren';

                if (isRegister) {
                    // registration flow
                    if (pass.length < 6) {
                        status.innerText = window.checkoutTranslations.password_min_len || <?= json_encode($translations['Wachtwoord minimaal 6 tekens'][$lang] ?? 'Wachtwoord minimaal 6 tekens') ?>;
                        return;
                    }
                    if (pass !== confirm) {
                        status.innerText = window.checkoutTranslations.passwords_not_match || <?= json_encode($translations['Wachtwoorden komen niet overeen'][$lang] ?? 'Wachtwoorden komen niet overeen') ?>;
                        return;
                    }
                    status.innerText = window.checkoutTranslations.registering || <?= json_encode($translations['Versturen...'][$lang] ?? 'Versturen...') ?>;
                    try {
                        const res = await fetch('/register.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                email: email,
                                password: pass,
                                voornaam: '',
                                achternaam: ''
                            })
                        });
                        const j = await res.json();
                        if (j.ok) {
                            status.innerText = window.checkoutTranslations.account_created_logged_in || <?= json_encode($translations['Account aangemaakt en ingelogd'][$lang] ?? 'Account aangemaakt en ingelogd') ?>;
                            // prefill checkout form if klant data returned
                            if (j.klant) fillCustomer(j.klant);
                            stepAuth.classList.add('hide');
                            checkoutForm.classList.remove('hide');
                        } else {
                            status.innerText = (window.checkoutTranslations.error_prefix || 'Fout: ') + (j.error || 'Onbekend');
                        }
                    } catch (err) {
                        status.innerText = window.checkoutTranslations.network_error || <?= json_encode($translations['Netwerkfout'][$lang] ?? 'Netwerkfout') ?>;
                    }
                } else {
                    // login flow
                    status.innerText = window.checkoutTranslations.logging_in || <?= json_encode($translations['Inloggen'][$lang] ?? 'Inloggen') ?> + '...';
                    try {
                        const res = await fetch('/login_password.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                email: email,
                                password: pass,
                                lang: document.documentElement.lang || 'nl'
                            })
                        });
                        const j = await res.json();
                        if (j.ok) {
                            status.innerText = window.checkoutTranslations.login_successful || <?= json_encode($translations['Inloggen geslaagd'][$lang] ?? 'Inloggen geslaagd') ?>;
                            if (j.klant) fillCustomer(j.klant);
                            stepAuth.classList.add('hide');
                            checkoutForm.classList.remove('hide');
                        } else {
                            status.innerText = (window.checkoutTranslations.error_prefix || 'Fout: ') + (j.error || 'Onbekend');
                        }
                    } catch (err) {
                        status.innerText = window.checkoutTranslations.network_error || <?= json_encode($translations['Netwerkfout'][$lang] ?? 'Netwerkfout') ?>;
                    }
                }
            });

            // Inline register submit
            document.getElementById('reg-submit').addEventListener('click', async function(ev) {
                ev.preventDefault();
                const email = document.getElementById('auth-email-hidden').value.trim();
                const voor = document.getElementById('reg-voornaam').value.trim();
                const ach = document.getElementById('reg-achternaam').value.trim();
                const pass = document.getElementById('reg-password').value || '';
                const st = document.getElementById('reg-status');
                if (pass.length < 6) {
                    st.innerText = window.checkoutTranslations.password_min_len || <?= json_encode($translations['Wachtwoord minimaal 6 tekens'][$lang] ?? 'Wachtwoord minimaal 6 tekens') ?>;
                    return;
                }
                st.innerText = window.checkoutTranslations.creating_account || <?= json_encode($translations['Versturen...'][$lang] ?? 'Versturen...') ?>;
                try {
                    const res = await fetch('/register.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            email: email,
                            password: pass,
                            voornaam: voor,
                            achternaam: ach
                        })
                    });
                    const j = await res.json();
                    if (j.ok) {
                        st.innerText = window.checkoutTranslations.account_created || <?= json_encode($translations['Account aangemaakt'][$lang] ?? 'Account aangemaakt') ?>;
                        // fill customer data
                        if (j.klant) fillCustomer(j.klant);
                        document.getElementById('inline-register').classList.add('hide');
                        document.getElementById('auth-status').innerText = window.checkoutTranslations.logged_in || <?= json_encode($translations['Ingelogd'][$lang] ?? 'Ingelogd') ?>;
                        stepAuth.classList.add('hide');
                        checkoutForm.classList.remove('hide');
                    } else {
                        st.innerText = (window.checkoutTranslations.error_prefix || 'Fout: ') + (j.error || 'Onbekend');
                    }
                } catch (err) {
                    st.innerText = window.checkoutTranslations.network_error || <?= json_encode($translations['Netwerkfout'][$lang] ?? 'Netwerkfout') ?>;
                }
            });

            // Verify code button
            document.getElementById('verify-code-btn').addEventListener('click', async function(ev) {
                ev.preventDefault();
                const email = document.getElementById('auth-email-hidden').value.trim();
                const code = document.getElementById('login-code-input').value.trim();
                const status = document.getElementById('code-status');
                if (!/^[0-9]{4}$/.test(code)) {
                    status.innerText = window.checkoutTranslations.code_4_digits || <?= json_encode($translations['Voer de 4-cijferige code in'][$lang] ?? 'Voer een 4-cijferige code in') ?>;
                    return;
                }
                status.innerText = window.checkoutTranslations.checking || <?= json_encode($translations['Controleren...'][$lang] ?? 'Controleren...') ?>;
                try {
                    const res = await fetch('/verify_login_code.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            email: email,
                            code: code,
                            return: window.location.pathname,
                            lang: document.documentElement.lang || 'nl'
                        })
                    });
                    const j = await res.json();
                    if (j.ok) {
                        status.innerText = window.checkoutTranslations.login_successful || <?= json_encode($translations['Inloggen geslaagd'][$lang] ?? 'Inloggen geslaagd') ?>;
                        // fill customer and proceed to checkout
                        if (j.klant) fillCustomer(j.klant);
                        stepAuth.classList.add('hide');
                        checkoutForm.classList.remove('hide');
                    } else {
                        status.innerText = (window.checkoutTranslations.error_prefix || 'Fout: ') + (j.error || j.message || 'Onbekend');
                    }
                } catch (err) {
                    status.innerText = window.checkoutTranslations.network_error || <?= json_encode($translations['Netwerkfout'][$lang] ?? 'Netwerkfout') ?>;
                }
            });

            // Resend code
            document.getElementById('resend-code-btn').addEventListener('click', async function(ev) {
                ev.preventDefault();
                const email = document.getElementById('auth-email-hidden').value.trim();
                const status = document.getElementById('code-status');
                status.innerText = window.checkoutTranslations.resending || <?= json_encode($translations['Opnieuw versturen...'][$lang] ?? 'Opnieuw versturen...') ?>;
                try {
                    const res = await fetch('/create_login_code.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            email: email,
                            lang: document.documentElement.lang || 'nl'
                        })
                    });
                    const j = await res.json();
                    if (j.ok) status.innerText = window.checkoutTranslations.code_resent || <?= json_encode($translations['Code opnieuw verzonden.'][$lang] ?? 'Code opnieuw verzonden.') ?>;
                    else status.innerText = (window.checkoutTranslations.error_prefix || 'Fout: ') + (j.error || j.message || 'Onbekend');
                } catch (err) {
                    status.innerText = window.checkoutTranslations.network_error || <?= json_encode($translations['Netwerkfout'][$lang] ?? 'Netwerkfout') ?>;
                }
            });

            // Magic link form handler (only if form exists)
            (function() {
                const mf = document.getElementById('magic-login-form');
                if (!mf) return;
                mf.addEventListener('submit', async function(ev) {
                    ev.preventDefault();
                    const email = (this.email.value || '').trim();
                    const status = document.getElementById('magic-login-status');
                    if (!email) {
                        if (status) status.innerText = window.checkoutTranslations.enter_valid_email || <?= json_encode($translations['Voer een geldig e‑mailadres in'][$lang] ?? 'Voer een geldig e‑mailadres in') ?>;
                        return;
                    }
                    if (status) status.innerText = window.checkoutTranslations.sending || <?= json_encode($translations['Versturen...'][$lang] ?? 'Versturen...') ?>;
                    try {
                        const res = await fetch('/create_magic_link.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                email: email,
                                return: window.location.pathname,
                                lang: document.documentElement.lang || 'nl'
                            })
                        });
                        const j = await res.json();
                        if (j.ok) {
                            if (status) status.innerText = window.checkoutTranslations.login_link_sent || <?= json_encode($translations['Inloglink verzonden. Controleer je e‑mail.'][$lang] ?? 'Inloglink verzonden. Controleer je e‑mail.') ?>;
                        } else {
                            if (status) status.innerText = (window.checkoutTranslations.error_prefix || 'Fout: ') + (j.error || j.message || 'Onbekend');
                        }
                    } catch (err) {
                        if (status) status.innerText = window.checkoutTranslations.network_error_try_later || <?= json_encode($translations['Netwerkfout, probeer later opnieuw'][$lang] ?? 'Netwerkfout, probeer later opnieuw') ?>;
                    }
                });
            })();

            document.querySelectorAll('input[name=verzendmethode]').forEach(r => r.addEventListener('change', e => {
                document.getElementById('delivery-address').classList.toggle('hide', e.target.value !== 'levering');
            }));

            // If slot picker is enabled, populate selects and wire selection to hidden inputs
            (function() {
                try {
                    if (window.tijdsloten_enabled && window.availableTimeslots) {
                        const dateEl = document.getElementById('slot_date');
                        const timeEl = document.getElementById('slot_time');
                        const unixEl = document.getElementById('bezorg_unix');
                        const slotIdEl = document.getElementById('bezorg_slot_id');

                        // determine if cart contains backorder items with an expected_date
                        let cartRequiredDate = null; // yyyy-mm-dd or null
                        try {
                            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
                            cart.forEach(i => {
                                // support either backorder flag or expected_date field
                                const isBack = !!(i.backorder || i.affects_stock === 0 && i.expected_date);
                                const ed = (i.expected_date || '').split('T')[0] || null;
                                if (isBack && ed) {
                                    if (!cartRequiredDate || ed > cartRequiredDate) cartRequiredDate = ed;
                                }
                            });
                        } catch (e) {
                            // ignore parse errors — fallback to no restriction
                            cartRequiredDate = null;
                        }

                        // (no UI note shown for backorder required date)

                        // populate dates with placeholder
                        const placeholderDate = document.createElement('option');
                        placeholderDate.value = '';
                        placeholderDate.textContent = window.checkoutTranslations.kies_een_datum || 'Kies een datum';
                        placeholderDate.disabled = true;
                        placeholderDate.selected = true;
                        dateEl.appendChild(placeholderDate);

                        function populateDatesForType(type) {
                            // clear existing (keep placeholder)
                            dateEl.querySelectorAll('option:not([value=""])').forEach(n => n.remove());
                            const allDates = Object.keys(window.availableTimeslots).sort();
                            allDates.forEach(d => {
                                const slots = window.availableTimeslots[d] || [];
                                // only include the date if there is at least one slot matching the current type
                                const hasForType = slots.some(s => s.type === type);
                                // if cart requires a later date, skip earlier dates
                                if (hasForType) {
                                    if (cartRequiredDate && d < cartRequiredDate) return;
                                    const opt = document.createElement('option');
                                    opt.value = d;
                                    // Format label: 'vrijdag 26-09-2025'
                                    try {
                                        const parts = d.split('-'); // yyyy-mm-dd
                                        const dateObj = new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
                                        const weekdays = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
                                        const wd = weekdays[dateObj.getDay()];
                                        const dd = ('0' + dateObj.getDate()).slice(-2);
                                        const mm = ('0' + (dateObj.getMonth() + 1)).slice(-2);
                                        const yyyy = dateObj.getFullYear();
                                        opt.textContent = wd + ' ' + dd + '-' + mm + '-' + yyyy;
                                    } catch (e) {
                                        opt.textContent = d;
                                    }
                                    dateEl.appendChild(opt);
                                }
                            });
                            // reset time select
                            timeEl.innerHTML = '';
                            const placeholderTime = document.createElement('option');
                            placeholderTime.value = '';
                            placeholderTime.textContent = window.checkoutTranslations.kies_een_tijdslot || 'Kies een tijdslot';
                            placeholderTime.disabled = true;
                            placeholderTime.selected = true;
                            timeEl.appendChild(placeholderTime);
                        }

                        // initial populate for the default selected verzendmethode
                        const currentMethod = document.querySelector('input[name="verzendmethode"]:checked').value || 'afhalen';
                        populateDatesForType(currentMethod === 'levering' ? 'delivery' : 'pickup');

                        // listen for changes in verzendmethode to repopulate dates
                        document.querySelectorAll('input[name="verzendmethode"]').forEach(r => r.addEventListener('change', function(e) {
                            const t = e.target.value === 'levering' ? 'delivery' : 'pickup';
                            populateDatesForType(t);
                        }));

                        function populateTimesForDate(d) {
                            timeEl.innerHTML = '';
                            const placeholderTime = document.createElement('option');
                            placeholderTime.value = '';
                            placeholderTime.textContent = 'Kies een tijdslot';
                            placeholderTime.disabled = true;
                            placeholderTime.selected = true;
                            timeEl.appendChild(placeholderTime);

                            const list = window.availableTimeslots[d] || [];
                            // filter by current selected verzendmethode
                            const currentMethod = document.querySelector('input[name="verzendmethode"]:checked').value || 'afhalen';
                            const wantedType = currentMethod === 'levering' ? 'delivery' : 'pickup';
                            // helper to format time strings like '06:00:00' or '06:00' -> '6u00'
                            function formatTimeLabel(ts) {
                                if (!ts) return '';
                                var parts = ts.split(':');
                                var hh = parts[0] ? parseInt(parts[0], 10) : 0;
                                var mm = parts[1] ? ('0' + parseInt(parts[1], 10)).slice(-2) : '00';
                                return hh + 'u' + mm;
                            }

                            list.forEach(s => {
                                if (s.type !== wantedType) return;
                                const opt = document.createElement('option');
                                opt.value = s.id + '|' + s.start_time + '|' + (s.preparation_minutes !== null ? s.preparation_minutes : '');
                                var startLabel = formatTimeLabel(s.start_time);
                                var endLabel = formatTimeLabel(s.end_time);
                                // Do not show preparation minutes to customers — keep option text simple
                                opt.textContent = startLabel + ' - ' + endLabel;
                                timeEl.appendChild(opt);
                            });
                        }

                        dateEl.addEventListener('change', function() {
                            const d = this.value;
                            populateTimesForDate(d);
                        });

                        timeEl.addEventListener('change', function() {
                            const v = this.value;
                            if (!v) return;
                            const parts = v.split('|');
                            const slotId = parseInt(parts[0]) || 0;
                            const start = parts[1];
                            const prep = parts[2] !== '' ? parseInt(parts[2]) : null;
                            // compute unix timestamp from selected date + start time
                            const d = dateEl.value;
                            if (d && start) {
                                const dt = new Date(d + 'T' + start);
                                unixEl.value = Math.floor(dt.getTime() / 1000);
                                slotIdEl.value = slotId;
                            }
                        });

                        // Do not auto-select a date here — keep the placeholder 'Kies een datum' selected
                    }
                } catch (err) {
                    console.warn('slot picker init error', err);
                }
            })();

            document.getElementById('checkout-form').addEventListener('submit', async function(ev) {
                ev.preventDefault();
                let cart = JSON.parse(localStorage.getItem('cart') || '[]');
                if (!cart || cart.length === 0) {
                    showMsg('Je winkelwagen is leeg');
                    return;
                }

                let form = new FormData(this);
                let customer = {
                    voornaam: form.get('voornaam'),
                    achternaam: form.get('achternaam'),
                    email: form.get('email'),
                    telefoon: form.get('telefoon'),
                    bedrijfsnaam: form.get('bedrijfsnaam'),
                    btw_nummer: form.get('btw_nummer'),
                    straat: form.get('straat'),
                    postcode: form.get('postcode'),
                    plaats: form.get('plaats'),
                    land: form.get('land')
                };
                let meta = {
                    verzendmethode: form.get('verzendmethode'),
                    betaalmethode: form.get('betaalmethode'),
                    bezorg_moment: form.get('bezorg_moment'),
                    bezorg_unix: form.get('bezorg_unix') || 0,
                    bezorg_slot_id: form.get('bezorg_slot_id') || 0,
                    guest: !!checkout_is_guest
                };

                // Client-side validation: require address fields for levering
                if ((meta.verzendmethode || '') === 'levering') {
                    const elStraat = document.querySelector('input[name="straat"]');
                    const elPost = document.querySelector('input[name="postcode"]');
                    const elPlaats = document.querySelector('input[name="plaats"]');

                    // clear previous error styles
                    [elStraat, elPost, elPlaats].forEach(function(el) {
                        if (el) {
                            el.style.border = '';
                            el.style.boxShadow = '';
                        }
                    });

                    const s = (form.get('straat') || '').trim();
                    const p = (form.get('postcode') || '').trim();
                    const pl = (form.get('plaats') || '').trim();
                    const invalid = [];
                    if (!s && elStraat) invalid.push(elStraat);
                    if (!p && elPost) invalid.push(elPost);
                    if (!pl && elPlaats) invalid.push(elPlaats);

                    if (invalid.length) {
                        invalid.forEach(function(el) {
                            el.style.border = '1px solid #e74c3c';
                            el.style.boxShadow = '0 0 0 3px rgba(231,76,60,0.08)';
                        });
                        // focus first invalid field and scroll into view
                        try {
                            invalid[0].focus();
                            invalid[0].scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        } catch (e) {}
                        return;
                    }
                }

                // Client-side validation: if tijdsloten are enabled, require a date+time slot
                if (window.tijdsloten_enabled) {
                    // clear previous slot error styles
                    var slotDateEl = document.getElementById('slot_date');
                    var slotTimeEl = document.getElementById('slot_time');
                    if (slotDateEl) {
                        slotDateEl.style.border = '';
                        slotDateEl.style.boxShadow = '';
                    }
                    if (slotTimeEl) {
                        slotTimeEl.style.border = '';
                        slotTimeEl.style.boxShadow = '';
                    }

                    var bezorg_unix = meta.bezorg_unix || 0;
                    var bezorg_slot_id = meta.bezorg_slot_id || 0;
                    // if no slot chosen, show visual error on selects
                    if (!bezorg_unix || !bezorg_slot_id) {
                        if (slotDateEl) {
                            slotDateEl.style.border = '1px solid #e74c3c';
                            slotDateEl.style.boxShadow = '0 0 0 3px rgba(231,76,60,0.08)';
                        }
                        if (slotTimeEl) {
                            slotTimeEl.style.border = '1px solid #e74c3c';
                            slotTimeEl.style.boxShadow = '0 0 0 3px rgba(231,76,60,0.08)';
                        }
                        try {
                            if (slotDateEl) {
                                slotDateEl.focus();
                                slotDateEl.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                            }
                        } catch (e) {}
                        return;
                    }
                }

                // include basic prijs/btw in cart items if not present
                cart = cart.map(i => ({
                    product_id: i.product_id || i.productId || i.product_id,
                    name: i.name,
                    price: i.price,
                    qty: i.qty,
                    btw: i.btw || 21,
                    options: i.options || []
                }));

                showMsg('Bestelling wordt verwerkt...');
                let res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        customer,
                        meta,
                        cart
                    })
                });
                let j = await res.json();
                if (!j.ok) {
                    // If the server says the slot is invalid, highlight the date/time selects instead of showing a message
                    var slotDateEl = document.getElementById('slot_date');
                    var slotTimeEl = document.getElementById('slot_time');
                    var err = (j.error || '').toLowerCase();
                    if (err.indexOf('kies een geldig tijdslot') !== -1 || err.indexOf('tijdslot') !== -1) {
                        if (slotDateEl) {
                            slotDateEl.style.border = '1px solid #e74c3c';
                            slotDateEl.style.boxShadow = '0 0 0 3px rgba(231,76,60,0.08)';
                        }
                        if (slotTimeEl) {
                            slotTimeEl.style.border = '1px solid #e74c3c';
                            slotTimeEl.style.boxShadow = '0 0 0 3px rgba(231,76,60,0.08)';
                        }
                        try {
                            if (slotDateEl) {
                                slotDateEl.focus();
                                slotDateEl.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                            }
                        } catch (e) {}
                        return;
                    }
                    showMsg((window.checkoutTranslations.error_prefix || 'Fout: ') + (j.error || 'Onbekend'));
                    return;
                }
                // clear cart on success
                localStorage.removeItem('cart');
                if (j.online_payment && j.payment_redirect) {
                    window.location.href = j.payment_redirect;
                } else {
                    window.location.href = '/bedankt?order=' + j.bestelling_id;
                }
            });
        </script>
    </main>
    <?php //include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-footer.php"; 
    ?>
</body>

</html>