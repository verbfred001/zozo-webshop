<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");
$id = intval($_GET['id'] ?? 0);
$lang = $_GET['lang'] ?? 'nl';

// Haal product op (inclusief art_catID)
$col_naam = 'art_naam' . ($lang === 'nl' ? '' : "_$lang");
$col_kenmerk = 'art_kenmerk' . ($lang === 'nl' ? '' : "_$lang");
$q = $mysqli->prepare("SELECT art_id, $col_naam AS name, $col_kenmerk AS kenmerk, art_kostprijs, art_BTWtarief, art_catID, art_levertijd, art_aantal FROM products WHERE art_id=?");
$q->bind_param('i', $id);
$q->execute();
$res = $q->get_result();
$product = $res->fetch_assoc();

if (!$product) {
    http_response_code(404);
    echo json_encode(['error' => 'Product niet gevonden']);
    exit;
}

$btw = $product['art_BTWtarief'] ?? 21;
$prijs_excl = floatval($product['art_kostprijs']);
$prijs_incl = $prijs_excl * (1 + $btw / 100);

$productData = [
    'id' => $product['art_id'],
    'name' => $product['name'],
    'kenmerk' => isset($product['kenmerk']) ? $product['kenmerk'] : '',
    'price_incl' => round($prijs_incl, 2),
    'btw' => $btw
];

// voeg levertijd (dagen) toe aan product response zodat frontend exact kan tonen
$productData['art_levertijd'] = intval($product['art_levertijd'] ?? 0);
// voeg productniveau voorraad toe zodat frontend bij geen opties direct kan gebruiken
$productData['art_aantal'] = intval($product['art_aantal'] ?? 0);

// Haal optie-groepen op via de categorie van het product
$groups = [];
$groups_by_id = [];
$catID = $product['art_catID'] ?? 0;
$gq = $mysqli->prepare("SELECT og.group_id, og.group_name, og.group_name_fr, og.group_name_en, og.type, og.affects_stock, cog.is_required, cog.sort_order
    FROM category_option_groups cog
    JOIN option_groups og ON cog.group_id = og.group_id
    WHERE cog.cat_id = ?");
$gq->bind_param('i', $catID);
$gq->execute();
$gres = $gq->get_result();
while ($g = $gres->fetch_assoc()) {
    $group_name = $g['group_name'];
    if ($lang === 'fr' && $g['group_name_fr']) $group_name = $g['group_name_fr'];
    if ($lang === 'en' && $g['group_name_en']) $group_name = $g['group_name_en'];
    // Haal opties op
    $oq = $mysqli->prepare("SELECT option_id, option_name, option_name_fr, option_name_en, price_delta
        FROM options WHERE group_id=? ORDER BY sort_order ASC, option_name ASC");
    $oq->bind_param('i', $g['group_id']);
    $oq->execute();
    $ores = $oq->get_result();
    $options = [];
    while ($o = $ores->fetch_assoc()) {
        $option_name = $o['option_name'];
        if ($lang === 'fr' && $o['option_name_fr']) $option_name = $o['option_name_fr'];
        if ($lang === 'en' && $o['option_name_en']) $option_name = $o['option_name_en'];
        $options[] = [
            'option_id' => $o['option_id'],
            'option_name' => $option_name,
            'price_delta' => floatval($o['price_delta'] ?? 0),
            'affects_stock' => $g['affects_stock']
        ];
    }
    $entry = [
        'group_id' => $g['group_id'],
        'group_name' => $group_name, // vertaalde naam voor de UI
        'group_name_nl' => $g['group_name'], // technische naam, altijd NL
        'type' => $g['type'],
        'affects_stock' => $g['affects_stock'],
        'is_required' => $g['is_required'],
        'category_sort_order' => isset($g['sort_order']) ? intval($g['sort_order']) : null,
        'product_sort_order' => null,
        'is_overridden' => false,
        'options' => $options
    ];
    $groups_by_id[intval($g['group_id'])] = $entry;
}

$stmt_over = $mysqli->prepare("SELECT pog.option_group_id, pog.is_enabled, pog.sort_order, pog.is_required, pog.created_at, og.group_name, og.group_name_fr, og.group_name_en, og.type, og.affects_stock
    FROM product_option_groups pog
    JOIN option_groups og ON pog.option_group_id = og.group_id
    WHERE pog.product_id = ?");
$stmt_over->bind_param('i', $id);
$stmt_over->execute();
$res_over = $stmt_over->get_result();
$overrides = [];
while ($ov = $res_over->fetch_assoc()) {
    $gid = intval($ov['option_group_id']);
    $overrides[$gid] = $ov;
}

// Apply overrides: we'll interpret product sort_order as a desired 1-based insertion position
// Start from category-ordered visible groups, remove disabled ones, then insert enabled overrides
// at their requested positions. This matches the admin UI's notion of 'positie'.

// First: remove explicitly disabled overrides from category groups
foreach ($overrides as $gid => $ov) {
    if (intval($ov['is_enabled']) === 0) {
        if (isset($groups_by_id[$gid])) unset($groups_by_id[$gid]);
    }
}

// Build an initial ordered list from category groups (ordered by category_sort_order then name)
$initial = array_values($groups_by_id);
usort($initial, function ($a, $b) {
    $aOrder = isset($a['category_sort_order']) && $a['category_sort_order'] !== null ? intval($a['category_sort_order']) : PHP_INT_MAX;
    $bOrder = isset($b['category_sort_order']) && $b['category_sort_order'] !== null ? intval($b['category_sort_order']) : PHP_INT_MAX;
    if ($aOrder !== $bOrder) return $aOrder <=> $bOrder;
    return strcmp($a['group_name'] ?? '', $b['group_name'] ?? '');
});

// We'll maintain an ordered list and a helper to remove existing occurrence of a group id
$ordered = $initial;
$removeExisting = function (&$arr, $gid) {
    foreach ($arr as $i => $it) {
        if (isset($it['group_id']) && intval($it['group_id']) === intval($gid)) {
            array_splice($arr, $i, 1);
            return;
        }
    }
};

// Collect overrides that are enabled and have an explicit sort_order
$over_with_pos = [];
$over_without_pos = [];
foreach ($overrides as $gid => $ov) {
    if (intval($ov['is_enabled']) !== 1) continue; // skip disabled
    if ($ov['sort_order'] !== null) {
        $over_with_pos[] = $ov + ['option_group_id' => $gid];
    } else {
        $over_without_pos[] = $ov + ['option_group_id' => $gid];
    }
}

// tiebreak by created_at so earlier assignments keep earlier placement
usort($over_with_pos, function ($a, $b) {
    $aPos = intval($a['sort_order']);
    $bPos = intval($b['sort_order']);
    if ($aPos !== $bPos) return $aPos <=> $bPos;
    // fallback to created_at if present
    $aCreated = $a['created_at'] ?? null;
    $bCreated = $b['created_at'] ?? null;
    if ($aCreated && $bCreated) return strcmp($aCreated, $bCreated);
    return intval($a['option_group_id']) <=> intval($b['option_group_id']);
});

// Insert overrides with explicit positions
foreach ($over_with_pos as $ov) {
    $gid = intval($ov['option_group_id']);
    // Ensure group data exists in groups_by_id, if not fetch and create it
    if (!isset($groups_by_id[$gid])) {
        $oq = $mysqli->prepare("SELECT option_id, option_name, option_name_fr, option_name_en, price_delta FROM options WHERE group_id=? ORDER BY sort_order ASC, option_name ASC");
        $oq->bind_param('i', $gid);
        $oq->execute();
        $ores = $oq->get_result();
        $options = [];
        while ($o = $ores->fetch_assoc()) {
            $option_name = $o['option_name'];
            if ($lang === 'fr' && $o['option_name_fr']) $option_name = $o['option_name_fr'];
            if ($lang === 'en' && $o['option_name_en']) $option_name = $o['option_name_en'];
            $options[] = [
                'option_id' => $o['option_id'],
                'option_name' => $option_name,
                'price_delta' => $o['price_delta'],
                'affects_stock' => $ov['affects_stock']
            ];
        }
        $group_name = $ov['group_name'];
        if ($lang === 'fr' && !empty($ov['group_name_fr'])) $group_name = $ov['group_name_fr'];
        if ($lang === 'en' && !empty($ov['group_name_en'])) $group_name = $ov['group_name_en'];
        $is_required_val = isset($ov['is_required']) ? intval($ov['is_required']) : 0;
        $groups_by_id[$gid] = [
            'group_id' => $gid,
            'group_name' => $group_name,
            'group_name_nl' => $ov['group_name'],
            'type' => $ov['type'],
            'affects_stock' => $ov['affects_stock'],
            'is_required' => $is_required_val,
            'category_sort_order' => null,
            'product_sort_order' => ($ov['sort_order'] !== null ? intval($ov['sort_order']) : null),
            'is_overridden' => true,
            'options' => $options
        ];
    } else {
        // override existing category group
        $groups_by_id[$gid]['is_overridden'] = true;
        $groups_by_id[$gid]['product_sort_order'] = ($ov['sort_order'] !== null ? intval($ov['sort_order']) : null);
        if (isset($ov['is_required'])) $groups_by_id[$gid]['is_required'] = intval($ov['is_required']);
    }

    // Remove any existing occurrence and insert at requested 1-based position
    $removeExisting($ordered, $gid);
    $pos = max(1, intval($ov['sort_order']));
    $idx = $pos - 1; // convert to 0-based index
    if ($idx > count($ordered)) $idx = count($ordered);
    // Insert the group's full definition from groups_by_id
    $item = $groups_by_id[$gid];
    // ensure product_sort_order reflects requested pos (helpful for debugging)
    $item['product_sort_order'] = intval($ov['sort_order']);
    array_splice($ordered, $idx, 0, [$item]);
}

// Now handle enabled overrides without explicit position: ensure they exist and append if not present
foreach ($over_without_pos as $ov) {
    $gid = intval($ov['option_group_id']);
    if (!isset($groups_by_id[$gid])) {
        $oq = $mysqli->prepare("SELECT option_id, option_name, option_name_fr, option_name_en, price_delta FROM options WHERE group_id=? ORDER BY sort_order ASC, option_name ASC");
        $oq->bind_param('i', $gid);
        $oq->execute();
        $ores = $oq->get_result();
        $options = [];
        while ($o = $ores->fetch_assoc()) {
            $option_name = $o['option_name'];
            if ($lang === 'fr' && $o['option_name_fr']) $option_name = $o['option_name_fr'];
            if ($lang === 'en' && $o['option_name_en']) $option_name = $o['option_name_en'];
            $options[] = [
                'option_id' => $o['option_id'],
                'option_name' => $option_name,
                'price_delta' => $o['price_delta'],
                'affects_stock' => $ov['affects_stock']
            ];
        }
        $group_name = $ov['group_name'];
        if ($lang === 'fr' && !empty($ov['group_name_fr'])) $group_name = $ov['group_name_fr'];
        if ($lang === 'en' && !empty($ov['group_name_en'])) $group_name = $ov['group_name_en'];
        $is_required_val = isset($ov['is_required']) ? intval($ov['is_required']) : 0;
        $groups_by_id[$gid] = [
            'group_id' => $gid,
            'group_name' => $group_name,
            'group_name_nl' => $ov['group_name'],
            'type' => $ov['type'],
            'affects_stock' => $ov['affects_stock'],
            'is_required' => $is_required_val,
            'category_sort_order' => null,
            'product_sort_order' => null,
            'is_overridden' => true,
            'options' => $options
        ];
    } else {
        $groups_by_id[$gid]['is_overridden'] = true;
        if (isset($ov['is_required'])) $groups_by_id[$gid]['is_required'] = intval($ov['is_required']);
    }
    // append if not already present
    $present = false;
    foreach ($ordered as $it) if (isset($it['group_id']) && intval($it['group_id']) === $gid) {
        $present = true;
        break;
    }
    if (!$present) $ordered[] = $groups_by_id[$gid];
}

// Final ordered list
$groups = $ordered;

// Haal voorraadbeheersinstelling op
$stmt_settings = $mysqli->prepare("SELECT voorraadbeheer FROM instellingen LIMIT 1");
$stmt_settings->execute();
$settings = $stmt_settings->get_result()->fetch_assoc();
$voorraadbeheer_actief = $settings['voorraadbeheer'] ?? 1;

if ($mysqli->error) {
    http_response_code(500);
    echo json_encode(['error' => $mysqli->error]);
    exit;
}

echo json_encode([
    'product' => $productData,
    'options' => $groups,
    'voorraadbeheer' => $voorraadbeheer_actief
]);
