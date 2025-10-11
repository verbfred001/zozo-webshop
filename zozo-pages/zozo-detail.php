<?php
// filepath: e:\meettoestel.be\zozo-pages\zozo-detail.php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);


$langs = ['nl' => 'Nederlands', 'fr' => 'Français', 'en' => 'English'];

// Detecteer taal uit URL of standaard naar 'nl' (gebruik activelanguage voor branded slugs)
if (!isset($lang)) {
    $lang = function_exists('activelanguage') ? activelanguage() : 'nl';
}

// VOEG HIER TOE:
include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/zozo-vertalingen.php";
include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/zozo-translations-js.php";

// Categorieën en helpers beschikbaar maken
include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/zozo-categories.php";

// Bouw $new_paths voor taalwisseling in topbar
$new_paths = [];
if (!empty($ids) && !empty($cat_slugs)) {
    foreach ($langs as $taal_code => $taal_naam) {
        $path_parts = [];

        // Bouw het pad voor deze taal
        foreach ($ids as $cat_id) {
            if (isset($all_cats[$cat_id])) {
                $cat = $all_cats[$cat_id];
                if ($taal_code === 'fr' && !empty($cat['cat_afkorting_fr'])) {
                    $path_parts[] = $cat['cat_afkorting_fr'];
                } elseif ($taal_code === 'en' && !empty($cat['cat_afkorting_en'])) {
                    $path_parts[] = $cat['cat_afkorting_en'];
                } else {
                    $path_parts[] = $cat['cat_afkorting'] ?: $cat['cat_naam'];
                }
            }
        }

        $new_paths[$taal_code] = implode('/', $path_parts);
    }
}

function get_category_path($cat_id, $all_cats, $lang = 'nl')
{
    $pad = [];
    while ($cat_id && isset($all_cats[$cat_id])) {
        // Kies de juiste veldnaam op basis van taal
        $naam = '';
        if ($lang === 'fr' && !empty($all_cats[$cat_id]['cat_naam_fr'])) {
            $naam = $all_cats[$cat_id]['cat_naam_fr'];
        } elseif ($lang === 'en' && !empty($all_cats[$cat_id]['cat_naam_en'])) {
            $naam = $all_cats[$cat_id]['cat_naam_en'];
        } elseif (!empty($all_cats[$cat_id]['naam'])) {
            $naam = $all_cats[$cat_id]['naam'];
        } elseif (!empty($all_cats[$cat_id]['cat_naam'])) {
            $naam = $all_cats[$cat_id]['cat_naam'];
        } elseif (!empty($all_cats[$cat_id]['cat_naam_nl'])) {
            $naam = $all_cats[$cat_id]['cat_naam_nl'];
        }
        if ($naam) {
            array_unshift($pad, $naam);
        }
        // Stop als parent niet bestaat of leeg is
        if (empty($all_cats[$cat_id]['parent']) && empty($all_cats[$cat_id]['cat_top_sub'])) {
            break;
        }
        // Gebruik 'parent' of 'cat_top_sub' als parent-id
        if (!empty($all_cats[$cat_id]['parent'])) {
            $cat_id = $all_cats[$cat_id]['parent'];
        } elseif (!empty($all_cats[$cat_id]['cat_top_sub'])) {
            $cat_id = $all_cats[$cat_id]['cat_top_sub'];
        } else {
            break;
        }
    }
    return implode(' > ', $pad);
}

// Product ophalen
$id = $_GET['id'] ?? null;
if (!$id) die("Geen product-ID opgegeven.");

$stmt = $mysqli->prepare("SELECT * FROM products WHERE art_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
if (!$product) die("Product niet gevonden.");

// Naam in juiste taal
$naam = '';
if ($lang === 'fr' && !empty($product['art_naam_fr'])) {
    $naam = $product['art_naam_fr'];
} elseif ($lang === 'en' && !empty($product['art_naam_en'])) {
    $naam = $product['art_naam_en'];
} else {
    $naam = $product['art_naam'];
}

// Kenmerk in juiste taal
$kenmerk = '';
if ($lang === 'fr' && !empty($product['art_kenmerk_fr'])) {
    $kenmerk = $product['art_kenmerk_fr'];
} elseif ($lang === 'en' && !empty($product['art_kenmerk_en'])) {
    $kenmerk = $product['art_kenmerk_en'];
} else {
    $kenmerk = $product['art_kenmerk'];
}

// Omschrijving in juiste taal
$omschrijving = '';
if ($lang === 'fr' && !empty($product['art_omschrijving_fr'])) {
    $omschrijving = $product['art_omschrijving_fr'];
} elseif ($lang === 'en' && !empty($product['art_omschrijving_en'])) {
    $omschrijving = $product['art_omschrijving_en'];
} else {
    $omschrijving = $product['art_omschrijving'];
}

// Afbeelding: gebruik upload image wanneer aanwezig, anders lokale SVG placeholder
$img = !empty($product['image_name']) ? '/upload/' . $product['image_name'] : '/zozo-assets/img/placeholder-image.svg';

// Afbeelding
$img = !empty($product['image_name']) ?
    '/upload/' . $product['image_name'] :
    'https://placehold.co/600x400?text=Geen+afbeelding';

// Meerdere afbeeldingen uit product_images-tabel
$images = [];
$stmt_imgs = $mysqli->prepare("SELECT image_name FROM product_images WHERE product_id = ? ORDER BY image_order ASC, id ASC");
$stmt_imgs->bind_param("i", $product['art_id']);
$stmt_imgs->execute();
$res_imgs = $stmt_imgs->get_result();
while ($row = $res_imgs->fetch_assoc()) {
    $images[] = '/upload/' . $row['image_name'];
}
if (empty($images)) {
    $images[] = $img; // Gebruik hoofdafbeelding als fallback
}

// Prijs berekenen (zoals in zozo-products.php)
include_once $_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/price_helpers.php';
$btw = isset($product['art_BTWtarief']) ? floatval($product['art_BTWtarief']) : 21;
$prijs_excl = floatval($product['art_kostprijs']);
$prijs_incl = $prijs_excl * (1 + $btw / 100);

if (round($prijs_incl, 2) == 0) {
    $prijs = $translations['op maat'][$lang] ?? 'op maat';
} else {
    $prijs = format_price_eur_nosymbol(round($prijs_incl, 2));
}

$oldprijs = '';
if (!empty($product['art_oudeprijs']) && $product['art_oudeprijs'] > $product['art_kostprijs']) {
    $oudeprijs_excl = floatval($product['art_oudeprijs']);
    $oudeprijs_incl = $oudeprijs_excl * (1 + $btw / 100);
    if (function_exists('format_price_eur_nosymbol')) {
        $oldprijs = format_price_eur_nosymbol(round($oudeprijs_incl, 2));
    } else {
        $oldprijs = number_format(round($oudeprijs_incl, 2), 2, ',', '.');
    }
}

// Categorie pad voor breadcrumbs
$cat_pad = get_category_path($product['art_catID'], $all_cats, $lang);

require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");

// Voeg dit toe na het ophalen van $product (en nadat $langs is gedefinieerd):

$new_paths = [];
if (!empty($product)) {
    foreach ($langs as $taal_code => $taal_naam) {
        // Stel je URL-structuur is /[taal]/product/[id]/[slug]
        $slug = '';
        if ($taal_code === 'fr' && !empty($product['art_naam_fr'])) {
            $slug = $product['art_naam_fr'];
        } elseif ($taal_code === 'en' && !empty($product['art_naam_en'])) {
            $slug = $product['art_naam_en'];
        } else {
            $slug = $product['art_naam'];
        }
        // Voeg variant/kenmerk toe aan de slug wanneer aanwezig (meertaligheid)
        $variant = '';
        if ($taal_code === 'fr' && !empty($product['art_kenmerk_fr'])) {
            $variant = $product['art_kenmerk_fr'];
        } elseif ($taal_code === 'en' && !empty($product['art_kenmerk_en'])) {
            $variant = $product['art_kenmerk_en'];
        } else {
            $variant = $product['art_kenmerk'] ?? '';
        }

        $slug_base = $slug . (!empty($variant) ? ' ' . $variant : '');

        // small slugify helper: transliterate (iconv) then remove non-alphanum and hyphens
        $slugified = (function ($s) {
            if (!is_string($s)) return '';
            $s = trim($s);
            // lower-case utf8
            $s = mb_strtolower($s, 'UTF-8');
            // Transliterate to ASCII when possible
            if (function_exists('iconv')) {
                $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
            }
            // Replace non alnum with hyphen
            $s = preg_replace('/[^a-z0-9]+/i', '-', $s);
            $s = trim($s, '-');
            $s = strtolower($s);
            return $s;
        })($slug_base);

        $new_paths[$taal_code] = "product/{$product['art_id']}/{$slugified}";
    }
}

// SEO: bouw paginatitel (productnaam - categorie - host) en canonical URL
// category last segment from $cat_pad (which uses ' > ' separator)
$category_last = '';
if (!empty($cat_pad)) {
    $parts = array_map('trim', explode(' > ', $cat_pad));
    $category_last = end($parts) ?: '';
}
$host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/^www\./', '', $_SERVER['HTTP_HOST']) : '';
$components = [];
$components[] = $naam;
// Voeg variant/kenmerk altijd toe wanneer aanwezig (vermijdt identieke titles)
if (!empty($kenmerk)) {
    $components[] = $kenmerk;
}
// Voeg categorie als extra context toe wanneer beschikbaar
if (!empty($category_last)) {
    $components[] = $category_last;
}
// Host toevoegen als laatste element
if (!empty($host)) {
    $components[] = $host;
}
$page_title = implode(' - ', $components);

// canonical: scheme + host + path (remove query string)
$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? 'https' : 'http';
$path = strtok($_SERVER['REQUEST_URI'], '?');
$canonical_url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $path;

?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="canonical" href="<?= htmlspecialchars($canonical_url) ?>">
    <?php if (!empty($omschrijving)): ?>
        <meta name="description" content="<?= htmlspecialchars(mb_substr(strip_tags($omschrijving), 0, 160)) ?>">
    <?php else: ?>
        <meta name="description" content="<?= htmlspecialchars($naam . ($category_last ? ' - ' . $category_last : '')) ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="/zozo-assets/css/zozo-main.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-navbar.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-topbar.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-detail.css">
    <!-- JavaScript -->
    <script src="/zozo-assets/js/zozo-product-shared.js" defer></script>
    <script src="/zozo-assets/js/zozo-product-detailpagina.js" defer></script>

    <script>
        const productId = <?= json_encode($product['art_id']) ?>;
        const basePrice = <?= json_encode(round($prijs_incl, 2)) ?>;
        const productName = <?= json_encode($naam) ?>;
        const productCategory = <?= json_encode($cat_pad) ?>;
    </script>
</head>

<body>
    <?php
    include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-topbar.php";
    include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-navbar.php";
    ?>

    <main class="detail-main">
        <div class="detail-container">
            <!-- FOTO'S LINKS -->
            <div class="detail-images">
                <img id="main-image" class="detail-images-main" src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($naam) ?>">

                <?php if (count($images) > 1): ?>
                    <div class="detail-thumbs">
                        <?php foreach ($images as $idx => $imgSrc): ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                data-idx="<?= $idx ?>"
                                class="<?= $idx === 0 ? 'active' : '' ?>"
                                alt="Foto <?= $idx + 1 ?>">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PRODUCT INFO RECHTS -->
            <div class="detail-info">
                <div class="detail-breadcrumb">
                    <?= htmlspecialchars($cat_pad) ?>
                </div>

                <h1 class="detail-title"><?= htmlspecialchars($naam) ?></h1>

                <?php if (!empty($kenmerk)): ?>
                    <div class="detail-kenmerk"><?= htmlspecialchars($kenmerk) ?></div>
                <?php endif; ?>

                <div class="detail-price-row">
                    <?php if ($oldprijs): ?>
                        <div class="product-oldprice detail-oldprice" aria-hidden="true"><?= htmlspecialchars($oldprijs) ?></div>
                    <?php endif; ?>
                    <div class="detail-price" id="detail-final-price">
                        <?= htmlspecialchars($prijs) ?>
                    </div>
                </div>

                <?php if (!empty($omschrijving)): ?>
                    <div class="detail-description">
                        <?= nl2br(htmlspecialchars($omschrijving)) ?>
                    </div>
                <?php endif; ?>

                <!-- INLINE PRODUCT FORMULIER -->
                <div id="detail-required-hint" style="font-size:0.85em;color:#1f2937;margin-bottom:6px;min-height:18px;"></div>
                <div id="detail-options-container">
                    <!-- Opties worden hier geladen via JS -->
                </div>

                <div id="detail-stock-info" class="voorraad-info" style="display: none;"></div>
                <div class="qty-section">
                    <label for="detail-qty"><?= $translations['Aantal:'][$lang] ?? 'Aantal:' ?></label>
                    <input type="number" id="detail-qty" value="1" min="1">
                </div>

                <div id="detail-error-msg" class="error-msg" style="display: none;"></div>

                <form id="detail-options-form">
                    <button type="submit" id="detail-add-btn" class="add-to-cart-btn">
                        <!-- Use same cart SVG as product-card for visual consistency -->
                        <svg class="detail-add-icon" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="9" cy="21" r="1" />
                            <circle cx="20" cy="21" r="1" />
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61l1.38-7.59H6" />
                        </svg>
                        <span class="detail-add-label"><?= htmlspecialchars($translations['Toevoegen aan winkelwagen'][$lang] ?? 'Toevoegen aan winkelwagen') ?></span>
                    </button>
                </form>
            </div>
        </div>
    </main>

    <?php include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-footer.php"; ?>

    <script src="/zozo-assets/js/option-rule-overrides.js"></script>


</body>

</html>