<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/config.php");  // TOEGEVOEGD

// Controleer of admin is ingelogd
if (!isset($_SESSION['admin_logged_in'])) {
    //   header('Location: login.php');
    //   exit;
}

// Ophalen van alle categorieën en subcategorieën
$sql = "SELECT * FROM category ORDER BY cat_top_sub ASC, cat_volgorde ASC, cat_naam ASC";
$result = $mysqli->query($sql);

// Bouw een boomstructuur
$categories = [];
$lookup = [];

// Eerst alle categorieën in een lookup array
while ($row = $result->fetch_assoc()) {
    $row['subs'] = [];
    $lookup[$row['cat_id']] = $row;
}

// Bouw de boomstructuur
foreach ($lookup as $id => &$cat) {
    if ($cat['cat_top_sub'] == 0) {
        $categories[$id] = &$cat;
    } else {
        if (isset($lookup[$cat['cat_top_sub']])) {
            $lookup[$cat['cat_top_sub']]['subs'][] = &$cat;
        }
    }
}
unset($cat); // break reference

// In categorieen.php
$talen = ['nl'];
$res = $mysqli->query("SELECT talen_fr, talen_en FROM instellingen LIMIT 1");
if ($row = $res->fetch_assoc()) {
    if ($row['talen_fr']) $talen[] = 'fr';
    if ($row['talen_en']) $talen[] = 'en';
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorieën beheren</title>
    <link rel="stylesheet" href="/zozo-admin/css/navbar.css">
    <link rel="stylesheet" href="/zozo-admin/css/main.css">
    <link rel="stylesheet" href="/zozo-admin/css/categorieen.css">
</head>

<body class="page-bg">
    <?php include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-admin/templates/navbar.php'); ?>

    <main class="main-container">
        <div class="main-card">
            <div class="main-card-header">
                <h1 class="main-title">Categorieën beheren</h1>
                <button onclick="showAddForm(0)" class="btn btn--add">
                    + Hoofdcategorie
                </button>
            </div>
            <?php include('templates/categorie_tree.php'); ?>
        </div>
        <?php include('templates/categorie_modal.php'); ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        window.activeLanguages = <?= json_encode($talen) ?>;
    </script>
    <script src="/zozo-admin/js/categorieen.js">
    </script>

</body>

</html>