<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Language labels and welcome URLs
$langLabels = [
    'nl' => 'NL',
    'fr' => 'FR',
    'en' => 'ENG'
];

// Haal de branded URLs uit de database (indien nog niet beschikbaar)
if (!isset($url_nl) || !isset($url_fr) || !isset($url_en)) {
    require_once $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php";
    $res = $mysqli->query("SELECT url_welkom, url_welkom_fr, url_welkom_en FROM instellingen LIMIT 1");
    $row = $res ? $res->fetch_assoc() : [];
    $url_nl = $row['url_welkom'] ?? 'welkom';
    $url_fr = $row['url_welkom_fr'] ?? 'bienvenue';
    $url_en = $row['url_welkom_en'] ?? 'welcome';
}
$welkoms = [
    'nl' => ['url' => '/' . $url_nl, 'label' => $langLabels['nl']],
    'fr' => ['url' => '/' . $url_fr, 'label' => $langLabels['fr']],
    'en' => ['url' => '/' . $url_en, 'label' => $langLabels['en']],
];

// Detect current language from URL using activelanguage() (reads branded slugs from DB)
$current = function_exists('activelanguage') ? activelanguage() : 'nl';

// Show the selector if there are extra languages (set $talen in config)
$showLangSelector = isset($talen) && count($talen) > 0;
?>

<div class="topnav">
    <div class="topnav-container">
        <a href="/<?= $current ?>/contact" class="topnav-link">
            <i class="fas fa-envelope"></i> Contact
        </a>
        <?php if ($showLangSelector): ?>
            <div style="display:inline-block;position:relative;margin-left:10px;">
                <button id="welkom-lang-btn" style="background:none;border:none;color:#fff;font:inherit;cursor:pointer;display:flex;align-items:center;">
                    <?= $welkoms[$current]['label']; ?>
                    <svg style="margin-left:5px;" width="12" height="8" viewBox="0 0 12 8">
                        <path d="M1 1l5 5 5-5" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" />
                    </svg>
                </button>
                <div id="welkom-lang-dropdown" style="display:none;position:absolute;left:0;top:100%;background:#222;border-radius:4px;box-shadow:0 2px 8px #0002;z-index:10;">
                    <?php foreach ($welkoms as $code => $info): ?>
                        <a href="<?= $info['url'] ?>" style="display:block;padding:7px 18px;color:#fff;text-decoration:none;">
                            <?= $info['label'] ?>
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