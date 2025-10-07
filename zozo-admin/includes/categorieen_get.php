<?php
// filepath: e:\maxice.be\zozo-admin\includes\categorieen_get.php
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $mysqli->prepare("SELECT * FROM category WHERE cat_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cat = $result->fetch_assoc();
    $stmt->close();

    if ($cat) {
        header('Content-Type: application/json');
        echo json_encode($cat);
        exit;
    }
}

http_response_code(404);
echo json_encode(['error' => 'Categorie niet gevonden']);
