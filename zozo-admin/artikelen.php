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
                            window.location.href = '/admin/artikelen';
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

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-admin/includes/voorraad_artikelen_aanmaken.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-admin/includes/functions.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-admin/includes/function_tel_voorraad_varianten.php");

// --- NIEUW ARTIKEL TOEVOEGEN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nieuw_artikel'])) {
    $naam = trim($_POST['art_naam'] ?? '');
    $naam_fr = trim($_POST['art_naam_fr'] ?? '');
    $naam_en = trim($_POST['art_naam_en'] ?? '');
    // Als de Franse/Engelse naamvelden leeg zijn, standaard de Nederlandse naam bewaren
    if ($naam_fr === '') {
        $naam_fr = $naam;
    }
    if ($naam_en === '') {
        $naam_en = $naam;
    }
    $kenmerk = trim($_POST['art_kenmerk'] ?? '');
    $kenmerk_fr = trim($_POST['art_kenmerk_fr'] ?? '');
    $kenmerk_en = trim($_POST['art_kenmerk_en'] ?? '');
    $afkorting = generateSlug($naam);
    $afkorting_fr = generateSlug($naam_fr ?: $naam);
    $afkorting_en = generateSlug($naam_en ?: $naam);
    $omschrijving = trim($_POST['art_omschrijving'] ?? '');
    $omschrijving_fr = trim($_POST['art_omschrijving_fr'] ?? '');
    $omschrijving_en = trim($_POST['art_omschrijving_en'] ?? '');
    $kostprijs = floatval($_POST['art_kostprijs'] ?? 0);
    $oudeprijs = floatval($_POST['art_oudeprijs'] ?? 0);
    $btw = intval($_POST['art_BTWtarief'] ?? 21);
    $aantal = intval($_POST['art_aantal'] ?? 500);
    $catID = intval($_POST['art_catID'] ?? 0);
    $levertijd = intval($_POST['art_levertijd'] ?? 0);
    $online = "nee";
    // Normalize flags to integers (0/1). Accept legacy 'ja' as true.
    $topzoekertje = 0;
    if (isset($_POST['art_indekijker'])) {
        $raw = $_POST['art_indekijker'];
        if ($raw === '1' || $raw === 1 || strtolower((string)$raw) === 'ja') $topzoekertje = 1;
    }
    $suggestie = 0;
    if (isset($_POST['art_suggestie'])) {
        $raw = $_POST['art_suggestie'];
        if ($raw === '1' || $raw === 1 || strtolower((string)$raw) === 'ja') $suggestie = 1;
    }
    // By default we set art_weergeven = 1 (visible) unless explicitly set otherwise
    $weergeven = 1;
    if (isset($_POST['art_weergeven'])) {
        $raw = $_POST['art_weergeven'];
        if (!($raw === '1' || $raw === 1 || strtolower((string)$raw) === 'ja')) {
            $weergeven = 0;
        }
    }
    $afbeelding = intval($_POST['art_afbeelding'] ?? 1);

    // Voeg hier eventueel meer velden toe

    $stmt = $mysqli->prepare("        INSERT INTO products (
            art_naam, art_naam_fr, art_naam_en,
            art_kenmerk, art_kenmerk_fr, art_kenmerk_en,
            art_afkorting, art_afkorting_fr, art_afkorting_en,
            art_omschrijving, art_omschrijving_fr, art_omschrijving_en,
            art_kostprijs, art_oudeprijs, art_BTWtarief, art_aantal,
            art_catID, art_levertijd, art_indekijker,
                art_suggestie, art_weergeven, art_afbeelding
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        // 13 strings, 2 doubles, 8 integers
        "ssssssssssssddiiiiiiii",
        $naam,
        $naam_fr,
        $naam_en,
        $kenmerk,
        $kenmerk_fr,
        $kenmerk_en,
        $afkorting,
        $afkorting_fr,
        $afkorting_en,
        $omschrijving,
        $omschrijving_fr,
        $omschrijving_en,
        $kostprijs,
        $oudeprijs,
        $btw,
        $aantal,
        $catID,
        $levertijd,
        $topzoekertje,
        $suggestie,
        $weergeven,
        $afbeelding
    );
    $stmt->execute();

    // Haal het nieuw aangemaakte artikel id
    $new_art_id = $mysqli->insert_id;

    // Haal voorraadbepalende groepen op voor deze categorie
    $group_ids = [];
    $sql = "SELECT og.group_id
        FROM category_option_groups cog
        JOIN option_groups og ON og.group_id = cog.group_id
        WHERE cog.cat_id = $catID AND og.affects_stock = 1";
    $res = $mysqli->query($sql);
    while ($row = $res->fetch_assoc()) {
        $group_ids[] = $row['group_id'];
    }
    if (!empty($group_ids)) {
        // Create only missing voorraad combinations for the newly inserted article
        createVoorraadCombinatiesVoorArtikel($mysqli, $new_art_id, $catID, $group_ids);
    }

    // Optioneel: melding of redirect
    header("Location: /admin/artikelen?nieuw=ok");
    exit;
}

// Haal alle categorieën op voor de filter
$catResult = $mysqli->query("SELECT cat_id, cat_naam, cat_top_sub FROM category ORDER BY cat_top_sub, cat_volgorde");
$categories = [];
while ($row = $catResult->fetch_assoc()) {
    $categories[] = $row;
}

// Haal voorraadbeheer instelling (gebruik in meerdere templates)
$instellingenRow = $mysqli->query("SELECT voorraadbeheer FROM instellingen LIMIT 1")->fetch_assoc() ?: [];
$voorraadbeheer = !empty($instellingenRow['voorraadbeheer']) && $instellingenRow['voorraadbeheer'] != 0;

// Zoek/filter
$zoek = $_GET['zoek'] ?? '';
$catFilter = $_GET['cat'] ?? '';

// Query opbouwen
$where = [];
if ($zoek) {
    $zoekEsc = $mysqli->real_escape_string($zoek);
    $where[] = "(art_naam LIKE '%$zoekEsc%' OR art_afkorting LIKE '%$zoekEsc%')";
}
if ($catFilter) {
    $catFilter = intval($catFilter);
    $allCatIds = getAllCategoryIds($categories, $catFilter);
    $catIdList = implode(',', array_map('intval', $allCatIds));
    $where[] = "art_catID IN ($catIdList)";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT * FROM products $whereSql ORDER BY art_catID ASC, art_order ASC, art_naam ASC";
$result = $mysqli->query($sql);

function getAllCategoryIds($categories, $catId)
{
    $ids = [$catId];
    foreach ($categories as $cat) {
        if ($cat['cat_top_sub'] == $catId) {
            $ids = array_merge($ids, getAllCategoryIds($categories, $cat['cat_id']));
        }
    }
    return $ids;
}

// DEBUG: laat zien wat we hebben
echo "<!-- DEBUG artikelen.php - zoek: '$zoek', catFilter: '$catFilter' -->\n";

// Bouw return URL met huidige filters
$returnParams = [];
if ($zoek) $returnParams['zoek'] = $zoek;
if ($catFilter) $returnParams['cat'] = $catFilter;

// Voeg return_anchor toe voor specifiek artikel (wordt later gebruikt)
// Maar niet hier, want we weten nog niet welk artikel
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artikelen beheren</title>
    <link rel="stylesheet" href="/zozo-admin/css/navbar.css">
    <link rel="stylesheet" href="/zozo-admin/css/main.css">
    <link rel="stylesheet" href="/zozo-admin/css/artikelen.css">
</head>

<body class="page-bg">



    <?php include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-admin/templates/navbar.php'); ?>

    <main class="main-container">
        <div class="main-card">
            <!-- Header -->
            <div class="main-card-header flex-between-row">
                <h1 class="main-title">Artikelen beheren</h1>
                <button class="btn btn--add" id="btn-nieuw-artikel" type="button">
                    + Nieuw artikel
                </button>

                <button class="btn btn--main" id="open-filter-modal" type="button">Filter resultaten</button>


            </div>



            <!-- Results info -->
            <?php
            $totalResults = $result->num_rows;
            if ($zoek || $catFilter):
            ?>
                <div class="results-info">
                    <?= $totalResults ?> artikel(en) gevonden
                    <?php if ($zoek): ?>
                        voor "<strong><?= htmlspecialchars($zoek) ?></strong>"
                    <?php endif; ?>
                </div>
            <?php endif; ?>




            <!-- Artikelen kaarten -->
            <div class="main-card">
                <div class="artikel_lijst">
                    <?php if ($totalResults === 0): ?>
                        <div class="artikelen-empty">
                            <div class="artikelen-empty-title">Geen artikelen gevonden</div>
                            <div class="artikelen-empty-info">Pas je zoekopdracht aan of voeg een nieuw artikel toe.</div>
                        </div>
                    <?php else: ?>
                        <?php $result->data_seek(0); ?>
                        <?php while ($art = $result->fetch_assoc()): ?>
                            <div class="artikel_card" id="artikel_<?= $art['art_id'] ?>" data-art-id="<?= $art['art_id'] ?>" data-cat-id="<?= $art['art_catID'] ?>">
                                <div class="artikel_card_img">
                                    <?php
                                    $imgResult = $mysqli->query("SELECT image_name FROM product_images WHERE product_id = " . intval($art['art_id']) . " ORDER BY image_order ASC LIMIT 1");
                                    if ($img = $imgResult->fetch_assoc()) {
                                        echo '<img src="/upload/' . htmlspecialchars($img['image_name']) . '" alt="" class="artikel_img" style="width: 80px; height: 80px; object-fit: cover; border-radius: 6px;">';
                                    } else {
                                        echo '<div class="artikel_img_empty" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
    <svg class="artikel_img_icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 40px; height: 40px;">
        <circle cx="12" cy="12" r="10" stroke-width="2" />
    </svg>
</div>';
                                    }
                                    ?>
                                </div>
                                <div class="artikel_card_info">
                                    <div class="artikel-badges">
                                        <?php if (isset($art['art_indekijker']) && ($art['art_indekijker'] === '1' || $art['art_indekijker'] === 1 || strtolower((string)$art['art_indekijker']) === 'ja')): ?>
                                            <span class="artikel-badge">in de kijker</span>
                                        <?php endif; ?>
                                        <?php if (isset($art['art_suggestie']) && ($art['art_suggestie'] === '1' || $art['art_suggestie'] === 1 || strtolower((string)$art['art_suggestie']) === 'ja')): ?>
                                            <span class="artikel-badge">suggestie</span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="artikel-title"><?= htmlspecialchars($art['art_naam']) ?></h3>
                                    <?php if ($art['art_kenmerk']): ?>
                                        <p class="artikel-kenmerk"><?= htmlspecialchars($art['art_kenmerk']) ?></p>
                                    <?php endif; ?>
                                    <?php
                                    // Categorie pad
                                    $catId = $art['art_catID'];
                                    $categoryPath = [];
                                    $currentCatId = $catId;
                                    $lookup = [];
                                    foreach ($categories as $cat) {
                                        $lookup[$cat['cat_id']] = $cat;
                                    }
                                    while ($currentCatId && isset($lookup[$currentCatId])) {
                                        $categoryPath[] = $lookup[$currentCatId]['cat_naam'];
                                        $currentCatId = $lookup[$currentCatId]['cat_top_sub'];
                                    }
                                    $categoryPath = array_reverse($categoryPath);
                                    if (!empty($categoryPath)):
                                    ?>
                                        <p class="artikel_catpad"><?= htmlspecialchars(implode(' > ', $categoryPath)) ?></p>
                                    <?php endif; ?>
                                    <?php $visible = isset($art['art_weergeven']) && ($art['art_weergeven'] === 1 || $art['art_weergeven'] === '1' || strtolower((string)$art['art_weergeven']) === 'ja'); ?>
                                    <span class="artikel-status <?= $visible ? 'artikel_status_online' : 'artikel_status_offline' ?>">
                                        <?= $visible ? 'Online' : 'Offline' ?>
                                    </span>
                                    <div class="artikel_card_meta">
                                        <?php
                                        $art_prijs_excl = isset($art['art_kostprijs']) ? floatval($art['art_kostprijs']) : 0.0;
                                        $art_btw = isset($art['art_BTWtarief']) ? floatval($art['art_BTWtarief']) : 21.0;
                                        $art_prijs_incl = $art_prijs_excl * (1 + $art_btw / 100);
                                        ?>
                                        <span class="artikel_prijs">€<?= number_format($art_prijs_incl, 2, ',', '.') ?></span>
                                        <?php
                                        // Tel totaal voorraad (sommeert variant-voorraad indien aanwezig, anders art_aantal)
                                        $voorraad_total = tel_voorraad_varianten($mysqli, $art['art_id']);
                                        $voorraad_class = ($voorraad_total <= 0 ? 'artikel_voorraad_low' : ($voorraad_total < 5 ? 'artikel_voorraad_warn' : ''));
                                        ?>
                                        <?php if ($voorraadbeheer): ?>
                                            <span class="artikel_voorraad <?= $voorraad_class ?>">Voorraad: <?= $voorraad_total ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <a class="artikel_btn_bewerken" href="/admin/detail?id=<?= $art['art_id'] ?>&zoek=<?= urlencode($zoek) ?>&cat=<?= urlencode($catFilter) ?>">Bewerken</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Include de modals -->
            <?php
            // Talen ophalen uit instellingen
            $talen = ['nl']; // Nederlands is altijd aanwezig
            $instellingenResult = $mysqli->query("SELECT talen_fr, talen_en FROM instellingen LIMIT 1");
            if ($instellingenResult && $instellingen = $instellingenResult->fetch_assoc()) {
                if (!empty($instellingen['talen_fr'])) $talen[] = 'fr';
                if (!empty($instellingen['talen_en'])) $talen[] = 'en';
            }

            // nu is $talen bv. ['nl', 'fr', 'en'] of ['nl', 'en'] of ['nl']
            // Nu kun je veilig de modals includen:
            include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-admin/templates/artikel_modals.php');
            ?>



            <script>
                function openBewerkArtikelModal(artikelId) {
                    // Laad artikelgegevens en toon bewerk modal
                    // Dit kan via AJAX of direct naar een bewerk-pagina
                    document.getElementById('bewerk-artikel-modal').classList.remove('hidden');
                    // Vul formulier met artikel data (via AJAX)
                    loadArtikelData(artikelId);
                }

                function closeModal(modalId) {
                    document.getElementById(modalId).classList.add('hidden');
                }

                // Sluit modal bij klik buiten modal
                document.addEventListener('click', function(e) {
                    if (e.target.classList.contains('modal-overlay')) {
                        e.target.classList.add('hidden');
                    }
                });

                // AJAX functie om artikel data te laden (optioneel)
                function loadArtikelData(artikelId) {
                    // Implementeer AJAX call om artikel data op te halen
                    // En vul het bewerk formulier in de modal
                }

                // Categories functionaliteit

                // Scroll position functies
                function saveScrollPosition() {
                    sessionStorage.setItem('artikelenScrollY', window.scrollY);
                }

                // Restore scroll position en anchor
                document.addEventListener('DOMContentLoaded', function() {
                    const btnNieuwArtikel = document.getElementById('btn-nieuw-artikel');
                    if (btnNieuwArtikel) {
                        btnNieuwArtikel.addEventListener('click', function() {
                            const modal = document.getElementById('nieuw-artikel-modal');
                            if (modal) modal.classList.remove('hidden');
                        });
                    }

                    // Bewerk artikel buttons
                    document.querySelectorAll('.artikel-btn-bewerk').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const artikelId = this.getAttribute('data-artikel-id');
                            openBewerkArtikelModal(artikelId);
                        });
                    });

                    // Dan scroll/anchor handling
                    const urlParams = new URLSearchParams(window.location.search);
                    const anchor = urlParams.get('anchor') || urlParams.get('return_anchor');

                    if (anchor) {
                        const element = document.getElementById(anchor);
                        if (element) {
                            setTimeout(() => {
                                element.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                                // Highlight effect
                                element.style.backgroundColor = '#dbeafe';
                                setTimeout(() => element.style.backgroundColor = '', 2000);
                            }, 100);
                            return;
                        }
                    }

                    // Restore scroll position if no anchor
                    const savedY = sessionStorage.getItem('artikelenScrollY');
                    if (savedY) {
                        window.scrollTo(0, parseInt(savedY));
                        sessionStorage.removeItem('artikelenScrollY');
                    }
                });

                // Sla huidige pagina en filters op in session voor return URL
                <?php
                // Sla huidige pagina en filters op in session voor return URL
                $currentUrl = 'artikelen.php';
                $currentParams = [];
                if ($zoek) $currentParams['zoek'] = $zoek;
                if ($catFilter) $currentParams['cat'] = $catFilter;

                if (!empty($currentParams)) {
                    $currentUrl .= '?' . http_build_query($currentParams);
                }

                $_SESSION['return_to_artikelen'] = $currentUrl;
                ?>
            </script>

            <?php // JS voor cascade selectie voor categorieen 
            ?>
            <?php // einde JS voor cascade selectie voor categorieen 
            ?>
            <script src="/zozo-admin/js/artikel-modals.js"></script>
            <script>
                document.getElementById('open-filter-modal').addEventListener('click', function() {
                    document.getElementById('filter-modal').classList.remove('hidden');
                });

                function closeModal(id) {
                    document.getElementById(id).classList.add('hidden');
                }
            </script>


            <!-- Filter Modal -->
            <div class="modal-overlay hidden" id="filter-modal">
                <div class="modal">
                    <div class="modal-header">
                        <h2>Filter artikelen</h2>
                        <button type="button" class="modal-close" onclick="closeModal('filter-modal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="get" class="form-filter">
                            <div class="form-filter-fields">
                                <!-- Zoek veld -->
                                <div class="form-filter-field">
                                    <label class="form-label">Zoeken:</label>
                                    <input type="text" name="zoek" placeholder="Zoek artikel..." value="<?= htmlspecialchars($zoek) ?>" class="form-input">
                                </div>
                                <!-- Categorie veld -->
                                <div class="form-filter-field">
                                    <label class="form-label">Categorie:</label>
                                    <select name="cat" class="form-input">
                                        <option value="">-- Kies categorie --</option>
                                        <?php
                                        // Bouw een boomstructuur
                                        function buildCategoryTree($categories, $parentId = 0)
                                        {
                                            $branch = [];
                                            foreach ($categories as $cat) {
                                                if ($cat['cat_top_sub'] == $parentId) {
                                                    $children = buildCategoryTree($categories, $cat['cat_id']);
                                                    if ($children) {
                                                        $cat['children'] = $children;
                                                    }
                                                    $branch[] = $cat;
                                                }
                                            }
                                            return $branch;
                                        }
                                        $categoryTree = buildCategoryTree($categories);

                                        // Toon opties met padnotatie
                                        function renderCategoryOptions($tree, $prefix = '', $selected = '')
                                        {
                                            foreach ($tree as $cat) {
                                                $catId = $cat['cat_id'];
                                                $catNaam = $cat['cat_naam'];
                                                $isSelected = ($selected == $catId) ? 'selected' : '';
                                                echo '<option value="' . $catId . '" ' . $isSelected . '>' . $prefix . $catNaam . '</option>';
                                                if (!empty($cat['children'])) {
                                                    renderCategoryOptions($cat['children'], $prefix . $catNaam . ' - ', $selected);
                                                }
                                            }
                                        }
                                        renderCategoryOptions($categoryTree, '', $catFilter);
                                        ?>
                                    </select>

                                </div>
                                <!-- Knoppen -->
                                <div class="form-filter-actions">
                                    <button type="submit" class="btn btn--main">Zoeken</button>
                                    <?php if ($zoek || $catFilter): ?>
                                        <a href="/admin/artikelen" class="btn btn--gray">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>


            <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var el = document.querySelector('.artikel_lijst');
                    if (el) {
                        // Geef de huidige categorie-filter mee (indien aanwezig) zodat de server weet welke scope geordend wordt
                        var currentCategory = <?= json_encode($catFilter ? intval($catFilter) : null) ?>;

                        new Sortable(el, {
                            animation: 150,
                            onMove: function(evt, originalEvent) {
                                try {
                                    var dragged = evt.dragged;
                                    var related = evt.related; // element you're trying to drop before/after
                                    if (related && dragged && dragged.dataset && related.dataset) {
                                        var dragCat = dragged.dataset.catId;
                                        var relCat = related.dataset.catId;
                                        // Disallow move if categories differ
                                        if (dragCat !== relCat) return false;
                                    }
                                } catch (e) {
                                    // If any error, allow move (fail open)
                                }
                                return true;
                            },
                            onEnd: function(evt) {
                                // Verzamel de nieuwe volgorde
                                var ids = Array.from(el.children).map(card => card.getAttribute('data-art-id'));
                                // Verstuur via AJAX naar PHP (inclusief category context)
                                fetch('/zozo-admin/includes/artikelen_order_update.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        order: ids,
                                        category: currentCategory
                                    })
                                }).then(res => res.json()).then(data => {
                                    if (data && data.status === 'ok') {
                                        console.log('Artikelen volgorde opgeslagen');
                                    } else {
                                        console.error('Fout bij opslaan volgorde', data);
                                    }
                                }).catch(err => console.error('Network error saving order', err));
                            }
                        });
                    }
                });
            </script>
</body>

</html>