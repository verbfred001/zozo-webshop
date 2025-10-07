<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");

$id = intval($_GET['id'] ?? 0);
header('Content-Type: application/json; charset=utf-8');
if ($id <= 0) {
    echo json_encode(['image' => null]);
    exit;
}

$stmt = $mysqli->prepare("SELECT image_name FROM product_images WHERE product_id = ? ORDER BY image_order ASC, id ASC LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
if ($row && !empty($row['image_name'])) {
    // Return a web-root relative path
    $img = '/upload/' . $row['image_name'];
    echo json_encode(['image' => $img]);
} else {
    echo json_encode(['image' => null]);
}
exit;
