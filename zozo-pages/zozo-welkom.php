<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/zozo-vertalingen.php";

$langs = ['nl' => 'Nederlands', 'fr' => 'Français', 'en' => 'English'];

// Detecteer taal uit URL of standaard naar 'nl'
if (!isset($lang)) {
    if (preg_match('#^/(nl|fr|en)(/|$)#', $_SERVER['REQUEST_URI'], $m)) {
        $lang = $m[1];
    } elseif ($_SERVER['REQUEST_URI'] === '/bienvenue') {
        $lang = 'fr';
    } elseif ($_SERVER['REQUEST_URI'] === '/welcome') {
        $lang = 'en';
    } else {
        $lang = 'nl';
    }
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
    $pref = 'https://' . $host . '/broodjeszaak-take-away-eethuis-roeselare';
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