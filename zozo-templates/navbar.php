<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/functions.php'; // for activelanguage()

// Categorieën ophalen (nu ook verborgen als integer 0)
$sql = "SELECT cat_id, cat_naam, cat_naam_fr, cat_naam_en, cat_afkorting, cat_afkorting_fr, cat_afkorting_en, cat_top_sub, cat_volgorde FROM category WHERE verborgen = 0 ORDER BY cat_volgorde, cat_top_sub, cat_naam ASC";
$result = $mysqli->query($sql);

$categories = [];
while ($row = $result->fetch_assoc()) {
  $categories[] = $row;
}

// Bouw een boomstructuur
function buildCategoryTree($elements, $parentId = 0)
{
  $branch = [];
  foreach ($elements as $element) {
    if ((int)$element['cat_top_sub'] === (int)$parentId) {
      $children = buildCategoryTree($elements, $element['cat_id']);
      if ($children) {
        $element['subs'] = $children;
      } else {
        $element['subs'] = [];
      }
      $branch[] = $element;
    }
  }
  return $branch;
}

$catTree = buildCategoryTree($categories);

$activeLang = activelanguage();

// Recursieve functie om de navigatie te tonen
function printNav($cats, $depth = 0, $parentPath = [], $activeLang = 'nl')
{
  // Choose correct fields for name and slug
  $nameField = 'cat_naam';
  $slugField = 'cat_afkorting';
  if ($activeLang === 'fr') {
    $nameField = 'cat_naam_fr';
    $slugField = 'cat_afkorting_fr';
  } elseif ($activeLang === 'en') {
    $nameField = 'cat_naam_en';
    $slugField = 'cat_afkorting_en';
  }

  foreach ($cats as $cat) {
    $hasSubs = !empty($cat['subs']);
    // Build the path for this category
    $currentPath = array_merge($parentPath, [urlencode($cat[$slugField])]);
    // Add dash before subsubcategories (depth >= 2)
    $prefix = ($depth >= 2) ? '<span style="margin-right:4px;">-</span>' : '';
    echo '<li class="nav-item' . ($hasSubs ? ' has-dropdown' : '') . '">';
    if ($hasSubs) {
      echo '<span class="nav-link">' . $prefix . htmlspecialchars($cat[$nameField]) . ' <span class="arrow" style="font-size:0.9em;">&#9660;</span></span>';
      echo '<ul class="dropdown">';
      printNav($cat['subs'], $depth + 1, $currentPath, $activeLang);
      echo '</ul>';
    } else {
      // Build the full URL path
      $url = '/' . $activeLang . '/' . implode('/', $currentPath);
      echo '<a href="' . $url . '" class="nav-link">' . $prefix . htmlspecialchars($cat[$nameField]) . '</a>';
    }
    echo '</li>';
  }
}

if ($pageType === 'default') {
  include_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/topnav_home.php");
} elseif ($pageType === 'products') {
  include_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/topnav_products.php");
} elseif ($pageType === 'detail_product') {
  // You can include something here if needed
} else {
  include_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/topnav_products.php");
}

?>
<nav class="navbar">
  <div class="navbar-container">
    <a href="/" class="logo">
      <img src="/zozo-assets/img/LOGO_zozo.webp" alt="Zozo logo" style="height:55px;vertical-align:middle;">
    </a>
    <div class="menu-toggle" id="mobile-menu">
      <span></span><span></span><span></span>
    </div>
    <ul class="nav-links">
      <?php printNav($catTree, 0, [], $activeLang); ?>
    </ul>
  </div>
</nav>