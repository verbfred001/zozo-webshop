<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");
$data = json_decode(file_get_contents('php://input'), true);

if (!empty($data['order']) && isset($data['group_id'])) {
    foreach ($data['order'] as $item) {
        $id = intval($item['id']);
        $volgorde = intval($item['sort_order']);
        $stmt = $mysqli->prepare("UPDATE options SET sort_order = ? WHERE option_id = ? AND group_id = ?");
        $stmt->bind_param("iii", $volgorde, $id, $data['group_id']);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'No order data']);
}
