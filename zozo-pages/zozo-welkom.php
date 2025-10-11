<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);


include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/zozo-vertalingen.php";
include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php";

$langs = ['nl' => 'Nederlands', 'fr' => 'Français', 'en' => 'English'];


// Haal de branded URLs uit de database
$res = $mysqli->query("SELECT url_welkom, url_welkom_fr, url_welkom_en FROM instellingen LIMIT 1");
$row = $res ? $res->fetch_assoc() : [];
$url_nl = $row['url_welkom'] ?? '';
$url_fr = $row['url_welkom_fr'] ?? '';
$url_en = $row['url_welkom_en'] ?? '';

// Detecteer taal op basis van de branded URL in de request
if (!isset($lang)) {
    $req = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($req === $url_fr) {
        $lang = 'fr';
    } elseif ($req === $url_en) {
        $lang = 'en';
    } else {
        $lang = 'nl';
    }
}

// Haal de juiste URL op basis van de gedetecteerde taal
$url_welkom = '';
if ($lang === 'fr') {
    $url_welkom = $url_fr;
} elseif ($lang === 'en') {
    $url_welkom = $url_en;
} else {
    $url_welkom = $url_nl;
}

// Categorieën en helpers beschikbaar maken
include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/zozo-categories.php";
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Welkom</title>
    <link rel="stylesheet" href="/zozo-assets/css/zozo-main.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-navbar.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-topbar.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-welkom.css">
    <script src="/zozo-assets/js/zozo-product-shared.js" defer></script>
    <?php
    $host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/^www\./', '', $_SERVER['HTTP_HOST']) : 'example.com';
    $pref = 'https://' . $host . '/' . $url_welkom;
    ?>
    <link rel="canonical" href="<?= htmlspecialchars($pref) ?>">
</head>

<body>
    <?php
    include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-topbar.php";
    include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-navbar.php";
    ?>

    <!-- (Hero verwijderd — afbeelding is verplaatst naar de footer) -->

    <?php include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-breadcrumbs.php"; ?>

    <main class="welkom-main">
        <?php
        include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/welkom/wie_zijn_wij.php";
        ?>
    </main>



    <?php include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-footer.php";
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            updateCartBadge && updateCartBadge();
        });
    </script>
</body>

</html>