<?php

require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");
$data = json_decode(file_get_contents('php://input'), true);

if (!empty($data['order'])) {
    foreach ($data['order'] as $item) {
        $id = intval($item['id']);
        $volgorde = intval($item['volgorde']);
        $stmt = $mysqli->prepare("UPDATE option_groups SET sort_order = ? WHERE group_id = ?");
        $stmt->bind_param("ii", $volgorde, $id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'No order data']);
}