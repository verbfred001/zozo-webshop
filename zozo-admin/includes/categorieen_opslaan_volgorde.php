<?php
// filepath: /zozo-admin/categorieen_opslaan_volgorde.php
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");

// Haal de JSON-data op
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['order']) || !is_array($data['order'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Ongeldige data']);
    exit;
}

foreach ($data['order'] as $item) {
    $cat_id = intval($item['id']);
    $volgorde = intval($item['volgorde']);
    $stmt = $mysqli->prepare("UPDATE category SET cat_volgorde = ? WHERE cat_id = ?");
    $stmt->bind_param("ii", $volgorde, $cat_id);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['status' => 'success']);
