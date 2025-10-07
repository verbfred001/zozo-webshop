<?php
function voorraadCombinatiesAanmaken($mysqli, $cat_id, $group_ids)
{
    echo ('<br>Functie voorraadCombinatiesAanmaken gestart');

    // helper: normaliseer/alfabetiseer een optiestring "g:1|h:2|..." => gesorteerd
    $normalizeOpties = function (string $optie_str) {
        $parts = array_filter(array_map('trim', explode('|', $optie_str)));
        sort($parts, SORT_STRING);
        return implode('|', $parts);
    };

    // 1. Haal alle voorraad-beïnvloedende groepen op
    $groups = [];
    $placeholders = implode(',', array_fill(0, count($group_ids), '?'));
    $types = str_repeat('i', count($group_ids));
    $stmt = $mysqli->prepare("SELECT group_id, group_name FROM option_groups WHERE affects_stock = 1 AND group_id IN ($placeholders)");
    $stmt->bind_param($types, ...$group_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $groups[$row['group_id']] = $row['group_name'];
        echo ('<br>Group ID: ' . $row['group_id'] . ', Group Name: ' . $row['group_name']);
    }
    if (empty($groups)) return;

    // 2. Haal alle opties per groep op
    $options_per_group = [];
    foreach ($groups as $group_id => $group_name) {
        $opt_stmt = $mysqli->prepare("SELECT option_id, option_name FROM options WHERE group_id = ?");
        $opt_stmt->bind_param("i", $group_id);
        $opt_stmt->execute();
        $opt_result = $opt_stmt->get_result();
        $options = [];
        while ($opt = $opt_result->fetch_assoc()) {
            $options[] = ['id' => $opt['option_id'], 'name' => $opt['option_name']];
        }
        $options_per_group[$group_name] = $options;
    }

    // 3. Genereer alle combinaties
    $combinations = [[]];
    foreach ($options_per_group as $group_name => $options) {
        $tmp = [];
        foreach ($combinations as $comb) {
            foreach ($options as $option) {
                $tmp[] = array_merge($comb, [$group_name . ':' . $option['id']]);
            }
        }
        $combinations = $tmp;
    }

    // 4. Haal alle artikelen in deze categorie
    $prod_stmt = $mysqli->prepare("SELECT art_id FROM products WHERE art_catID = ?");
    $prod_stmt->bind_param("i", $cat_id);
    $prod_stmt->execute();
    $prod_result = $prod_stmt->get_result();
    $products = [];
    while ($row = $prod_result->fetch_assoc()) {
        $products[] = $row['art_id'];
        echo ('<br>Product ID: ' . $row['art_id']);
    }

    // 4b. Verwijder ALLE oude voorraadregels voor dit artikel
    if (!empty($products)) {
        foreach ($products as $art_id) {
            $sql = "DELETE FROM voorraad WHERE art_id = ?";
            $del_stmt = $mysqli->prepare($sql);
            $del_stmt->bind_param("i", $art_id);
            $del_stmt->execute();
        }
    }

    // 5. Voeg voorraad-rijen toe (sla opties ALTIJD alfabetisch gesorteerd op in kolom 'opties')
    $insert_stmt = $mysqli->prepare("INSERT IGNORE INTO voorraad (art_id, opties, stock) VALUES (?, ?, 0)");
    foreach ($products as $art_id) {
        foreach ($combinations as $comb) {
            $optie_str = implode('|', $comb);
            // normaliseer: alfabetisch sorteren van onderdelen voordat we opslaan
            $optie_str = $normalizeOpties($optie_str);
            $insert_stmt->bind_param("is", $art_id, $optie_str);
            $insert_stmt->execute();
        }
    }
    $insert_stmt->close();
}

/**
 * Create missing voorraad combinations for a single article without deleting existing rows.
 * Parameters:
 *  - $mysqli: mysqli connection
 *  - $art_id: the single article id to create voorraad rows for
 *  - $cat_id: category id (used to find option groups)
 *  - $group_ids: array of group_ids that affect stock
 */
function createVoorraadCombinatiesVoorArtikel($mysqli, $art_id, $cat_id, $group_ids)
{
    echo ('<br>Functie createVoorraadCombinatiesVoorArtikel gestart voor art_id: ' . intval($art_id));

    // helper: normaliseer/alfabetiseer een optiestring "g:1|h:2|..." => gesorteerd
    $normalizeOpties = function (string $optie_str) {
        $parts = array_filter(array_map('trim', explode('|', $optie_str)));
        sort($parts, SORT_STRING);
        return implode('|', $parts);
    };

    if (empty($group_ids)) return;

    // 1. Haal alle voorraad-beïnvloedende groepen op (zelfde als de grotere functie)
    $groups = [];
    $placeholders = implode(',', array_fill(0, count($group_ids), '?'));
    $types = str_repeat('i', count($group_ids));
    $stmt = $mysqli->prepare("SELECT group_id, group_name FROM option_groups WHERE affects_stock = 1 AND group_id IN ($placeholders)");
    $stmt->bind_param($types, ...$group_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $groups[$row['group_id']] = $row['group_name'];
    }
    $stmt->close();
    if (empty($groups)) return;

    // 2. Haal alle opties per groep op
    $options_per_group = [];
    foreach ($groups as $group_id => $group_name) {
        $opt_stmt = $mysqli->prepare("SELECT option_id, option_name FROM options WHERE group_id = ?");
        $opt_stmt->bind_param("i", $group_id);
        $opt_stmt->execute();
        $opt_result = $opt_stmt->get_result();
        $options = [];
        while ($opt = $opt_result->fetch_assoc()) {
            $options[] = ['id' => $opt['option_id'], 'name' => $opt['option_name']];
        }
        $opt_stmt->close();
        $options_per_group[$group_name] = $options;
    }

    // 3. Genereer alle combinaties
    $combinations = [[]];
    foreach ($options_per_group as $group_name => $options) {
        $tmp = [];
        foreach ($combinations as $comb) {
            foreach ($options as $option) {
                $tmp[] = array_merge($comb, [$group_name . ':' . $option['id']]);
            }
        }
        $combinations = $tmp;
    }

    if (empty($combinations)) return;

    // 4. Insert only missing voorraad rows for this single article (INSERT IGNORE is safe)
    $insert_stmt = $mysqli->prepare("INSERT IGNORE INTO voorraad (art_id, opties, stock) VALUES (?, ?, 0)");
    foreach ($combinations as $comb) {
        $optie_str = implode('|', $comb);
        $optie_str = $normalizeOpties($optie_str);
        $insert_stmt->bind_param("is", $art_id, $optie_str);
        $insert_stmt->execute();
    }
    $insert_stmt->close();
}
