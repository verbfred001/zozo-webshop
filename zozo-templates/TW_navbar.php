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
    $nameField = 'cat_naam';
    $slugField = 'cat_afkorting';
    if ($activeLang === 'fr') {
        $nameField = 'cat_naam_fr';
        $slugField = 'cat_afkorting_fr';
    } elseif ($activeLang === 'en') {
        $nameField = 'cat_naam_en';
        $slugField = 'cat_afkorting_en';
    }

    // Inspringing en kleur per diepte
    $indentClass = '';
    $bgClass = 'bg-gray-900';
    if ($depth === 1) {
        $indentClass = 'pl-4';
        $bgClass = 'bg-gray-800';
    }
    if ($depth === 2) {
        $indentClass = 'pl-8';
        $bgClass = 'bg-gray-700';
    }
    if ($depth >= 3) {
        $indentClass = 'pl-12';
        $bgClass = 'bg-gray-600';
    }

    foreach ($cats as $cat) {
        $hasSubs = !empty($cat['subs']);
        $currentPath = array_merge($parentPath, [urlencode($cat[$slugField])]);
        if ($hasSubs) {
            $dropdownId = 'cat-dropdown-' . $cat['cat_id'];
            $dropdownPosition = ($depth > 0) ? 'right-0' : 'left-0';
            echo '<li class="relative cat-dropdown">';
            echo '<button type="button" data-dropdown-id="' . $dropdownId . '" class="cat-dropdown-btn cursor-pointer px-3 py-2 rounded hover:bg-gray-700 flex items-center focus:outline-none ' . $indentClass . '">' . htmlspecialchars($cat[$nameField]) . ' <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg></button>';
            echo '<ul id="' . $dropdownId . '" class="cat-dropdown-menu absolute ' . $dropdownPosition . ' mt-2 ' . $bgClass . ' rounded shadow-lg min-w-[180px] max-w-xs z-20 hidden">';
            printNav($cat['subs'], $depth + 1, $currentPath, $activeLang);
            echo '</ul>';
            echo '</li>';
        } else {
            $url = '/' . $activeLang . '/' . implode('/', $currentPath);
            echo '<li><a href="' . $url . '" class="block px-3 py-2 rounded hover:bg-gray-700 transition ' . $indentClass . '">' . htmlspecialchars($cat[$nameField]) . '</a></li>';
        }
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
<nav class="bg-gray-800 text-white shadow">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <!-- Logo -->
        <a href="/" class="flex items-center space-x-2">
            <img src="/zozo-assets/img/LOGO_zozo.webp" alt="Zozo logo" class="h-12" />
        </a>
        <!-- Hamburger menu (mobiel) -->
        <button class="sm:hidden flex flex-col space-y-1" id="mobile-menu-btn" aria-label="Menu">
            <span class="w-6 h-0.5 bg-white"></span>
            <span class="w-6 h-0.5 bg-white"></span>
            <span class="w-6 h-0.5 bg-white"></span>
        </button>
        <!-- Categorieën -->
        <ul class="hidden sm:flex space-x-4 ml-8">
            <?php printNav($catTree, 0, [], $activeLang); ?>
        </ul>
    </div>
    <!-- Mobiele menu -->
    <div id="mobile-menu" class="sm:hidden hidden px-4 pb-4">
        <ul class="flex flex-col space-y-2">
            <?php printNav($catTree, 0, [], $activeLang); ?>
        </ul>
    </div>
</nav>
<script>
    document.getElementById('mobile-menu-btn').onclick = function() {
        var menu = document.getElementById('mobile-menu');
        menu.classList.toggle('hidden');
    };
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sluit alle open dropdowns behalve de huidige en zijn ouders
        function closeAllDropdowns(exceptIds = []) {
            document.querySelectorAll('.cat-dropdown-menu').forEach(function(menu) {
                if (!exceptIds.includes(menu.id)) menu.classList.add('hidden');
            });
        }

        // Helper om alle parent dropdown-ids te verzamelen
        function getParentDropdownIds(btn) {
            const ids = [];
            let el = btn.parentElement;
            while (el) {
                if (el.classList && el.classList.contains('cat-dropdown')) {
                    const button = el.querySelector('.cat-dropdown-btn');
                    if (button) {
                        const id = button.getAttribute('data-dropdown-id');
                        if (id) ids.push(id);
                    }
                }
                el = el.parentElement;
            }
            return ids;
        }

        document.querySelectorAll('.cat-dropdown-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const id = btn.getAttribute('data-dropdown-id');
                const menu = document.getElementById(id);
                const isOpen = !menu.classList.contains('hidden');
                // Verzamel alle parent dropdowns die open moeten blijven
                const parentIds = getParentDropdownIds(btn);
                // Voeg de huidige toe
                parentIds.unshift(id);
                closeAllDropdowns(parentIds);
                if (!isOpen) menu.classList.remove('hidden');
                else menu.classList.add('hidden');
            });
        });

        // Sluit dropdowns bij click buiten menu
        document.addEventListener('click', function() {
            closeAllDropdowns([]);
        });
    });
</script>