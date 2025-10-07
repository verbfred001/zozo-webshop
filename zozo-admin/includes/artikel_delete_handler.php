<?php
// filepath: e:\meettoestel.be\zozo-admin\includes\artikel_delete_handler.php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");

$art_id = intval($_POST['art_id'] ?? 0);

if ($art_id) {
    // 1. Verwijder voorraadregels
    $mysqli->query("DELETE FROM voorraad WHERE art_id = $art_id");

    // 2. Verwijder images uit tabel en van server
    $res = $mysqli->query("SELECT image_name FROM product_images WHERE product_id = $art_id");
    while ($row = $res->fetch_assoc()) {
        $imgPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . $row['image_name'];
        if (is_file($imgPath)) {
            unlink($imgPath);
        }
    }
    $mysqli->query("DELETE FROM product_images WHERE product_id = $art_id");

    // 3. Verwijder artikel zelf
    $mysqli->query("DELETE FROM products WHERE art_id = $art_id LIMIT 1");
}

header('Location: /admin/artikelen?deleted=1');
exit;
