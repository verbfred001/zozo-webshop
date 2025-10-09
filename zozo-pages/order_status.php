<?php
// Simple JSON endpoint to return the current order row for a given order id
// Usage: /zozo-pages/order_status.php?order=2907
include_once __DIR__ . '/../zozo-includes/DB_connectie.php';
header('Content-Type: application/json; charset=utf-8');
$order = (int)($_GET['order'] ?? 0);
if (!$order) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_order']);
    exit;
}

$q = $mysqli->prepare('SELECT b.bestelling_id, b.levernaam, b.leverplaats, b.leverstraat, b.UNIX_bezorgmoment, b.verzendmethode, b.inhoud_bestelling, b.bestelling_tebetalen, b.reeds_betaald, b.VOLDAAN, k.email FROM bestellingen b LEFT JOIN klanten k ON b.klant_id = k.klant_id WHERE b.bestelling_id = ? LIMIT 1');
if (!$q) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
}
$q->bind_param('i', $order);
$q->execute();
$res = $q->get_result();
$row = $res ? $res->fetch_assoc() : null;

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

// Return the raw row as JSON
echo json_encode(['ok' => true, 'order' => $row], JSON_UNESCAPED_UNICODE);
exit;
