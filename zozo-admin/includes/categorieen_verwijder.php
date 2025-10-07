<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");

$cat_id = intval($_POST['id'] ?? 0);

function deleteCategoryRecursive($catId, $mysqli)
{
    $catId = (int)$catId;
    $result = $mysqli->query("SELECT cat_id FROM category WHERE cat_top_sub = $catId");
    while ($row = $result->fetch_assoc()) {
        deleteCategoryRecursive($row['cat_id'], $mysqli);
    }
    $stmt = $mysqli->prepare("DELETE FROM category WHERE cat_id = ?");
    $stmt->bind_param("i", $catId);
    $stmt->execute();
    $stmt->close();
}

if ($cat_id > 0) {
    deleteCategoryRecursive($cat_id, $mysqli);
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Geen geldig ID']);
}
