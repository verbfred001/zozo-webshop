<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php";
include_once $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/get_cat_ids.php";
include_once $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/functions.php";

// Language labels
$langLabels = [
    'nl' => 'NL',
    'fr' => 'FR',
    'en' => 'ENG'
];

// Show the selector if there are extra languages
$showLangSelector = isset($talen) && count($talen) > 0;

// --- Language detection ---
$uri = $_SERVER['REQUEST_URI'];
if (preg_match('#^/(nl|fr|en)(/.*)?$#', $uri, $matches)) {
    $currentLang = $matches[1];
    $currentPath = $matches[2] ?? '';
} else {
    $currentLang = 'nl';
    $currentPath = $uri;
}

// --- Get all category IDs in the path ---
$slugs = array_filter(explode('/', ltrim($currentPath, '/')));
$cat_ids = [];
$parentId = 0;
$slugField = 'cat_afkorting';
if ($currentLang === 'fr') $slugField = 'cat_afkorting_fr';
if ($currentLang === 'en') $slugField = 'cat_afkorting_en';
foreach ($slugs as $slug) {
    $stmt = $mysqli->prepare("SELECT cat_id FROM category WHERE $slugField = ? AND cat_top_sub = ?");
    $stmt->bind_param("si", $slug, $parentId);
    $stmt->execute();
    $stmt->bind_result($catId);
    if ($stmt->fetch()) {
        $cat_ids[] = $catId;
        $parentId = $catId;
    } else {
        break;
    }
    $stmt->close();
}

echo cat_translate($mysqli, 320, "fr");

// --- Build language switch URLs ---
if (!isset($langUrls)) $langUrls = [];
foreach (['nl', 'fr', 'en'] as $lang) {
    $slugParts = [];
    foreach ($cat_ids as $cat_id) {
        $slug = cat_translate($mysqli, $cat_id, $lang);
        if ($slug) $slugParts[] = $slug;
    }
    $langUrls[$lang] = '/' . $lang . ($slugParts ? '/' . implode('/', $slugParts) : '');
}

// Set current for selector
$current = $currentLang;

?>

<div class="topnav">
    <div class="topnav-container">
        <a href="/<?= $current ?>/contact" class="topnav-link">
            <i class="fas fa-envelope"></i> Contact
        </a>
        <?php if ($showLangSelector): ?>
            <div style="display:inline-block;position:relative;margin-left:10px;">
                <!-- Button shows current language -->
                <button id="welkom-lang-btn" style="background:none;border:none;color:#fff;font:inherit;cursor:pointer;display:flex;align-items:center;">
                    <?= $langLabels[$current]; ?>
                    <svg style="margin-left:5px;" width="12" height="8" viewBox="0 0 12 8">
                        <path d="M1 1l5 5 5-5" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" />
                    </svg>
                </button>
                <!-- Dropdown always shows all languages -->
                <div id="welkom-lang-dropdown" style="display:none;position:absolute;left:0;top:100%;background:#222;border-radius:4px;box-shadow:0 2px 8px #0002;z-index:10;">
                    <?php foreach (['nl', 'fr', 'en'] as $code): ?>
                        <a href="<?= isset($langUrls[$code]) ? $langUrls[$code] : '#' ?>" style="display:block;padding:7px 18px;color:#fff;text-decoration:none;">
                            <?= $langLabels[$code] ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <script>
                const btn = document.getElementById('welkom-lang-btn');
                const dd = document.getElementById('welkom-lang-dropdown');
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dd.style.display = dd.style.display === 'block' ? 'none' : 'block';
                });
                document.addEventListener('click', function() {
                    dd.style.display = 'none';
                });
            </script>
        <?php endif; ?>
        <a href="/login" class="topnav-link">
            <i class="fas fa-user"></i> <?= function_exists('t') ? t('login') : 'Login' ?>
        </a>
        <a href="/cart" class="topnav-link">
            <i class="fas fa-shopping-cart"></i> <?= function_exists('t') ? t('cart') : 'Cart' ?>
        </a>
    </div>
</div>