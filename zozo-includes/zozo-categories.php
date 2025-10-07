<?php
// filepath: e:\meettoestel.be\zozo-includes\zozo-categories.php

require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");

// Aan het begin van het bestand, voor debugging:
error_log("zozo-categories.php wordt geladen voor URL: " . $_SERVER['REQUEST_URI']);

// Haal ALLE benodigde velden op
$res = $mysqli->query(
    "SELECT 
        cat_id, 
        cat_naam, cat_naam_fr, cat_naam_en, 
        cat_top_sub, cat_volgorde, 
        cat_afkorting, cat_afkorting_fr, cat_afkorting_en 
     FROM category 
     WHERE verborgen = 0 
     ORDER BY cat_top_sub, cat_volgorde, cat_naam"
);

$all_cats = [];
while ($row = $res->fetch_assoc()) {
    $row['children'] = [];
    $all_cats[$row['cat_id']] = $row;
}

// Bouw boomstructuur voor menu
$categories = [];
foreach ($all_cats as $id => &$cat) {
    if ($cat['cat_top_sub'] && isset($all_cats[$cat['cat_top_sub']])) {
        $all_cats[$cat['cat_top_sub']]['children'][] = &$cat;
    } else {
        $categories[] = &$cat;
    }
}
unset($cat);

// Mapping: slug <-> id per taal
$slug_to_id = ['nl' => [], 'fr' => [], 'en' => []];
$id_to_slug = ['nl' => [], 'fr' => [], 'en' => []];
foreach ($all_cats as $cat) {
    if (!empty($cat['cat_afkorting'])) {
        $slug_to_id['nl'][$cat['cat_afkorting']] = $cat['cat_id'];
        $id_to_slug['nl'][$cat['cat_id']] = $cat['cat_afkorting'];
    }
    if (!empty($cat['cat_afkorting_fr'])) {
        $slug_to_id['fr'][$cat['cat_afkorting_fr']] = $cat['cat_id'];
        $id_to_slug['fr'][$cat['cat_id']] = $cat['cat_afkorting_fr'];
    }
    if (!empty($cat['cat_afkorting_en'])) {
        $slug_to_id['en'][$cat['cat_afkorting_en']] = $cat['cat_id'];
        $id_to_slug['en'][$cat['cat_id']] = $cat['cat_afkorting_en'];
    }
}

// Huidige slugpad ophalen
$cat_path = '';
if (preg_match('#^/(nl|fr|en)/(.*)#', $_SERVER['REQUEST_URI'], $m)) {
    $cat_path = $m[2];
    $cur_lang = $m[1];
} else {
    $cur_lang = $lang ?? 'nl';
}
$cat_slugs = $cat_path ? explode('/', $cat_path) : [];

// Zet huidig slugpad om naar IDs - HIËRARCHISCH
$ids = [];
$current_parent = 0; // Start bij root (geen parent)

foreach ($cat_slugs as $slug) {
    $found_id = null;

    // Zoek categorie met deze slug die een child is van current_parent
    foreach ($all_cats as $cat) {
        // Check of deze categorie de juiste parent heeft
        if ($cat['cat_top_sub'] != $current_parent) {
            continue;
        }

        // Check of de slug matcht in de huidige taal
        $cat_slug_current = '';
        if ($cur_lang === 'fr' && !empty($cat['cat_afkorting_fr'])) {
            $cat_slug_current = $cat['cat_afkorting_fr'];
        } elseif ($cur_lang === 'en' && !empty($cat['cat_afkorting_en'])) {
            $cat_slug_current = $cat['cat_afkorting_en'];
        } else {
            $cat_slug_current = $cat['cat_afkorting'];
        }

        if ($cat_slug_current === $slug) {
            $found_id = $cat['cat_id'];
            break;
        }
    }

    if ($found_id) {
        $ids[] = $found_id;
        $current_parent = $found_id; // Deze wordt de parent voor de volgende stap
    } else {
        // Slug niet gevonden op deze diepte - stop
        break;
    }
}

// De laatste gevonden ID is de huidige categorie
$current_cat_id = !empty($ids) ? end($ids) : null;

// DEBUG: Voeg dit toe na de nieuwe code hierboven
error_log("DEBUG: Slugs: " . implode(' -> ', $cat_slugs));
error_log("DEBUG: IDs gevonden: " . implode(' -> ', $ids));
error_log("DEBUG: Final current_cat_id: " . ($current_cat_id ?? 'null'));

// Helperfuncties
function cat_name($cat, $lang)
{
    $key = 'cat_naam' . ($lang === 'nl' ? '' : "_$lang");
    return isset($cat[$key]) && $cat[$key] !== '' ? $cat[$key] : (isset($cat['cat_naam']) ? $cat['cat_naam'] : 'Categorie');
}
function cat_slug($cat, $lang)
{
    $key = 'cat_afkorting' . ($lang === 'nl' ? '' : "_$lang");
    return isset($cat[$key]) && $cat[$key] !== '' ? $cat[$key] : (isset($cat['cat_afkorting']) ? $cat['cat_afkorting'] : '');
}

// In je centrale include:
$cat_by_id = [];
foreach ($all_cats as $cat) {
    $cat_by_id[$cat['cat_id']] = $cat;
}

function render_menu($cats, $lang, $depth = 0, $parent_ids = [])
{
    global $cat_by_id;
    if (!$cats) return;

    // Haal huidige URL-pad op voor actieve state
    $current_path = $_SERVER['REQUEST_URI'];

    echo '<ul class="nav-list depth-' . $depth . '">';
    foreach ($cats as $cat) {
        $name = htmlspecialchars(cat_name($cat, $lang));
        $slug = htmlspecialchars(cat_slug($cat, $lang));
        $has_children = !empty($cat['children']);

        // Bouw het pad op: vertaal alle parent-IDs naar slugs in de juiste taal
        $slugs = [];
        foreach ($parent_ids as $pid) {
            $slugs[] = htmlspecialchars(cat_slug($cat_by_id[$pid], $lang));
        }
        $slugs[] = $slug;
        $url = '/' . $lang . '/' . implode('/', $slugs);

        // Check of deze categorie actief is
        $is_active = ($current_path === $url || strpos($current_path, $url . '/') === 0);
        $active_class = $is_active ? ' active' : '';

        echo '<li class="nav-item' . ($has_children ? ' has-children' : '') . $active_class . '">';
        echo "<a href=\"$url\" class=\"nav-link$active_class\">$name";
        if ($has_children) {
            echo ' <span class="nav-arrow">&#9660;</span>';
        }
        echo "</a>";
        if ($has_children) {
            // Geef de parent-IDs door
            render_menu($cat['children'], $lang, $depth + 1, array_merge($parent_ids, [$cat['cat_id']]));
        }
        echo '</li>';
    }
    echo '</ul>';
}

function get_all_subcat_ids($cat_id, $all_cats)
{
    $ids = [$cat_id];
    foreach ($all_cats as $cat) {
        if ($cat['cat_top_sub'] == $cat_id) {
            $ids = array_merge($ids, get_all_subcat_ids($cat['cat_id'], $all_cats));
        }
    }
    return $ids;
}
