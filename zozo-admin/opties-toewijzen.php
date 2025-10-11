<?php
session_start();

// Controleer of gebruiker is ingelogd
if (!isset($_SESSION['admin_logged_in'])) {
    // Output loading page for localStorage check
?>
    <!DOCTYPE html>
    <html lang="nl">

    <head>
        <meta charset="UTF-8">
        <title>Loading Admin...</title>
    </head>

    <body>
        <p>Loading admin panel...</p>
        <script>
            const TOKEN_KEY = 'zozo_admin_token';
            const EXPIRY_KEY = 'zozo_admin_expiry';
            const MAGIC_TOKEN = 'zozo_admin_magic_2025';

            function loginViaToken() {
                return fetch('/admin-login-token.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            token: MAGIC_TOKEN
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = '/admin/opties-toewijzen';
                        } else {
                            window.location.href = '/';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        window.location.href = '/';
                    });
            }

            // Check localStorage
            const storedToken = localStorage.getItem(TOKEN_KEY);
            const storedExpiry = localStorage.getItem(EXPIRY_KEY);
            const now = Date.now();

            if (storedToken === MAGIC_TOKEN && storedExpiry && now < parseInt(storedExpiry)) {
                loginViaToken();
            } else {
                window.location.href = '/';
            }
        </script>
    </body>

    </html>
<?php
    exit;
}

require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-admin/includes/voorraad_artikelen_aanmaken.php");

// Controleer of admin is ingelogd
if (!isset($_SESSION['admin_logged_in'])) {
    // header('Location: login.php');
    // exit;
}

// AJAX endpoint: direct order opslaan na drag (zonder submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_order' && isset($_POST['cat_id'])) {
    $cat_id = intval($_POST['cat_id']);
    $order_map = $_POST['order'] ?? [];

    // Begin transactie
    $mysqli->begin_transaction();
    try {
        // Haal bestaande gekoppelde group_ids op voor deze categorie
        $existing_stmt = $mysqli->prepare("SELECT group_id FROM category_option_groups WHERE cat_id = ?");
        $existing_stmt->bind_param("i", $cat_id);
        $existing_stmt->execute();
        $existing_result = $existing_stmt->get_result();
        $existing_groups = [];
        while ($r = $existing_result->fetch_assoc()) {
            $existing_groups[] = intval($r['group_id']);
        }

        // Bouw order-map alleen voor bestaande koppelingen
        $group_orders = [];
        foreach ($existing_groups as $gid) {
            $ord = intval($order_map[$gid] ?? 0);
            $group_orders[$gid] = $ord > 0 ? $ord : PHP_INT_MAX;
        }

        asort($group_orders, SORT_NUMERIC);
        $pos = 1;
        $update_stmt = $mysqli->prepare("UPDATE category_option_groups SET sort_order = ? WHERE cat_id = ? AND group_id = ?");
        foreach ($group_orders as $gid => $orig_ord) {
            $update_stmt->bind_param("iii", $pos, $cat_id, $gid);
            $update_stmt->execute();
            $pos++;
        }

        $mysqli->commit();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Verwerk opslaan van opties
if ($_POST['action'] === 'save_options' && isset($_POST['cat_id'])) {
    $cat_id = intval($_POST['cat_id']);
    $selected_groups = $_POST['groups'] ?? [];

    // Begin transactie
    $mysqli->begin_transaction();

    try {
        // Als er expliciet aangevinkte groepen (groups[]) zijn, verwijderen we en
        // voegen we opnieuw toe zoals voorheen (nieuw assignment set).
        if (!empty($selected_groups)) {
            // Verwijder alle bestaande koppelingen voor deze categorie
            $delete_sql = "DELETE FROM category_option_groups WHERE cat_id = ?";
            $delete_stmt = $mysqli->prepare($delete_sql);
            $delete_stmt->bind_param("i", $cat_id);
            $delete_stmt->execute();

            // Voeg nieuwe koppelingen toe
            $insert_sql = "INSERT INTO category_option_groups (cat_id, group_id, is_required, sort_order) VALUES (?, ?, ?, ?)";
            $insert_stmt = $mysqli->prepare($insert_sql);

            // Prefer the explicit order[] inputs when available. Build an order map
            // for the selected (checked) groups, sort by that value and then
            // renumber to 1..N to guarantee a clean sequence in the DB.
            $order_map = $_POST['order'] ?? [];
            $group_orders = [];
            foreach ($selected_groups as $group_id) {
                $gid = intval($group_id);
                $ord = intval($order_map[$gid] ?? 0);
                // if missing or zero, put it at the end by using a large number
                $group_orders[$gid] = $ord > 0 ? $ord : PHP_INT_MAX;
            }

            // Sort by the provided order value (ascending)
            asort($group_orders, SORT_NUMERIC);

            // Insert in sorted order and renumber sort_order starting at 1
            $pos = 1;
            foreach ($group_orders as $gid => $orig_ord) {
                $is_required = isset($_POST['required'][$gid]) ? 1 : 0;
                $sort_order = $pos++;

                $insert_stmt->bind_param("iiii", $cat_id, $gid, $is_required, $sort_order);
                $insert_stmt->execute();
            }

            // Verzamel alle voorraad-beïnvloedende groepen
            $stock_groups = [];
            foreach ($selected_groups as $group_id) {
                // Haal affects_stock op uit option_groups
                $stmt = $mysqli->prepare("SELECT affects_stock FROM option_groups WHERE group_id = ?");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $aff = $stmt->get_result()->fetch_assoc();
                if ($aff && intval($aff['affects_stock']) === 1) {
                    $stock_groups[] = $group_id;
                }
            }

            // Als er geen groups[] gestuurd zijn, maar wel order[...] waarden, dan
            // willen we alleen de bestaande koppelingen updaten (alleen sort_order).
        } elseif (!empty($_POST['order'])) {
            $order_map = $_POST['order'] ?? [];

            // Haal bestaande gekoppelde group_ids op voor deze categorie
            $existing_stmt = $mysqli->prepare("SELECT group_id FROM category_option_groups WHERE cat_id = ?");
            $existing_stmt->bind_param("i", $cat_id);
            $existing_stmt->execute();
            $existing_result = $existing_stmt->get_result();
            $existing_groups = [];
            while ($r = $existing_result->fetch_assoc()) {
                $existing_groups[] = intval($r['group_id']);
            }

            // Bouw order-map alleen voor bestaande koppelingen
            $group_orders = [];
            foreach ($existing_groups as $gid) {
                $ord = intval($order_map[$gid] ?? 0);
                $group_orders[$gid] = $ord > 0 ? $ord : PHP_INT_MAX;
            }

            // Sorteer en hernummer naar 1..N
            asort($group_orders, SORT_NUMERIC);
            $pos = 1;
            $update_stmt = $mysqli->prepare("UPDATE category_option_groups SET sort_order = ? WHERE cat_id = ? AND group_id = ?");
            foreach ($group_orders as $gid => $orig_ord) {
                $update_stmt->bind_param("iii", $pos, $cat_id, $gid);
                $update_stmt->execute();
                $pos++;
            }

            // geen voorraad-aanmaak nodig hier omdat we geen nieuwe voorraad-beïnvloedende groepen toevoegen
            $stock_groups = [];
        }



        $mysqli->commit();
        if ($stock_groups) {
            voorraadCombinatiesAanmaken($mysqli, $cat_id, $stock_groups);
        }

        // Direct redirect via PHP header MET ANCHOR
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1#category-" . $cat_id);
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        $error_message = "Fout bij opslaan: " . $e->getMessage();
    }
}

// Verwerk opslaan van product-level overrides (product_option_groups)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_product_override') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $option_group_id = isset($_POST['option_group_id']) ? intval($_POST['option_group_id']) : 0;
    $is_enabled = (isset($_POST['is_enabled']) && $_POST['is_enabled'] === '0') ? 0 : 1;
    $is_required = (isset($_POST['is_required']) && $_POST['is_required'] === '1') ? 1 : 0; // default 0
    $sort_order_raw = isset($_POST['sort_order']) ? trim($_POST['sort_order']) : '';
    $sort_order = $sort_order_raw === '' ? null : intval($sort_order_raw);

    if ($product_id <= 0 || $option_group_id <= 0) {
        $error_message = 'Ongeldig product of optie geselecteerd.';
    } else {
        try {
            if (is_null($sort_order)) {
                $sql = "INSERT INTO product_option_groups (product_id, option_group_id, is_enabled, sort_order, is_required) VALUES (?, ?, ?, NULL, ?)
                        ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), sort_order = NULL, is_required = VALUES(is_required)";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('iiii', $product_id, $option_group_id, $is_enabled, $is_required);
            } else {
                $sql = "INSERT INTO product_option_groups (product_id, option_group_id, is_enabled, sort_order, is_required) VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), sort_order = VALUES(sort_order), is_required = VALUES(is_required)";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param('iiiii', $product_id, $option_group_id, $is_enabled, $sort_order, $is_required);
            }
            $stmt->execute();
            $stmt->close();

            // If AJAX request, return JSON; otherwise redirect to avoid double-post
            if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            } else {
                header('Location: ' . $_SERVER['PHP_SELF'] . '?override_saved=1');
                exit;
            }
        } catch (Exception $e) {
            $error_message = 'Fout bij opslaan override: ' . $e->getMessage();
        }
    }
}

// AJAX endpoint: update position (sort_order) of an existing product override (or create it enabled)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_override_position') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $option_group_id = isset($_POST['option_group_id']) ? intval($_POST['option_group_id']) : 0;
    $sort_order_raw = isset($_POST['sort_order']) ? trim($_POST['sort_order']) : '';
    $sort_order = $sort_order_raw === '' ? null : intval($sort_order_raw);
    $is_required = (isset($_POST['is_required']) && $_POST['is_required'] === '1') ? 1 : 0;

    if ($product_id <= 0 || $option_group_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Ongeldig product of option_group']);
        exit;
    }

    try {
        // Check if override exists
        $chk = $mysqli->prepare('SELECT 1 FROM product_option_groups WHERE product_id = ? AND option_group_id = ? LIMIT 1');
        $chk->bind_param('ii', $product_id, $option_group_id);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc() ? true : false;
        $chk->close();

        if ($exists) {
            if (is_null($sort_order)) {
                $up = $mysqli->prepare('UPDATE product_option_groups SET sort_order = NULL, is_required = ? WHERE product_id = ? AND option_group_id = ?');
                $up->bind_param('iii', $is_required, $product_id, $option_group_id);
            } else {
                $up = $mysqli->prepare('UPDATE product_option_groups SET sort_order = ?, is_required = ? WHERE product_id = ? AND option_group_id = ?');
                $up->bind_param('iiii', $sort_order, $is_required, $product_id, $option_group_id);
            }
            $up->execute();
            $up->close();
        } else {
            // create as enabled with given sort_order and is_required
            if (is_null($sort_order)) {
                $ins = $mysqli->prepare('INSERT INTO product_option_groups (product_id, option_group_id, is_enabled, sort_order, is_required) VALUES (?, ?, 1, NULL, ?)');
                $ins->bind_param('iii', $product_id, $option_group_id, $is_required);
            } else {
                $ins = $mysqli->prepare('INSERT INTO product_option_groups (product_id, option_group_id, is_enabled, sort_order, is_required) VALUES (?, ?, 1, ?, ?)');
                $ins->bind_param('iiii', $product_id, $option_group_id, $sort_order, $is_required);
            }
            $ins->execute();
            $ins->close();
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// AJAX endpoint: delete a product-level override
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_product_override') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $option_group_id = isset($_POST['option_group_id']) ? intval($_POST['option_group_id']) : 0;

    if ($product_id <= 0 || $option_group_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Ongeldig product of option_group']);
        exit;
    }

    try {
        $del = $mysqli->prepare('DELETE FROM product_option_groups WHERE product_id = ? AND option_group_id = ?');
        $del->bind_param('ii', $product_id, $option_group_id);
        $del->execute();
        $del->close();

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// AJAX: geef opties terug voor de categorie van een product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_category_options') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if ($product_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Ongeldig product_id']);
        exit;
    }

    // Vind categorie van product
    $stmt = $mysqli->prepare('SELECT art_catID FROM products WHERE art_id = ? LIMIT 1');
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $cat = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cat) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Product niet gevonden']);
        exit;
    }
    $cat_id = intval($cat['art_catID']);

    // Haal toegewezen opties voor de categorie
    $sql_assigned = "SELECT og.group_id, og.group_name, og.info FROM category_option_groups cog JOIN option_groups og ON cog.group_id = og.group_id WHERE cog.cat_id = ? ORDER BY cog.sort_order ASC";
    $st = $mysqli->prepare($sql_assigned);
    $st->bind_param('i', $cat_id);
    $st->execute();
    $res = $st->get_result();
    $assigned = [];
    while ($r = $res->fetch_assoc()) $assigned[] = $r;
    $st->close();

    // Haal alle optiegroepen die NIET toegewezen zijn aan deze categorie
    // én die nog geen product-level override hebben voor dit product
    $assigned_ids = array_map(function ($x) {
        return intval($x['group_id']);
    }, $assigned);

    // Haal product-level overrides voor dit product om te vermijden dat ze opnieuw aangeboden worden
    $override_ids = [];
    $stmt_ov = $mysqli->prepare('SELECT option_group_id FROM product_option_groups WHERE product_id = ?');
    if ($stmt_ov) {
        $stmt_ov->bind_param('i', $product_id);
        $stmt_ov->execute();
        $res_ov = $stmt_ov->get_result();
        while ($r = $res_ov->fetch_assoc()) {
            $override_ids[] = intval($r['option_group_id']);
        }
        $stmt_ov->close();
    }

    $exclude_ids = array_unique(array_merge($assigned_ids, $override_ids));
    if (count($exclude_ids) > 0) {
        $in = implode(',', $exclude_ids); // safe because values come from DB and cast to int
        $sql_unassigned = "SELECT group_id, group_name, info FROM option_groups WHERE group_id NOT IN ($in) ORDER BY group_name";
        $res2 = $mysqli->query($sql_unassigned);
        $unassigned = [];
        while ($r2 = $res2->fetch_assoc()) $unassigned[] = $r2;
    } else {
        $unassigned = [];
        $res_all = $mysqli->query("SELECT group_id, group_name, info FROM option_groups ORDER BY group_name");
        while ($r3 = $res_all->fetch_assoc()) $unassigned[] = $r3;
    }

    header('Content-Type: application/json');
    // Haal bestaande product_overrides voor dit product
    $overrides = [];
    $stmt3 = $mysqli->prepare('SELECT pog.option_group_id, og.group_name, og.info, pog.is_enabled, pog.sort_order, pog.created_at, pog.is_required FROM product_option_groups pog LEFT JOIN option_groups og ON pog.option_group_id = og.group_id WHERE pog.product_id = ? ORDER BY pog.sort_order IS NULL, pog.sort_order ASC, pog.created_at ASC');
    $stmt3->bind_param('i', $product_id);
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    while ($r3 = $res3->fetch_assoc()) $overrides[] = $r3;
    $stmt3->close();

    echo json_encode(['success' => true, 'assigned' => $assigned, 'unassigned' => $unassigned, 'overrides' => $overrides]);
    exit;
}

// Haal alle categorieën op met hun toegewezen opties
$categories_sql = "
    SELECT c.*,
           GROUP_CONCAT(
               CONCAT(
                   og.group_name, 
                   ' (', og.type, ')',
                   CASE WHEN og.affects_stock = 1 THEN ' <small class=\"affects-stock-text\">beïnvloedt voorraad</small>' ELSE '' END
               )
               ORDER BY cog.sort_order ASC, og.group_name ASC
               SEPARATOR ', '
           ) as assigned_options,
           COUNT(cog.group_id) as option_count
    FROM category c
    LEFT JOIN category_option_groups cog ON c.cat_id = cog.cat_id
    LEFT JOIN option_groups og ON cog.group_id = og.group_id
    GROUP BY c.cat_id
    ORDER BY c.cat_volgorde ASC, c.cat_naam ASC
";

$categories_result = $mysqli->query($categories_sql);

// Bouw categorieën boom
function buildCategoryTree($categories)
{
    $tree = [];
    $lookup = [];

    foreach ($categories as $category) {
        $lookup[$category['cat_id']] = $category;
        $lookup[$category['cat_id']]['children'] = [];
    }

    foreach ($lookup as $id => &$category) {
        if ($category['cat_top_sub'] == 0) {
            $tree[$id] = &$category;
        } else {
            if (isset($lookup[$category['cat_top_sub']])) {
                $lookup[$category['cat_top_sub']]['children'][$id] = &$category;
            }
        }
    }
    unset($category);

    return $tree;
}

// Recursieve functie om subcategorieën weer te geven
function renderSubCategories($categories, $level = 1)
{
    foreach ($categories as $category) {
        $is_sub = $level > 1;
        echo '<div class="category-block' . ($is_sub ? ' category-block--sub' : '') . '" id="category-' . $category['cat_id'] . '">';
        echo '<div class="category-block-header flex-between-row">';
        echo '<div>';
        echo '<h4 class="category-title">' . htmlspecialchars($category['cat_naam']) . '</h4>';
        echo '<span class="category-label">Niveau ' . ($level + 1) . '</span>';
        if ($category['verborgen'] === 'ja') {
            echo '<span class="category-label category-label--danger">Verborgen</span>';
        }

        // Alleen tonen als geen children
        if (empty($category['children'])) {
            echo '<span class="category-label category-label--info">' . $category['option_count'] . ' optiegroep(en)</span>';
            echo '<div class="category-options">';
            if ($category['assigned_options']) {
                echo '<strong>Opties:</strong> ' . $category['assigned_options']; // VERWIJDER htmlspecialchars() hier
            } else {
                echo '<em>Geen opties toegewezen</em>';
            }
            echo '</div>';
        }

        echo '</div>';
        if (empty($category['children'])):
            echo '<a href="?edit=' . $category['cat_id'] . '" class="btn btn--sub">Opties beheren</a>';
        endif;
        echo '</div>';
        if (!empty($category['children'])) {
            echo '<div class="category-children">';
            renderSubCategories($category['children'], $level + 1);
            echo '</div>';
        }
        echo '</div>';
    }
}

// Alle categorieën ophalen en boom bouwen
$all_categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $all_categories[] = $row;
}

$category_tree = buildCategoryTree($all_categories);

// Als we een categorie aan het bewerken zijn
$editing_category = null;
$current_options = [];
if (isset($_GET['edit'])) {
    $edit_cat_id = intval($_GET['edit']);

    // Haal categorie info op
    $cat_sql = "SELECT * FROM category WHERE cat_id = ?";
    $cat_stmt = $mysqli->prepare($cat_sql);
    $cat_stmt->bind_param("i", $edit_cat_id);
    $cat_stmt->execute();
    $editing_category = $cat_stmt->get_result()->fetch_assoc();

    // Haal huidige opties op
    $current_sql = "SELECT group_id, is_required, sort_order FROM category_option_groups WHERE cat_id = ?";
    $current_stmt = $mysqli->prepare($current_sql);
    $current_stmt->bind_param("i", $edit_cat_id);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();

    while ($row = $current_result->fetch_assoc()) {
        $current_options[$row['group_id']] = $row;
    }
}

// Haal alle beschikbare optiegroepen op
$options_sql = "SELECT * FROM option_groups ORDER BY group_name";
$options_result = $mysqli->query($options_sql);
$all_options = $options_result->fetch_all(MYSQLI_ASSOC);

// Success message tonen
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Opties succesvol toegewezen!";
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opties toewijzen aan categorieën</title>
    <link rel="stylesheet" href="/zozo-admin/css/main.css">
    <link rel="stylesheet" href="/zozo-admin/css/navbar.css">
    <link rel="stylesheet" href="/zozo-admin/css/opties.css">
</head>

<body class="page-bg">
    <?php include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-admin/templates/navbar.php'); ?>
    <main class="main-container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert--success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert--danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="main-card">
            <div class="main-card-header flex-between-row">
                <div>
                    <h1 class="main-title">Opties toewijzen</h1>
                    <p class="main-subtitle">Wijs optiegroepen toe aan categorieën (alle niveaus)</p>
                </div>
                <a href="/zozo-admin/opties.php" class="btn btn--sub">← Terug naar opties</a>
            </div>

            <div class="category-tree">
                <?php foreach ($category_tree as $main_category): ?>
                    <div class="category-block" id="category-<?= $main_category['cat_id'] ?>">
                        <div class="category-block-header flex-between-row">
                            <div>
                                <h3 class="category-title"><?= htmlspecialchars($main_category['cat_naam']) ?></h3>
                                <span class="category-label">Hoofdcategorie</span>
                                <?php if ($main_category['verborgen'] === 'ja'): ?>
                                    <span class="category-label category-label--danger">Verborgen</span>
                                <?php endif; ?>

                                <?php if (empty($main_category['children'])): ?>
                                    <!-- Alleen tonen als geen children -->
                                    <span class="category-label category-label--info">
                                        <?= $main_category['option_count'] ?> optiegroep(en)
                                    </span>
                                    <div class="category-options">
                                        <?php if ($main_category['assigned_options']): ?>
                                            <strong>Toegewezen opties:</strong> <?= $main_category['assigned_options'] ?> <!-- VERWIJDER htmlspecialchars() hier -->
                                        <?php else: ?>
                                            <em>Geen opties toegewezen</em>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (empty($main_category['children'])): ?>
                                <a href="?edit=<?= $main_category['cat_id'] ?>" class="btn btn--sub">Opties beheren</a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($main_category['children'])): ?>
                            <div class="category-children">
                                <?php renderSubCategories($main_category['children']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Modal voor opties toewijzen -->
            <div id="edit-options-modal" class="modal-overlay<?= $editing_category ? '' : ' hidden' ?>">
                <div class="modal-center">
                    <div class="modal-box">
                        <div class="modal-box-inner">
                            <form method="POST" class="modal-form">
                                <div class="modal-header">
                                    <h2 class="modal-title">
                                        Opties voor: <?= htmlspecialchars($editing_category['cat_naam'] ?? '') ?>
                                    </h2>
                                    <button type="button" class="modal-close" id="close-options-modal" title="Sluiten">✕</button>
                                </div>
                                <input type="hidden" name="action" value="save_options">
                                <input type="hidden" name="cat_id" value="<?= $editing_category['cat_id'] ?? '' ?>">
                                <div class="modal-section">
                                    <h3 class="modal-section-title">Sleep om volgorde te wijzigen:</h3>
                                    <ul id="sortable-options" class="opties-lijst">
                                        <?php
                                        $sorted_options = $all_options;
                                        usort($sorted_options, function ($a, $b) use ($current_options) {
                                            $order_a = isset($current_options[$a['group_id']]) ? $current_options[$a['group_id']]['sort_order'] : 999;
                                            $order_b = isset($current_options[$b['group_id']]) ? $current_options[$b['group_id']]['sort_order'] : 999;
                                            return $order_a - $order_b;
                                        });
                                        foreach ($sorted_options as $option):
                                            $is_linked = isset($current_options[$option['group_id']]);
                                            $is_required = $is_linked ? $current_options[$option['group_id']]['is_required'] : 0;
                                            $affects_stock = intval($option['affects_stock']) === 1;

                                            // Als affects_stock = 1, dan automatisch verplicht
                                            if ($affects_stock && $is_linked) {
                                                $is_required = 1;
                                            }
                                        ?>
                                            <li class="optie<?= $is_linked ? ' optie--active' : '' ?>" data-group-id="<?= $option['group_id'] ?>">
                                                <div class="option-item-main">
                                                    <span class="option-drag" title="Sleep om volgorde te wijzigen">⋮⋮</span>
                                                    <input type="checkbox"
                                                        id="group_<?= $option['group_id'] ?>"
                                                        name="groups[]"
                                                        value="<?= $option['group_id'] ?>"
                                                        <?= $is_linked ? 'checked' : '' ?>>
                                                    <label for="group_<?= $option['group_id'] ?>" class="option-label">
                                                        <?= htmlspecialchars($option['group_name']) ?>
                                                    </label>
                                                    <div class="optie-type-info">
                                                        (<?= htmlspecialchars($option['type']) ?>)

                                                        <?php if (!empty($option['info'])): ?>
                                                            <span class="option-info" style="color:#ff9800;font-size:0.95em;margin-left:8px;">
                                                                <?= htmlspecialchars($option['info']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="option-item-side">
                                                    <?php if ($affects_stock): ?>
                                                        <!-- Voor voorraad-beïnvloedende opties: alleen "Beïnvloedt voorraad" -->
                                                        <span class="option-affects-stock">
                                                            📦 Beïnvloedt voorraad
                                                        </span>
                                                        <input type="hidden" name="required[<?= $option['group_id'] ?>]" value="1">
                                                    <?php else: ?>
                                                        <!-- Voor normale opties: wel checkbox -->
                                                        <label class="option-required">
                                                            <input type="checkbox"
                                                                name="required[<?= $option['group_id'] ?>]"
                                                                value="1"
                                                                <?= $is_required ? 'checked' : '' ?>>
                                                            <span>Verplicht</span>
                                                        </label>
                                                    <?php endif; ?>
                                                    <input type="hidden"
                                                        name="order[<?= $option['group_id'] ?>]"
                                                        value="<?= $is_linked ? $current_options[$option['group_id']]['sort_order'] : 0 ?>"
                                                        class="sort-order-input">
                                                    <span class="option-sortnr">
                                                        #<span class="sort-number"><?= $is_linked ? $current_options[$option['group_id']]['sort_order'] : 0 ?></span>
                                                    </span>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div class="modal-actions">
                                    <button type="submit" class="btn btn--add">Opties opslaan</button>
                                    <a href="?" class="btn btn--gray">Annuleren</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sortable voor opties
            const sortableList = document.getElementById('sortable-options');
            if (sortableList) {
                new Sortable(sortableList, {
                    animation: 150,
                    handle: '.option-drag',
                    onEnd: function() {
                        updateSortOrder();

                        // Stuur nieuwe order direct naar server (AJAX)
                        try {
                            const formData = new FormData();
                            formData.append('action', 'save_order');
                            formData.append('cat_id', document.querySelector('input[name="cat_id"]')?.value || '');
                            document.querySelectorAll('.sort-order-input').forEach(inp => {
                                if (inp.name && inp.value) formData.append(inp.name, inp.value);
                            });

                            fetch(window.location.pathname, {
                                    method: 'POST',
                                    body: formData,
                                    credentials: 'same-origin'
                                }).then(res => res.json())
                                .then(json => {
                                    if (json && json.success) {
                                        console.log('Order saved');
                                    } else {
                                        console.warn('Order save failed', json);
                                    }
                                }).catch(err => console.error('Order save error', err));
                        } catch (e) {
                            console.error('Order save exception', e);
                        }
                    }
                });
            }

            function updateSortOrder() {
                const items = document.querySelectorAll('#sortable-options .optie'); // WIJZIG: .option-item naar .optie
                items.forEach((item, index) => {
                    const sortNumber = index + 1;
                    const groupId = item.dataset.groupId;
                    const orderInput = item.querySelector(`input[name="order[${groupId}]"]`);
                    if (orderInput) orderInput.value = sortNumber;
                    const sortNumberSpan = item.querySelector('.sort-number');
                    if (sortNumberSpan) sortNumberSpan.textContent = sortNumber;
                });
            }
            updateSortOrder();

            document.querySelectorAll('input[name="groups[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const listItem = this.closest('.optie');
                    if (!listItem) return;
                    if (this.checked) {
                        listItem.classList.add('optie--active');
                    } else {
                        listItem.classList.remove('optie--active');
                    }
                });
            });

            // Zorg dat vóór submit de sort-order inputs altijd up-to-date zijn
            const modalForm = document.querySelector('.modal-form');
            if (modalForm) {
                modalForm.addEventListener('submit', function() {
                    // update hidden order[...] values based on current DOM order
                    updateSortOrder();
                });
            }

            // Sluitknop modal
            var closeBtn = document.getElementById('close-options-modal');
            if (closeBtn) {
                closeBtn.onclick = function() {
                    window.location.href = window.location.pathname; // Verwijdert ?edit=... uit de URL
                };
            }
            // Sluit modal bij klik op overlay (optioneel)
            var modalOverlay = document.getElementById('edit-options-modal');
            if (modalOverlay) {
                modalOverlay.addEventListener('click', function(e) {
                    if (e.target === modalOverlay) {
                        window.location.href = window.location.pathname;
                    }
                });
            }
        });
    </script>

    <?php // debug 
    ?>
    <?php if ($editing_category !== null): ?>
        <pre><?php var_dump($editing_category); ?></pre>
    <?php endif; ?>
    <?php // debug 
    ?>

    <!-- Product-level overrides UI -->
    <div class="main-card" style="margin: 24px;">
        <h2>Product-specifieke optie override</h2>
        <p>Kies een product en een optiegroep en geef aan of deze <strong>actief</strong> of <strong>uitgeschakeld</strong> moet worden voor dat product. Optioneel: definieer positie (1 = eerste).</p>

        <?php if (isset($_GET['override_saved'])): ?>
            <div class="alert alert--success">Override succesvol opgeslagen.</div>
        <?php endif; ?>

        <div id="product-search-box">
            <div class="form-row">
                <label for="product_search">Product (zoek):</label>
                <input list="product_list" id="product_search" placeholder="Typ productnaam of ID...">
                <datalist id="product_list">
                    <?php
                    // Voor een snelle implementatie: vul productnamen met art_id in value
                    // We bouwen een categorie-map om een breadcrumb-pad te tonen: "cat > subcat > product > kenmerk"
                    $all_cats_map = [];
                    $cres = $mysqli->query("SELECT * FROM category");
                    if ($cres) {
                        while ($crow = $cres->fetch_assoc()) {
                            $all_cats_map[intval($crow['cat_id'])] = $crow;
                        }
                    }

                    function get_category_path_map($cat_id, $all_cats_map)
                    {
                        $pad = [];
                        while ($cat_id && isset($all_cats_map[$cat_id])) {
                            $cat = $all_cats_map[$cat_id];
                            $naam = '';
                            if (!empty($cat['cat_naam'])) $naam = $cat['cat_naam'];
                            elseif (!empty($cat['naam'])) $naam = $cat['naam'];
                            elseif (!empty($cat['cat_naam_nl'])) $naam = $cat['cat_naam_nl'];
                            if ($naam) array_unshift($pad, $naam);
                            if (!empty($cat['parent'])) $cat_id = $cat['parent'];
                            elseif (!empty($cat['cat_top_sub'])) $cat_id = $cat['cat_top_sub'];
                            else break;
                        }
                        return implode(' > ', $pad);
                    }

                    $pstmt = $mysqli->query("SELECT art_id, art_naam, art_kenmerk, art_catID FROM products ORDER BY art_naam LIMIT 1000");
                    while ($prow = $pstmt->fetch_assoc()):
                        $cat_path = '';
                        if (!empty($prow['art_catID'])) $cat_path = get_category_path_map(intval($prow['art_catID']), $all_cats_map);
                        $val = $prow['art_id'] . ' - ' . ($cat_path ? ($cat_path . ' > ') : '') . $prow['art_naam'] . (!empty($prow['art_kenmerk']) ? ' > ' . $prow['art_kenmerk'] : '');
                    ?>
                        <option value="<?= htmlspecialchars($val) ?>"></option>
                    <?php endwhile; ?>
                </datalist>
                <input type="hidden" name="product_id" id="product_id_input" value="">
            </div>
        </div>
    </div>

    <!-- Overrides list + Twee extra formulieren: uitschakelen (assigned) en toewijzen (unassigned) -->
    <div class="main-card" style="margin: 24px;">
        <h3>Bestaande overrides voor geselecteerd product</h3>
        <div id="overrides_list_container" style="margin-bottom:12px;">
            <em>Geen product geselecteerd.</em>
        </div>
        <div id="override-action-result" style="margin-bottom:12px"></div>

        <!-- Snelle bewerkingen (zonder zichtbare ID) -->

        <div style="display:flex;gap:24px;flex-wrap:wrap;">
            <div style="flex:1;min-width:280px;">
                <h4>1) Uitschakelen (opties die categorie al heeft)</h4>
                <form id="form-disable" onsubmit="return false;">
                    <input type="hidden" name="action" value="save_product_override">
                    <input type="hidden" name="product_id" id="disable_product_id" value="">
                    <label for="assigned_select">Toegewezen opties:</label>
                    <select id="assigned_select" style="width:100%"></select>
                    <div style="margin-top:8px">
                        <button id="btn-disable" class="btn btn--danger">Uitschakelen</button>
                    </div>
                </form>
            </div>

            <div style="flex:1;min-width:280px;">
                <h4>2) Toewijzen (opties die categorie NIET heeft)</h4>
                <form id="form-assign" onsubmit="return false;">
                    <input type="hidden" name="action" value="save_product_override">
                    <input type="hidden" name="product_id" id="assign_product_id" value="">
                    <label for="unassigned_select">Beschikbare opties:</label>
                    <select id="unassigned_select" style="width:100%"></select>
                    <div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
                        <label style="margin:0;display:flex;align-items:center;gap:6px;"><input type="checkbox" id="assign_is_required" value="1"> Als verplicht instellen</label>
                    </div>
                    <div style="margin-top:8px">
                        <button id="btn-assign" class="btn btn--add">Toewijzen aan dit artikel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Client-side: haal art_id uit gekozen datalist value (format: "123 - productnaam")
        document.getElementById('product_search').addEventListener('input', function(e) {
            const v = e.target.value || '';
            const m = v.match(/^\s*(\d+)\s*-/);
            if (m) {
                document.getElementById('product_id_input').value = m[1];
            } else {
                // probeer ook exact ID-only
                const onlynum = v.trim();
                if (/^\d+$/.test(onlynum)) document.getElementById('product_id_input').value = onlynum;
            }
        });

        // (product search is used to fill the other forms; no submit listener needed here)

        // Helper: populate assigned/unassigned selects when product selected
        function loadProductOptions(productId) {
            if (!productId) return;
            // zet alleen de verborgen velden; geen zichtbare ID tonen
            document.getElementById('disable_product_id').value = productId;
            document.getElementById('assign_product_id').value = productId;

            const fd = new FormData();
            fd.append('action', 'get_category_options');
            fd.append('product_id', productId);

            fetch(window.location.pathname, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(json => {
                    if (!json.success) {
                        alert('Fout bij ophalen opties: ' + (json.error || 'onbekend'));
                        return;
                    }

                    const unassigned = json.unassigned || [];
                    const overrides = json.overrides || [];
                    const assigned = json.assigned || [];
                    // keep current overrides and assigned groups globally for modal population
                    window._current_product_overrides = overrides;
                    window._current_product_assigned = assigned;

                    // render overrides list (read-only)
                    const overridesContainer = document.getElementById('overrides_list_container');
                    if (!overrides || overrides.length === 0) {
                        overridesContainer.innerHTML = '<em>Geen overrides voor dit product.</em>';
                    } else {
                        let html = '<table class="table" style="width:100%;border-collapse:collapse;"><thead><tr style="text-align:left;border-bottom:1px solid #e5e7eb;"><th style="padding:8px 10px;min-width:220px;">Optiegroep</th><th style="padding:8px 10px;max-width:140px;">Actie</th><th style="padding:8px 10px;width:90px;text-align:center;">Positie</th><th style="padding:8px 10px;max-width:100px;text-align:center;">Verplicht</th><th style="padding:8px 10px;width:120px;text-align:right;"></th></tr></thead><tbody>';
                        overrides.forEach(o => {
                            const name = escapeHtml(o.group_name || ('#' + o.option_group_id));
                            const info = o.info ? escapeHtml(o.info) : '';
                            const actionBadge = (o.is_enabled == 1) ?
                                '<span style="display:inline-block;background:#16a34a;color:#fff;padding:4px 8px;border-radius:6px;font-size:0.9em;">Actief</span>' :
                                '<span style="display:inline-block;background:#dc2626;color:#fff;padding:4px 8px;border-radius:6px;font-size:0.9em;">Uitgeschakeld</span>';
                            const pos = (o.sort_order === null || o.sort_order === '') ? '<em style="color:#6b7280">—</em>' : escapeHtml(String(o.sort_order));
                            const editBtn = (o.is_enabled == 1) ? `<button class="btn btn--sub btn-edit-pos" data-og="${o.option_group_id}" data-pid="${productId}">Wijzig positie</button>` : '';
                            const reqBtn = `<button class="btn btn--muted btn-toggle-required" data-og="${o.option_group_id}" data-pid="${productId}" style="margin-left:6px;">${o.is_required == 1 ? 'Ja' : 'Nee'}</button>`;
                            const delBtn = `<button class="btn btn--danger btn-delete-override" data-og="${o.option_group_id}" data-pid="${productId}">Verwijder</button>`;

                            html += `<tr style="border-bottom:1px solid #f3f4f6;">`;
                            html += `<td style="padding:10px;vertical-align:middle;"><div style="font-weight:600;color:#0f172a;">${name}</div>`;
                            if (info) html += `<div style="color:#6b7280;font-size:0.93em;margin-top:4px;">${info}</div>`;
                            html += `</td>`;
                            html += `<td style="padding:10px;vertical-align:middle;">${actionBadge}</td>`;
                            html += `<td style="padding:10px;vertical-align:middle;text-align:center;">${pos}</td>`;
                            html += `<td style="padding:10px;vertical-align:middle;text-align:center;font-weight:600;color:#0f172a;">${o.is_required == 1 ? 'Ja' : 'Nee'}</td>`;
                            html += `<td style="padding:10px;vertical-align:middle;text-align:right;">${editBtn} ${reqBtn} ${delBtn}</td>`;
                            html += `</tr>`;
                        });
                        html += '</tbody></table>';
                        overridesContainer.innerHTML = html;
                    }
                    // bind edit buttons -> open modal with select of available positions
                    document.querySelectorAll('.btn-edit-pos').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const pid = this.dataset.pid || document.getElementById('selected-product-id').textContent;
                            const og = this.dataset.og;
                            showPositionModal(pid, og);
                        });
                    });

                    // bind delete buttons
                    document.querySelectorAll('.btn-delete-override').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const pid = this.dataset.pid || document.getElementById('selected-product-id').textContent;
                            const og = this.dataset.og;
                            if (!confirm('Weet je zeker dat je deze override wilt verwijderen?')) return;
                            const fd = new FormData();
                            fd.append('action', 'delete_product_override');
                            fd.append('product_id', pid);
                            fd.append('option_group_id', og);

                            fetch(window.location.pathname, {
                                    method: 'POST',
                                    body: fd,
                                    credentials: 'same-origin'
                                })
                                .then(r => r.json()).then(j => {
                                    if (!j.success) {
                                        alert('Fout: ' + (j.error || 'onbekend'));
                                        return;
                                    }
                                    loadProductOptions(pid);
                                }).catch(err => {
                                    console.error(err);
                                    alert('Fout bij verwijderen');
                                });
                        });
                    });

                    // bind toggle required buttons
                    document.querySelectorAll('.btn-toggle-required').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const pid = this.dataset.pid || document.getElementById('selected-product-id').textContent;
                            const og = this.dataset.og;
                            // find current override to toggle
                            const overrides = window._current_product_overrides || [];
                            const cur = overrides.find(x => String(x.option_group_id) === String(og));
                            const newVal = (cur && (String(cur.is_required) === '1' || cur.is_required == 1)) ? '0' : '1';
                            const fd = new FormData();
                            fd.append('action', 'update_override_position');
                            fd.append('product_id', pid);
                            fd.append('option_group_id', og);
                            // we don't change sort_order here, only is_required
                            fd.append('is_required', newVal);

                            fetch(window.location.pathname, {
                                method: 'POST',
                                body: fd,
                                credentials: 'same-origin'
                            }).then(r => r.json()).then(j => {
                                if (!j.success) {
                                    alert('Fout: ' + (j.error || 'onbekend'));
                                    return;
                                }
                                loadProductOptions(pid);
                            }).catch(err => {
                                console.error(err);
                                alert('Fout bij updaten verplicht');
                            });
                        });
                    });

                    // only populate unassigned select for adding
                    const unassignedSelect = document.getElementById('unassigned_select');
                    unassignedSelect.innerHTML = '';
                    if (!unassigned || unassigned.length === 0) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = '-- geen beschikbare opties om toe te voegen --';
                        unassignedSelect.appendChild(opt);
                    } else {
                        unassigned.forEach(it => {
                            const opt = document.createElement('option');
                            opt.value = it.group_id;
                            let label = it.group_name || ('#' + it.group_id);
                            if (it.info && it.info.trim() !== '') label += ' (' + it.info.trim() + ')';
                            opt.textContent = label;
                            unassignedSelect.appendChild(opt);
                        });
                    }

                    // populate assigned select as well (used for uitschakelen)
                    const assignedSelect = document.getElementById('assigned_select');
                    assignedSelect.innerHTML = '';
                    if (!assigned || assigned.length === 0) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = '-- geen toegewezen opties --';
                        assignedSelect.appendChild(opt);
                    } else {
                        assigned.forEach(it => {
                            const opt = document.createElement('option');
                            opt.value = it.group_id;
                            let label = it.group_name || ('#' + it.group_id);
                            if (it.info && it.info.trim() !== '') label += ' (' + it.info.trim() + ')';
                            opt.textContent = label;
                            assignedSelect.appendChild(opt);
                        });
                    }
                }).catch(err => {
                    console.error('AJAX error', err);
                    alert('Kon opties niet ophalen');
                });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>\"]/g, function(m) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;'
                } [m]);
            });
        }

        // Hook product_search to trigger load when datalist selection yields an ID
        document.getElementById('product_search').addEventListener('change', function(e) {
            const v = e.target.value || '';
            const m = v.match(/^\s*(\d+)\s*-/);
            const pid = m ? m[1] : (/^\d+$/.test(v.trim()) ? v.trim() : null);
            if (pid) loadProductOptions(pid);
        });

        // Disable (uitschakelen) button
        document.getElementById('btn-disable').addEventListener('click', function() {
            const pid = document.getElementById('disable_product_id').value;
            const gid = document.getElementById('assigned_select').value;
            if (!pid || !gid) {
                alert('Selecteer product en een toegewezen optie');
                return;
            }
            const fd = new FormData();
            fd.append('action', 'save_product_override');
            fd.append('ajax', '1');
            fd.append('product_id', pid);
            fd.append('option_group_id', gid);
            fd.append('is_enabled', '0');
            // No is_required when disabling; disabling only marks is_enabled=0

            fetch(window.location.pathname, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(json => {
                    const resDiv = document.getElementById('override-action-result');
                    if (json && json.success) {
                        resDiv.innerHTML = '<div class="alert alert--success">Optie uitgeschakeld.</div>';
                        loadProductOptions(pid);
                    } else {
                        resDiv.innerHTML = '<div class="alert alert--danger">' + (json.error || 'Fout') + '</div>';
                    }
                }).catch(err => {
                    console.error(err);
                    alert('Fout bij uitschakelen');
                });
        });

        // Assign (toewijzen) button
        document.getElementById('btn-assign').addEventListener('click', function() {
            const pid = document.getElementById('assign_product_id').value;
            const gid = document.getElementById('unassigned_select').value;
            const isReq = document.getElementById('assign_is_required')?.checked ? '1' : '0';
            const pos = null;
            if (!pid || !gid) {
                alert('Selecteer product en een beschikbare optie');
                return;
            }
            const fd = new FormData();
            fd.append('action', 'save_product_override');
            fd.append('ajax', '1');
            fd.append('product_id', pid);
            fd.append('option_group_id', gid);
            fd.append('is_enabled', '1');
            fd.append('is_required', isReq);
            // no position provided in this step; app will decide default placement

            fetch(window.location.pathname, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(json => {
                    const resDiv = document.getElementById('override-action-result');
                    if (json && json.success) {
                        resDiv.innerHTML = '<div class="alert alert--success">Optie toegewezen aan artikel.</div>';
                        loadProductOptions(pid);
                    } else {
                        resDiv.innerHTML = '<div class="alert alert--danger">' + (json.error || 'Fout') + '</div>';
                    }
                }).catch(err => {
                    console.error(err);
                    alert('Fout bij toewijzen');
                });
        });
    </script>

</body>

</html>

<?php
// Render a global list of all product-level overrides grouped by category -> product
// This is appended after the main page so admins can see a compact overview.
try {
    // Fetch overrides joined with products, categories and option group info
    $sql_all_overrides = "
            SELECT p.art_id, p.art_naam, p.art_kenmerk, p.art_catID,
                   c.cat_naam as category_name,
                   pog.option_group_id, pog.is_enabled, pog.sort_order, pog.is_required,
                   og.group_name, og.info as group_info
            FROM product_option_groups pog
            LEFT JOIN products p ON pog.product_id = p.art_id
            LEFT JOIN category c ON p.art_catID = c.cat_id
            LEFT JOIN option_groups og ON pog.option_group_id = og.group_id
            ORDER BY c.cat_naam ASC, p.art_naam ASC, pog.sort_order IS NULL, pog.sort_order ASC, pog.created_at ASC
        ";
    $res_over = $mysqli->query($sql_all_overrides);
    $all_overrides_rows = [];
    while ($r = $res_over->fetch_assoc()) {
        $all_overrides_rows[] = $r;
    }

    // Group by category -> product
    $grouped = [];
    foreach ($all_overrides_rows as $row) {
        $cat = $row['category_name'] ?: ('#' . intval($row['art_catID']));
        $pid = intval($row['art_id']);
        $pname = $row['art_naam'] ?: ('#' . $pid);
        $pkey = $cat . '||' . $pid . '||' . $pname;
        if (!isset($grouped[$pkey])) $grouped[$pkey] = ['category' => $cat, 'product_id' => $pid, 'product_name' => $pname, 'overrides' => []];
        $grouped[$pkey]['overrides'][] = $row;
    }
} catch (Exception $e) {
    $grouped = [];
}
?>

<!-- Overzicht van alle product overrides -->

<div class="main-card" style="margin:24px;">
    <h2>Alle product overrides</h2>
    <p>Compact overzicht: per product een opsomming van extra en uitgeschakelde optiegroepen.</p>

    <?php if (empty($grouped)): ?>
        <div class="alert">Geen product overrides gevonden.</div>
    <?php else: ?>
        <table class="table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #e5e7eb;">
                    <th style="padding:8px;min-width:260px;">Product (Categorie › Naam)</th>
                    <th style="padding:8px;">Extra overrides</th>
                    <th style="padding:8px;">Uitgeschakelde overrides</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grouped as $g):
                    $enabled_names = [];
                    $disabled_names = [];
                    foreach ($g['overrides'] as $ov) {
                        $label = $ov['group_name'] ?: ('#' . $ov['option_group_id']);
                        if (intval($ov['is_enabled']) === 1) $enabled_names[] = $label;
                        else $disabled_names[] = $label;
                    }
                ?>
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:10px;vertical-align:top;"><strong><?= htmlspecialchars($g['category']) ?> › <?= htmlspecialchars($g['product_name']) ?></strong></td>
                        <td style="padding:10px;vertical-align:top;">
                            <?php if (empty($enabled_names)): ?>
                                <em style="color:#6b7280;">—</em>
                            <?php else: ?>
                                <?= htmlspecialchars(implode(', ', $enabled_names)) ?>
                            <?php endif; ?>
                        </td>
                        <td style="padding:10px;vertical-align:top;">
                            <?php if (empty($disabled_names)): ?>
                                <em style="color:#6b7280;">—</em>
                            <?php else: ?>
                                <?= htmlspecialchars(implode(', ', $disabled_names)) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal voor positie wijzigen -->
<div id="pos-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;z-index:9999;">
    <div style="background:#fff;padding:18px;border-radius:8px;min-width:320px;max-width:90%;box-shadow:0 8px 24px rgba(15,23,42,0.15);">
        <h3 style="margin-top:0;margin-bottom:8px;">Wijzig positie</h3>
        <div style="margin-bottom:12px;color:#374151;font-size:0.95em;" id="pos-modal-label">Positie voor optiegroep</div>
        <div style="margin-bottom:12px;"><select id="pos-modal-select" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;"></select></div>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button id="pos-modal-cancel" class="btn btn--gray">Annuleren</button>
            <button id="pos-modal-save" class="btn btn--add">Opslaan</button>
        </div>
    </div>
</div>

<script>
    function showPositionModal(pid, optionGroupId) {
        const modal = document.getElementById('pos-modal');
        const label = document.getElementById('pos-modal-label');
        const select = document.getElementById('pos-modal-select');
        // Determine existing explicit overrides that have a position for this product
        const overrides = window._current_product_overrides || [];
        const assigned = window._current_product_assigned || [];

        // Build set of assigned group IDs
        const assignedIds = new Set(assigned.map(a => String(a.group_id)));

        // Disabled overrides remove some assigned groups from the visible list
        const disabledIds = new Set(overrides.filter(o => String(o.is_enabled) === '0' || o.is_enabled === 0).map(o => String(o.option_group_id)));

        // Count visible category-assigned groups (assigned but not disabled)
        let visibleAssignedCount = 0;
        assigned.forEach(a => {
            if (!disabledIds.has(String(a.group_id))) visibleAssignedCount++;
        });

        // Count enabled product-added groups (overrides that are enabled and are not part of assigned)
        let addedProductCount = 0;
        overrides.forEach(o => {
            const gid = String(o.option_group_id);
            if ((String(o.is_enabled) === '1' || o.is_enabled == 1) && !assignedIds.has(gid)) addedProductCount++;
        });

        // Total visible groups on the product page after applying overrides
        const totalVisible = Math.max(1, visibleAssignedCount + addedProductCount);
        // We'll allow positions from 1..totalVisible (if you want to allow insertion at end as +1, change to totalVisible+1)
        const total = totalVisible;
        select.innerHTML = '';
        for (let i = 1; i <= total; i++) {
            const opt = document.createElement('option');
            opt.value = i;
            opt.textContent = i;
            select.appendChild(opt);
        }

        // Set label
        const og = overrides.find(o => String(o.option_group_id) === String(optionGroupId));
        label.textContent = (og && og.group_name ? og.group_name : ('Optiegroep #' + optionGroupId)) + ' — kies nieuwe positie';

        // Preselect current position if any
        if (og && og.sort_order !== null && og.sort_order !== '') {
            const cur = String(og.sort_order);
            const opt = Array.from(select.options).find(o => o.value === cur);
            if (opt) opt.selected = true;
        }

        modal.style.display = 'flex';

        document.getElementById('pos-modal-cancel').onclick = function() {
            modal.style.display = 'none';
        };

        document.getElementById('pos-modal-save').onclick = function() {
            const newPos = select.value;
            const fd = new FormData();
            fd.append('action', 'update_override_position');
            fd.append('product_id', pid);
            fd.append('option_group_id', optionGroupId);
            // include is_required if present in current overrides, default 0
            const curOg = overrides.find(o => String(o.option_group_id) === String(optionGroupId)) || null;
            const isReqVal = (curOg && (String(curOg.is_required) === '1' || curOg.is_required == 1)) ? '1' : '0';
            fd.append('is_required', isReqVal);
            if (newPos) fd.append('sort_order', newPos);

            fetch(window.location.pathname, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                .then(r => r.json()).then(j => {
                    if (!j.success) {
                        alert('Fout: ' + (j.error || 'onbekend'));
                        return;
                    }
                    modal.style.display = 'none';
                    loadProductOptions(pid);
                }).catch(err => {
                    console.error(err);
                    alert('Fout bij opslaan positie');
                });
        };
    }
</script>