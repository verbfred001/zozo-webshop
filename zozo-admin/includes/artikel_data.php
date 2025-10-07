<?php
// Include centrale functies
require_once(__DIR__ . '/functions.php');


// Artikel gegevens ophalen (als niet al gedaan door update)
if (!isset($artikel)) {
    $sql = "SELECT * FROM products WHERE art_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $art_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $artikel = $result->fetch_assoc();
}

// Artikel foto's ophalen
$images = [];
if ($art_id) {
    // Check of product_images tabel bestaat
    $tableCheck = $mysqli->query("SHOW TABLES LIKE 'product_images'");
    if ($tableCheck->num_rows > 0) {
        $imagesSql = "SELECT * FROM product_images WHERE product_id = ? ORDER BY image_order ASC, id ASC";
        $imagesStmt = $mysqli->prepare($imagesSql);
        $imagesStmt->bind_param("i", $art_id);
        $imagesStmt->execute();
        $imagesResult = $imagesStmt->get_result();

        while ($img = $imagesResult->fetch_assoc()) {
            $images[] = $img;
        }
    }
}

// Debug: check wat we hebben
if (empty($images)) {
    // Maak dummy data voor nu
    $images = [];
    // Je kunt hier tijdelijk wat test data maken:
    // $images[0] = ['image_name' => 'test.jpg', 'is_primary' => true];
}

// Haal alle categorieën op voor hiërarchie
if (!isset($categories)) {
    $catResult = $mysqli->query("SELECT cat_id, cat_naam, cat_top_sub FROM category ORDER BY cat_top_sub, cat_volgorde");
    $categories = [];
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row;
    }
}
