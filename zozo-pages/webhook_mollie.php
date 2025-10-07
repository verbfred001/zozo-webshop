<?php
// Mollie webhook endpoint
// Accepts Mollie's POST webhook (form-encoded with 'id' parameter) or JSON payloads from custom clients.
// Fetches the payment via Mollie SDK to verify status and updates the corresponding bestelling row.
include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php";
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

// Mollie sends form-encoded POST with 'id' param. Also accept 'mollie_id' or 'payment_id' in JSON.
$mollie_id = $_POST['id'] ?? $data['mollie_id'] ?? $data['payment_id'] ?? null;
$incoming_status = $data['status'] ?? null; // fallback only

if (!$mollie_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing mollie_id']);
    exit;
}

// Try to fetch Mollie API key from instellingen
$kq = $mysqli->query("SELECT Mollie_API_key FROM instellingen LIMIT 1");
$krow = $kq ? $kq->fetch_assoc() : null;
$mollieKey = $krow['Mollie_API_key'] ?? '';

$status = null;
$metadata_order_id = null;

if (!empty($mollieKey)) {
    // Use Mollie SDK to retrieve payment status for a reliable result
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey($mollieKey);
        $payment = $mollie->payments->get($mollie_id);
        // Mollie API uses 'status' on the payment object
        $status = isset($payment->status) ? (string)$payment->status : null;
        if (isset($payment->metadata) && is_array((array)$payment->metadata)) {
            $metadata = (array)$payment->metadata;
            if (isset($metadata['order_id'])) $metadata_order_id = (int)$metadata['order_id'];
        }
    } catch (Exception $ex) {
        // If Mollie API call fails, fall back to incoming status if available
        error_log('webhook_mollie: Mollie API get failed for id=' . $mollie_id . ' error=' . $ex->getMessage());
        $status = $incoming_status;
    }
} else {
    // No API key configured - try to act on incoming payload (less reliable)
    $status = $incoming_status;
}

// Lookup bestelling by Mollie_betaal_id first. If not found and metadata order_id available, try that.
$stmt = $mysqli->prepare("SELECT bestelling_id FROM bestellingen WHERE Mollie_betaal_id = ? LIMIT 1");
$stmt->bind_param('s', $mollie_id);
$stmt->execute();
$res = $stmt->get_result();
$bid = null;
if ($row = $res->fetch_assoc()) {
    $bid = (int)$row['bestelling_id'];
} elseif ($metadata_order_id) {
    $bid = (int)$metadata_order_id;
}

if (!$bid) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'order not found']);
    exit;
}

// Normalize status checks
if ($status === 'paid' || $status === 'paidout' || $status === 'paid_out') {
    // mark order as paid: set status, VOLDAAN and reeds_betaald for online payments
    $u = $mysqli->prepare("UPDATE bestellingen SET STATUS_BESTELLING = ?, VOLDAAN = ?, reeds_betaald = ? WHERE bestelling_id = ?");
    $s = 'betaald';
    $v = 'ja';
    $rb = 'ja-online';
    $u->bind_param('sssi', $s, $v, $rb, $bid);
    $u->execute();
    echo json_encode(['ok' => true, 'updated' => $bid, 'status' => $status]);
    exit;
} else {
    // For other statuses we can optionally map or just record the received status
    // Example: cancelled/expired/failed -> set STATUS_BESTELLING accordingly
    $map = [
        'cancelled' => 'geannuleerd',
        'canceled' => 'geannuleerd',
        'failed' => 'betaal_mislukt',
        'expired' => 'betaal_verlopen',
        'open' => 'wacht_op_betaling'
    ];
    $new_status = $map[$status] ?? ($incoming_status ? $incoming_status : null);
    if ($new_status) {
        $u = $mysqli->prepare("UPDATE bestellingen SET STATUS_BESTELLING = ? WHERE bestelling_id = ?");
        $u->bind_param('si', $new_status, $bid);
        $u->execute();
        echo json_encode(['ok' => true, 'updated' => $bid, 'status' => $status, 'mapped' => $new_status]);
        exit;
    }

    // Nothing to change
    echo json_encode(['ok' => true, 'found' => $bid, 'status' => $status]);
    exit;
}
