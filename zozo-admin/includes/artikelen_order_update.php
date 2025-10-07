<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (empty($data) || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$order = $data['order'] ?? null;
$category = isset($data['category']) ? (is_null($data['category']) ? null : intval($data['category'])) : null;

if (!is_array($order)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing order array']);
    exit;
}

// Begin transaction
$mysqli->begin_transaction();
try {
    // Prepared statement for update
    $stmt = $mysqli->prepare("UPDATE products SET art_order = ? WHERE art_id = ?");
    if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);

    if (is_null($category)) {
        // No category context: update provided ids with given positions
        foreach ($order as $pos => $art_id) {
            $art_id = intval($art_id);
            $pos = intval($pos);
            $stmt->bind_param('ii', $pos, $art_id);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
        }
    } else {
        // Category provided: compute a full ordering for this category.
        // Step 1: fetch all art_ids in this category ordered by current art_order to preserve relative order
        $resAll = $mysqli->query("SELECT art_id FROM products WHERE art_catID = $category ORDER BY art_order ASC, art_id ASC");
        $allIds = [];
        while ($r = $resAll->fetch_assoc()) $allIds[] = intval($r['art_id']);

        // Step 2: build new ordered list: first the posted ids (in same order), then the remaining ids preserving previous order
        $posted = array_map('intval', $order);
        $postedSet = array_flip($posted);
        $newOrder = [];
        foreach ($posted as $pid) {
            if (in_array($pid, $allIds, true)) $newOrder[] = $pid;
        }
        foreach ($allIds as $aid) {
            if (!isset($postedSet[$aid])) $newOrder[] = $aid;
        }

        // Step 3: apply the new ordering (positions 0..n-1)
        foreach ($newOrder as $pos => $art_id) {
            $art_id = intval($art_id);
            $pos = intval($pos);
            $stmt->bind_param('ii', $pos, $art_id);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
        }
    }

    $mysqli->commit();
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
