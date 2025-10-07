<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");

// Functie: eerste letter hoofdletter, rest kleine letters (multibyte)
function ucfirst_mb($str)
{
    $str = trim($str);
    if ($str === '') return '';
    return mb_strtoupper(mb_substr($str, 0, 1)) . mb_strtolower(mb_substr($str, 1));
}

$cat_id = intval($_POST['cat_id'] ?? 0);
$cat_top_sub = intval($_POST['cat_top_sub'] ?? 0);

// Pas hier de casing toe
$cat_naam = ucfirst_mb($_POST['cat_naam'] ?? '');
$cat_naam_fr = ucfirst_mb($_POST['cat_naam_fr'] ?? '');
$cat_naam_en = ucfirst_mb($_POST['cat_naam_en'] ?? '');
$cat_afkorting = ucfirst_mb($_POST['cat_afkorting'] ?? '');
$cat_afkorting_fr = ucfirst_mb($_POST['cat_afkorting_fr'] ?? '');
$cat_afkorting_en = ucfirst_mb($_POST['cat_afkorting_en'] ?? '');
$verborgen = $_POST['verborgen'] ?? 0;
$cat_volgorde = intval($_POST['cat_volgorde'] ?? 999);

// Vul FR en EN aan met NL indien leeg
if ($cat_naam_fr === '') $cat_naam_fr = $cat_naam;
if ($cat_naam_en === '') $cat_naam_en = $cat_naam;
if ($cat_afkorting_fr === '') $cat_afkorting_fr = $cat_afkorting;
if ($cat_afkorting_en === '') $cat_afkorting_en = $cat_afkorting;

if ($cat_id > 0) {
    // Update bestaande categorie
    $stmt = $mysqli->prepare("UPDATE category SET cat_top_sub=?, cat_naam=?, cat_naam_fr=?, cat_naam_en=?, cat_afkorting=?, cat_afkorting_fr=?, cat_afkorting_en=?, verborgen=?, cat_volgorde=? WHERE cat_id=?");
    $stmt->bind_param("issssssiii", $cat_top_sub, $cat_naam, $cat_naam_fr, $cat_naam_en, $cat_afkorting, $cat_afkorting_fr, $cat_afkorting_en, $verborgen, $cat_volgorde, $cat_id);
    $stmt->execute();
    $stmt->close();
} else {
    // Nieuwe categorie toevoegen
    $stmt = $mysqli->prepare("INSERT INTO category (cat_top_sub, cat_naam, cat_naam_fr, cat_naam_en, cat_afkorting, cat_afkorting_fr, cat_afkorting_en, verborgen, cat_volgorde) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssii", $cat_top_sub, $cat_naam, $cat_naam_fr, $cat_naam_en, $cat_afkorting, $cat_afkorting_fr, $cat_afkorting_en, $verborgen, $cat_volgorde);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['status' => 'success']);
