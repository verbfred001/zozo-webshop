<?php
// Usage: include this file at the top of any page

// Detect language and slugs from URL
$uri = $_SERVER['REQUEST_URI'];
if (preg_match('#^/(nl|fr|en)(/.*)?$#', $uri, $matches)) {
    $lang = $matches[1];
    $path = $matches[2] ?? '';
} else {
    $lang = 'nl';
    $path = $uri;
}

$slugs = array_filter(explode('/', ltrim($path, '/')));

// Choose the correct slug field for the language
$slugField = 'cat_afkorting';
if ($lang === 'fr') $slugField = 'cat_afkorting_fr';
if ($lang === 'en') $slugField = 'cat_afkorting_en';

require_once $_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/DB_connectie.php';

echo "<div style='background: #ffe; border: 1px solid #ccc; padding: 8px; margin: 8px 0;'>";
echo "<br><br><br><br>taal = " . htmlspecialchars($lang) . "<br>";

$parentId = 0;
foreach ($slugs as $slug) {
    $stmt = $mysqli->prepare("SELECT cat_id FROM category WHERE $slugField = ? AND cat_top_sub = ?");
    $stmt->bind_param("si", $slug, $parentId);
    $stmt->execute();
    $stmt->bind_result($catId);
    if ($stmt->fetch()) {
        echo htmlspecialchars($slug) . ": cat_id = " . intval($catId) . "<br>";
        $parentId = $catId;
    } else {
        echo htmlspecialchars($slug) . ": cat_id = NOT FOUND<br>";
        break;
    }
    $stmt->close();
}
echo "</div>";
