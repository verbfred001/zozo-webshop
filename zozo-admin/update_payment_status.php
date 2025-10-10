<?php
// AJAX endpoint: update payment status to 'ja' for a given order ID
header('Content-Type: application/json; charset=utf-8');
// Prevent any redirects or caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// For AJAX endpoint we don't want stray warnings/html breaking JSON
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
session_start();

// Check if this is an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

require_once(__DIR__ . '/../zozo-includes/DB_connectie.php');

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid id']);
    @ob_end_clean();
    exit;
}

// First, get the current payment status
$stmt = $mysqli->prepare("SELECT reeds_betaald FROM bestellingen WHERE bestelling_id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Database error']);
    @ob_end_clean();
    exit;
}
$stmt->bind_param('i', $id);
$success = $stmt->execute();
if (!$success) {
    echo json_encode(['ok' => false, 'error' => 'Execute error']);
    @ob_end_clean();
    exit;
}
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['ok' => false, 'error' => 'Order not found']);
    @ob_end_clean();
    exit;
}

$currentBetaald = $order['reeds_betaald'];
$newBetaald = '';

if ($currentBetaald === 'nee-cash') {
    $newBetaald = 'ja-cash';
} elseif ($currentBetaald === 'nee-online') {
    $newBetaald = 'ja-online';
} else {
    $newBetaald = $currentBetaald;
}

// Update both reeds_betaald and VOLDAAN
$stmt = $mysqli->prepare("UPDATE bestellingen SET reeds_betaald = ?, VOLDAAN = 'ja' WHERE bestelling_id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Database error']);
    @ob_end_clean();
    exit;
}
$stmt->bind_param('si', $newBetaald, $id);
$success = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($success && $affected > 0) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Update failed']);
}

@ob_end_clean();
exit;
