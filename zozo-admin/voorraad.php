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
                            window.location.href = '/admin/voorraad';
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

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-admin/includes/function_tel_voorraad_varianten.php");

// Controleer of gebruiker is ingelogd, anders redirect naar login.php
//if (!isset($_SESSION['admin_logged_in'])) {
//   header('Location: login.php');
//   exit;
//}

// Handle stock updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id = intval($_POST['product_id']);
    $new_stock = intval($_POST['new_stock']);
    $reason = $_POST['reason'] ?? 'Handmatige aanpassing';

    // Update product stock
    $stmt = $mysqli->prepare("UPDATE products SET art_aantal = ? WHERE art_id = ?");
    $stmt->bind_param("ii", $new_stock, $product_id);
    $stmt->execute();

    // Log stock change
    $stmt = $mysqli->prepare("INSERT INTO stock_movements (product_id, quantity_change, new_quantity, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
    // bereken verandering indien nodig (optioneel)
    $quantity_change = 0;
    $stmt->bind_param("iiis", $product_id, $quantity_change, $new_stock, $reason);
    $stmt->execute();

    // Redirect terug en open het betreffende product-accordion
    header('Location: /admin/voorraad?success=1&open=' . $product_id . '#product-' . $product_id);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_variant_stock'])) {
    $art_id = intval($_POST['art_id']);
    $opties = $_POST['opties'];
    $new_stock = intval($_POST['new_stock']);
    // nieuwe beschikbaar-waarde van checkbox (1 of 0)
    // inverted checkbox name: 'niet_verkrijgbaar' => verkrijgbaar = 0 when checked
    $verkrijgbaar = isset($_POST['niet_verkrijgbaar']) ? 0 : 1;
    $stmt = $mysqli->prepare("UPDATE voorraad SET stock = ?, verkrijgbaar = ? WHERE art_id = ? AND opties = ?");
    $stmt->bind_param("iiis", $new_stock, $verkrijgbaar, $art_id, $opties);
    $stmt->execute();

    // Redirect terug en open de variant-accordion voor dit product
    header('Location: /admin/voorraad?success=1&open=' . $art_id . '#variant-' . $art_id);
    exit;
}

// Filter options
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
if ($search) {
    $search_esc = $mysqli->real_escape_string($search);
    $where_conditions[] = "(art_naam LIKE '%$search_esc%' OR art_afkorting LIKE '%$search_esc%')";
}

switch ($filter) {
    case 'low':
        $where_conditions[] = "art_aantal <= 5";
        break;
    case 'out':
        $where_conditions[] = "art_aantal = 0";
        break;
    case 'negative':
        $where_conditions[] = "art_aantal < 0";
        break;
}

$where_sql = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get products with stock info
$sql = "SELECT p.*, c.cat_naam, pc.cat_naam AS parent_cat_naam 
    FROM products p 
    LEFT JOIN category c ON p.art_catID = c.cat_id 
    LEFT JOIN category pc ON c.cat_top_sub = pc.cat_id 
    $where_sql 
    ORDER BY p.art_aantal ASC, p.art_naam ASC";
$result = $mysqli->query($sql);

// Get stock statistics
$stats_query = "SELECT 
    COUNT(*) as total_products,
    SUM(CASE WHEN art_aantal = 0 THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN art_aantal <= 5 AND art_aantal > 0 THEN 1 ELSE 0 END) as low_stock,
    SUM(CASE WHEN art_aantal < 0 THEN 1 ELSE 0 END) as negative_stock
    FROM products";
$stats_result = $mysqli->query($stats_query);
$stats = $stats_result->fetch_assoc();

$artikelen = [];
$res = $mysqli->query("SELECT art_id, art_naam FROM products ORDER BY art_naam");
while ($row = $res->fetch_assoc()) {
    $artikelen[$row['art_id']] = $row['art_naam'];
}

$voorraad = [];
$res = $mysqli->query("SELECT art_id, opties, stock, verkrijgbaar FROM voorraad ORDER BY art_id, opties");
while ($row = $res->fetch_assoc()) {
    $voorraad[$row['art_id']][] = $row;
}

$option_lookup = [];
$res = $mysqli->query("SELECT option_id, option_name FROM options");
while ($row = $res->fetch_assoc()) {
    $option_lookup[$row['option_id']] = $row['option_name'];
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Voorraad</title>
    <link rel="stylesheet" href="/zozo-admin/css/main.css">
    <link rel="stylesheet" href="/zozo-admin/css/navbar.css">
    <link rel="stylesheet" href="/zozo-admin/css/voorraad.css">
</head>

<body>
    <?php include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-admin/templates/navbar.php'); ?>

    <main>
        <div class="voorraad-container">
            <!-- Success Message -->
            <?php if (isset($success_message) || isset($_GET['success'])): ?>
                <div class="voorraad-success">
                    <?= htmlspecialchars($success_message ?? 'Voorraad bijgewerkt!') ?>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="voorraad-header">
                <div>
                    <div class="voorraad-title">Voorraad beheer</div>
                    <div class="voorraad-sub">Beheer en monitor je product voorraad</div>
                </div>
                <button onclick="openBulkUpdateModal()" class="voorraad-btn">
                    <span style="font-size:1.2em;">&#128230;</span>
                    <span>Bulk update</span>
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="voorraad-stats">
                <div class="voorraad-stat-card">
                    <span class="voorraad-stat-icon">&#128230;</span>
                    <div>
                        <div class="voorraad-stat-label">Totaal producten</div>
                        <div class="voorraad-stat-value"><?= $stats['total_products'] ?></div>
                    </div>
                </div>
                <div class="voorraad-stat-card">
                    <span class="voorraad-stat-icon">&#9940;</span>
                    <div>
                        <div class="voorraad-stat-label">Uitverkocht</div>
                        <div class="voorraad-stat-value"><?= $stats['out_of_stock'] ?></div>
                    </div>
                </div>
                <div class="voorraad-stat-card">
                    <span class="voorraad-stat-icon">&#9888;</span>
                    <div>
                        <div class="voorraad-stat-label">Lage voorraad</div>
                        <div class="voorraad-stat-value"><?= $stats['low_stock'] ?></div>
                    </div>
                </div>
                <div class="voorraad-stat-card">
                    <span class="voorraad-stat-icon">&#128200;</span>
                    <div>
                        <div class="voorraad-stat-label">Negatieve voorraad</div>
                        <div class="voorraad-stat-value"><?= $stats['negative_stock'] ?></div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" class="voorraad-filters">
                <div style="flex:1; min-width:220px;">
                    <label class="voorraad-modal-label">Zoeken:</label>
                    <input type="text" name="search" id="search-input" placeholder="Zoek product..." value="<?= htmlspecialchars($search) ?>" class="voorraad-modal-input" list="product-suggesties">
                    <datalist id="product-suggesties">
                        <?php foreach ($artikelen as $art_naam): ?>
                            <option value="<?= htmlspecialchars($art_naam) ?>">
                            <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="voorraad-modal-label">Filter:</label>
                    <select name="filter" class="voorraad-modal-select">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Alle producten</option>
                        <option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Lage voorraad (≤5)</option>
                        <option value="out" <?= $filter === 'out' ? 'selected' : '' ?>>Uitverkocht</option>
                        <option value="negative" <?= $filter === 'negative' ? 'selected' : '' ?>>Negatieve voorraad</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="voorraad-btn" style="padding:8px 18px; font-size:1em;">
                        Filteren
                    </button>
                    <?php if ($search || $filter !== 'all'): ?>
                        <a href="/admin/voorraad" class="voorraad-btn" style="background:#6b7280; margin-left:8px;">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Products Table -->
            <div class="voorraad-table-wrap">
                <table class="voorraad-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Categorie</th>
                            <th>Huidige voorraad</th>
                            <th>Prijs</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows === 0): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:40px 0; color:#6b7280;">
                                    <div style="font-size:1.2em; margin-bottom:8px;">Geen producten gevonden</div>
                                    <div style="font-size:0.98em;">Pas je filter aan of voeg nieuwe producten toe.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php while ($product = $result->fetch_assoc()): ?>
                                <tr id="product-<?= (int)$product['art_id'] ?>">
                                    <td>
                                        <div style="font-weight:500; color:#22223b;">
                                            <?= htmlspecialchars($product['art_naam']) ?>
                                        </div>
                                        <?php if ($product['art_afkorting']): ?>
                                            <div style="color:#6b7280; font-size:0.96em;">
                                                <?= htmlspecialchars($product['art_afkorting']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color:#6b7280;">
                                        <?php
                                        $child = $product['cat_naam'] ?? '';
                                        $parent = $product['parent_cat_naam'] ?? '';
                                        if ($parent && $child) {
                                            echo htmlspecialchars($parent . ' - ' . $child);
                                        } else if ($child) {
                                            echo htmlspecialchars($child);
                                        } else {
                                            echo 'Geen categorie';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $voorraad_totaal = tel_voorraad_varianten($mysqli, $product['art_id']);
                                        $voorraad_klasse = 'voorraad-aantal-op';
                                        if ($voorraad_totaal < 0) $voorraad_klasse = 'voorraad-aantal-negatief';
                                        elseif ($voorraad_totaal == 0) $voorraad_klasse = 'voorraad-aantal-uit';
                                        elseif ($voorraad_totaal <= 5) $voorraad_klasse = 'voorraad-aantal-laag';

                                        $heeft_varianten = !empty($voorraad[$product['art_id']]);
                                        ?>
                                        <span class="voorraad-aantal <?= $voorraad_klasse ?>">
                                            <?= $voorraad_totaal ?>
                                        </span>
                                        <?php if ($heeft_varianten): ?>
                                            <button class="variant-accordion-toggle"
                                                onclick="toggleVariantAccordion(this)"
                                                data-target="variant-<?= (int)$product['art_id'] ?>"
                                                title="Toon varianten">
                                                <span class="accordion-arrow">&#9660;</span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:1em; color:#22223b;">
                                        €&nbsp;<?= number_format($product['art_kostprijs'], 2, ',', '.') ?>
                                    </td>
                                    <td class="voorraad-acties">
                                        <?php if (!$heeft_varianten): ?>
                                            <button onclick="openStockModal(<?= $product['art_id'] ?>, '<?= htmlspecialchars($product['art_naam']) ?>', <?= $product['art_aantal'] ?>)">
                                                Aanpassen
                                            </button>
                                        <?php endif; ?>
                                        <a href="/zozo-admin/artikel_bewerk.php?id=<?= $product['art_id'] ?>" class="artikel-bewerk-link" title="Artikel bewerken">
                                            <span style="font-size:1.1em; vertical-align:middle;">&#9998;</span>
                                            <span style="margin-left:4px;">Artikel bewerken</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php if (!empty($voorraad[$product['art_id']])): ?>
                                    <tr id="variant-<?= (int)$product['art_id'] ?>" class="variant-accordion-row" style="display:none;">
                                        <td colspan="6" style="padding:0;">
                                            <div style="padding:0.5em 1em;">
                                                <div class="variant-card-grid">
                                                    <?php foreach ($voorraad[$product['art_id']] as $variant): ?>
                                                        <script>
                                                            console.log("variant opties voor art_id <?= (int)$product['art_id'] ?>:", <?= json_encode($variant['opties']) ?>);
                                                        </script>
                                                        <div class="variant-card">
                                                            <div class="variant-card-title">
                                                                <?php
                                                                // Variantnaam opbouwen: elke optie op een aparte regel
                                                                $parts = explode('|', $variant['opties']);
                                                                foreach ($parts as $part) {
                                                                    list($groep, $optie_id) = explode(':', $part);
                                                                    echo '<div class="variant-card-optie">' . htmlspecialchars($option_lookup[$optie_id] ?? $optie_id) . '</div>';
                                                                }
                                                                ?>
                                                            </div>
                                                            <?php if (isset($variant['verkrijgbaar']) && intval($variant['verkrijgbaar']) === 0): ?>
                                                                <div class="variant-card-stock variant-unavailable" title="Niet verkrijgbaar" aria-label="Niet verkrijgbaar">
                                                                    <span class="variant-unavailable-icon" aria-hidden="true">&#10060;</span>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="variant-card-stock"><?= (int)$variant['stock'] ?></div>
                                                            <?php endif; ?>
                                                            <!-- opties: <?php echo htmlspecialchars($variant['opties']); ?> -->
                                                            <a href="#"
                                                                class="variant-card-edit"
                                                                onclick='openVariantStockModal(
                                                                    <?= (int)$product['art_id'] ?>,
                                                                    <?= json_encode($product['art_naam']) ?>,
                                                                    <?= json_encode($variant['opties']) ?>,
                                                                    <?= (int)$variant['stock'] ?>,
                                                                    <?= isset($variant['verkrijgbaar']) ? (int)$variant['verkrijgbaar'] : 1 ?>
                                                                ); return false;'>
                                                                Aanpassen
                                                            </a>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Stock Update Modal -->
        <div id="stock-modal" class="voorraad-modal-overlay hidden">
            <div style="display:flex; align-items:center; justify-content:center; min-height:100vh; width:100vw;">
                <div class="voorraad-modal">
                    <form method="post" action="/admin/voorraad">
                        <input type="hidden" name="update_stock" value="1">
                        <input type="hidden" name="product_id" id="modal-product-id">

                        <div class="voorraad-modal-header">
                            <div class="voorraad-modal-title">Voorraad aanpassen</div>
                            <button type="button" onclick="closeStockModal()" class="voorraad-modal-close">&times;</button>
                        </div>

                        <div class="voorraad-modal-fields">
                            <div>
                                <label class="voorraad-modal-label">Product:</label>
                                <p id="modal-product-name" style="font-weight:600;"></p>
                            </div>
                            <div>
                                <label class="voorraad-modal-label">Huidige voorraad:</label>
                                <p id="modal-current-stock" style="font-size:1.3em; font-weight:bold; color:#2563eb;"></p>
                            </div>
                            <div>
                                <label class="voorraad-modal-label">Nieuwe voorraad:</label>
                                <input type="number" name="new_stock" id="modal-new-stock" required class="voorraad-modal-input">
                            </div>
                            <div>
                                <label class="voorraad-modal-label">Reden:</label>
                                <select name="reason" class="voorraad-modal-select">
                                    <option value="Handmatige aanpassing">Handmatige aanpassing</option>
                                    <option value="Nieuwe levering">Nieuwe levering</option>
                                    <option value="Inventaris correctie">Inventaris correctie</option>
                                    <option value="Defect/beschadigd">Defect/beschadigd</option>
                                    <option value="Retour">Retour</option>
                                </select>
                            </div>
                        </div>

                        <div class="voorraad-modal-actions">
                            <button type="submit" class="voorraad-btn" style="background:#2563eb;">Opslaan</button>
                            <button type="button" onclick="closeStockModal()" class="voorraad-btn" style="background:#6b7280;">Annuleren</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Variant Stock Update Modal -->
        <div id="variant-stock-modal" class="voorraad-modal-overlay hidden">
            <div class="voorraad-modal">
                <form method="post" action="/admin/voorraad">
                    <input type="hidden" name="update_variant_stock" value="1">
                    <input type="hidden" name="art_id" id="variant-modal-art-id">
                    <input type="hidden" name="opties" id="variant-modal-opties">

                    <div class="voorraad-modal-header">
                        <div class="voorraad-modal-title">Variant voorraad aanpassen</div>
                        <button type="button" onclick="closeVariantStockModal()" class="voorraad-modal-close">&times;</button>
                    </div>

                    <div class="voorraad-modal-fields">
                        <div>
                            <label class="voorraad-modal-label">Product:</label>
                            <p id="variant-modal-product-name" style="font-weight:600;"></p>
                        </div>
                        <div>
                            <label class="voorraad-modal-label">Variant:</label>
                            <p id="variant-modal-variant-name" style="font-size:1em;"></p>
                        </div>
                        <div>
                            <label class="voorraad-modal-label">Voorraad:</label>
                            <input type="number" name="new_stock" id="variant-modal-new-stock" required class="voorraad-modal-input">
                        </div>
                        <div>
                            <label class="voorraad-modal-label">Niet verkrijgbaar</label>
                            <input type="checkbox" name="niet_verkrijgbaar" id="variant-modal-beschikbaar" value="1">
                        </div>
                    </div>

                    <div class="voorraad-modal-actions">
                        <button type="submit" class="voorraad-btn" style="background:#2563eb;">Opslaan</button>
                        <button type="button" onclick="closeVariantStockModal()" class="voorraad-btn" style="background:#6b7280;">Annuleren</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Mapping van option_id naar option_name
        var optionLookup = <?= json_encode($option_lookup) ?>;

        function openStockModal(productId, productName, currentStock) {
            document.getElementById('modal-product-id').value = productId;
            document.getElementById('modal-product-name').textContent = productName;
            document.getElementById('modal-current-stock').textContent = currentStock;
            document.getElementById('modal-new-stock').value = currentStock;
            document.getElementById('stock-modal').classList.remove('hidden');
        }

        function closeStockModal() {
            document.getElementById('stock-modal').classList.add('hidden');
        }

        function openVariantStockModal(artId, productName, opties, currentStock, beschikbaar) {
            document.getElementById('variant-modal-art-id').value = artId;
            document.getElementById('variant-modal-product-name').textContent = productName;
            // Zet de opties in het verborgen inputveld!
            document.getElementById('variant-modal-opties').value = opties;

            console.log("openVariantStockModal", artId, opties, currentStock);

            // Variant naam opbouwen
            var variantName = opties.split('|').map(function(optie) {
                var parts = optie.split(':');
                if (parts.length > 1) {
                    var optionId = parts[1];
                    return optionLookup[optionId] || optionId;
                }
                return optie;
            }).join(' - ');
            document.getElementById('variant-modal-variant-name').textContent = variantName;

            document.getElementById('variant-modal-new-stock').value = currentStock;
            // checkbox 'Niet verkrijgbaar' : checked wanneer verkrijgbaar == 0
            var chk = document.getElementById('variant-modal-beschikbaar');
            if (chk) chk.checked = Number(beschikbaar) === 0;
            document.getElementById('variant-stock-modal').classList.remove('hidden');
        }

        function closeVariantStockModal() {
            document.getElementById('variant-stock-modal').classList.add('hidden');
        }

        function openBulkUpdateModal() {
            alert('Bulk update functionaliteit kan hier worden toegevoegd');
        }

        function toggleVariantAccordion(btn) {
            // Vind de tr van het product
            var tr = btn.closest('tr');
            // Vind de next tr (de accordion-row)
            var accordionRow = document.getElementById(btn.getAttribute('data-target')) || tr.nextElementSibling;
            if (!accordionRow || !accordionRow.classList.contains('variant-accordion-row')) return;
            var isOpen = accordionRow.style.display !== 'none';
            accordionRow.style.display = isOpen ? 'none' : '';
            btn.classList.toggle('open', !isOpen);
            if (!isOpen) {
                accordionRow.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }

        // On load: open accordion indien query param 'open' aanwezig of hash present
        (function() {
            function openFromQueryOrHash() {
                var hash = window.location.hash;
                if (hash) {
                    var id = hash.substring(1); // zonder '#'
                    var targetBtn = document.querySelector('[data-target="' + id + '"]');
                    if (targetBtn) {
                        toggleVariantAccordion(targetBtn);
                        return;
                    }
                    var row = document.getElementById(id);
                    if (row && row.classList.contains('variant-accordion-row')) {
                        var prev = row.previousElementSibling;
                        if (prev) {
                            var btn = prev.querySelector('.variant-accordion-toggle');
                            if (btn) toggleVariantAccordion(btn);
                        }
                        return;
                    }
                }
                var params = new URLSearchParams(window.location.search);
                var openId = params.get('open');
                if (openId) {
                    var btn = document.querySelector('[data-target="variant-' + openId + '"]') || document.querySelector('[data-target="product-' + openId + '"]');
                    if (btn) toggleVariantAccordion(btn);
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', openFromQueryOrHash);
            } else {
                openFromQueryOrHash();
            }
        })();

        // Click outside modal to close
        document.getElementById('stock-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStockModal();
            }
        });
        document.getElementById('variant-stock-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeVariantStockModal();
            }
        });

        const producten = <?= json_encode(array_values($artikelen)) ?>;
        const input = document.getElementById('search-input');
        const datalist = document.getElementById('product-suggesties');

        input.addEventListener('input', function() {
            const val = this.value.toLowerCase();
            datalist.innerHTML = '';
            if (val.length === 0) return;
            producten.forEach(function(naam) {
                if (naam.toLowerCase().includes(val)) {
                    const opt = document.createElement('option');
                    opt.value = naam;
                    datalist.appendChild(opt);
                }
            });
        });
    </script>
</body>

</html>