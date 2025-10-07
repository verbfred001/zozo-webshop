<?php
// AJAX endpoint: return order details as JSON
header('Content-Type: application/json; charset=utf-8');
// For AJAX endpoint we don't want stray warnings/html breaking JSON
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/DB_connectie.php');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid id']);
    exit;
}

$stmt = $mysqli->prepare(
    "SELECT b.*, 
                COALESCE(k.email, '') as klant_email, 
                COALESCE(k.telefoon, '') as klant_telefoon,
                COALESCE(k.bedrijfsnaam, '') as klant_bedrijfsnaam,
                COALESCE(g.email, '') as guest_email,
                COALESCE(g.telefoon, '') as guest_telefoon,
                COALESCE(g.bedrijfsnaam, '') as guest_bedrijfsnaam
      FROM bestellingen b
      LEFT JOIN klanten k ON b.klant_id = k.klant_id
      LEFT JOIN guest_info g ON b.guest_id = g.guest_id
      WHERE b.bestelling_id = ? LIMIT 1"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();

if (!$order) {
    echo json_encode(['ok' => false, 'error' => 'Order not found']);
    exit;
}

$items = [];
$inhoud = trim($order['inhoud_bestelling'] ?? '');
if ($inhoud !== '') {
    // Try iterative JSON decode to handle double-encoded JSON strings
    $try = $inhoud;
    $decoded_final = null;
    for ($i = 0; $i < 3; $i++) {
        $tmp = json_decode($try, true);
        if (is_array($tmp)) {
            $decoded_final = $tmp;
            break;
        }
        if (is_string($tmp)) {
            $try = $tmp;
            continue;
        }
        break;
    }
    if (is_array($decoded_final)) {
        $items = $decoded_final;
    } else {
        // Try direct json_decode (non-iterative) as fallback
        $decoded = json_decode($inhoud, true);
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            // Try unserialize (older installations)
            $un = @unserialize($inhoud);
            if (is_array($un)) {
                $items = $un;
            } else {
                // As a last resort, try to parse simple line-based format (name|price|qty) or JSON-per-line
                $lines = preg_split('/\r?\n/', $inhoud);
                foreach ($lines as $ln) {
                    $ln = trim($ln);
                    if ($ln === '') continue;
                    // If line looks like JSON, try decode
                    if (($ln[0] ?? '') === '{' || ($ln[0] ?? '') === '[') {
                        $try2 = json_decode($ln, true);
                        if (is_array($try2)) {
                            // merge arrays
                            foreach ($try2 as $ti) $items[] = $ti;
                            continue;
                        }
                    }
                    // try CSV or pipe
                    if (strpos($ln, '|') !== false) {
                        $parts = array_map('trim', explode('|', $ln));
                        $items[] = [
                            'name' => $parts[0] ?? '',
                            'prijs' => $parts[1] ?? null,
                            'aantal' => $parts[2] ?? 1,
                        ];
                    } else {
                        $items[] = ['name' => $ln];
                    }
                }
            }
        }
    }
}

// Normalize items: support multiple stored formats, including the new compact schema
$norm = [];
foreach ($items as $it) {
    if (!is_array($it)) continue;
    // Detect new compact schema (omschrijving/kostprijs/aantal)
    $name = '';
    $price = null;
    $qty = 1;
    $image = '';

    if (isset($it['omschrijving'])) {
        $name = $it['omschrijving'];
        $price = isset($it['kostprijs']) ? $it['kostprijs'] : (isset($it['price']) ? $it['price'] : null);
        $qty = isset($it['aantal']) ? $it['aantal'] : (isset($it['qty']) ? $it['qty'] : 1);
        $image = $it['afbeelding'] ?? '';
    } else {
        // older formats
        $name = $it['name'] ?? ($it['product_naam'] ?? ($it['title'] ?? ''));
        $price = isset($it['prijs']) ? $it['prijs'] : (isset($it['price']) ? $it['price'] : null);
        $qty = $it['aantal'] ?? $it['qty'] ?? $it['quantity'] ?? 1;
        $image = $it['afbeelding'] ?? ($it['image'] ?? '');
    }

    // If afbeelding is a filename, convert to path (best effort)
    if (!empty($image) && strpos($image, '/') === false) {
        $image = '/upload/' . ltrim($image, '/');
    }

    $norm[] = [
        'name' => $name,
        'prijs' => $price,
        'aantal' => $qty,
        'image' => $image,
        'raw' => $it,
    ];
}

// Return select order fields and items
$order_out = $order;
// hide large raw inhoud to keep response compact
unset($order_out['inhoud_bestelling']);

// If klant_email empty but guest_email present, prefer guest_email
if (empty($order_out['klant_email']) && !empty($order_out['guest_email'])) {
    $order_out['klant_email'] = $order_out['guest_email'];
}
// Prefer klant_telefoon (from klanten) otherwise guest_telefoon
if (empty($order_out['klant_telefoon']) && !empty($order_out['guest_telefoon'])) {
    $order_out['klant_telefoon'] = $order_out['guest_telefoon'];
}
// Prefer bedrijfsnaam from klanten otherwise from guest_info
if (empty($order_out['klant_bedrijfsnaam']) && !empty($order_out['guest_bedrijfsnaam'])) {
    $order_out['klant_bedrijfsnaam'] = $order_out['guest_bedrijfsnaam'];
}

// If the order references a timeslot id, fetch the start/end times so the
// admin modal can display the interval (e.g. "09u00 - 09u30").
$slotId = isset($order['bezorg_slot_id']) ? (int)$order['bezorg_slot_id'] : 0;
if ($slotId > 0) {
    $s2 = $mysqli->prepare("SELECT start_time, end_time FROM timeslot_fixed_ranges WHERE id = ? LIMIT 1");
    if ($s2) {
        $s2->bind_param('i', $slotId);
        $s2->execute();
        $res2 = $s2->get_result();
        $ts = $res2 ? $res2->fetch_assoc() : null;
        if ($ts && !empty($ts['start_time'])) {
            $order_out['slot_start_time'] = $ts['start_time'];
            $order_out['slot_end_time'] = $ts['end_time'] ?? '';
            // Build a simple human-friendly label using 'u' as hour separator: 09u00 - 09u30
            $startTs = strtotime($ts['start_time']);
            $endTs = !empty($ts['end_time']) ? strtotime($ts['end_time']) : null;
            if ($startTs !== false && $endTs !== false) {
                $order_out['slot_label'] = date('H', $startTs) . 'u' . date('i', $startTs) . ' - ' . date('H', $endTs) . 'u' . date('i', $endTs);
            } else {
                // fallback: raw values
                $order_out['slot_label'] = trim(($ts['start_time'] ?? '') . ' - ' . ($ts['end_time'] ?? ''));
            }
        }
        $s2->close();
    }
}

// If no slot id but a UNIX_bezorgmoment exists, try to infer the timeslot row and label
$slotUnix = isset($order['UNIX_bezorgmoment']) ? (int)$order['UNIX_bezorgmoment'] : 0;
if (($slotId <= 0) && $slotUnix > 0) {
    $dayOfWeek = (int)date('N', $slotUnix); // 1 (Mon) - 7 (Sun)
    $timeStr = date('H:i:s', $slotUnix);
    $q = $mysqli->prepare("SELECT id, start_time, end_time FROM timeslot_fixed_ranges WHERE day_of_week = ? AND start_time <= ? AND end_time > ? ORDER BY start_time LIMIT 1");
    if ($q) {
        $q->bind_param('iss', $dayOfWeek, $timeStr, $timeStr);
        $q->execute();
        $resq = $q->get_result();
        $trow = $resq ? $resq->fetch_assoc() : null;
        if ($trow && !empty($trow['start_time'])) {
            $order_out['slot_start_time'] = $trow['start_time'];
            $order_out['slot_end_time'] = $trow['end_time'] ?? '';
            $startTs = strtotime($trow['start_time']);
            $endTs = !empty($trow['end_time']) ? strtotime($trow['end_time']) : false;
            if ($startTs !== false && $endTs !== false) {
                $order_out['slot_label'] = date('H', $startTs) . 'u' . date('i', $startTs) . ' - ' . date('H', $endTs) . 'u' . date('i', $endTs);
            } else {
                $order_out['slot_label'] = trim(($trow['start_time'] ?? '') . ' - ' . ($trow['end_time'] ?? ''));
            }
        }
        $q->close();
    }
}

// Clear any accidental buffered output (warnings/html) and return clean JSON
@ob_end_clean();
echo json_encode(['ok' => true, 'order' => $order_out, 'items' => $norm]);

exit;
