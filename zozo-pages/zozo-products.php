<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);


$langs = ['nl' => 'Nederlands', 'fr' => 'Français', 'en' => 'English'];

// Detecteer taal uit URL of standaard naar 'nl'
if (!isset($lang)) {
    if (preg_match('#^/(nl|fr|en)(/|$)#', $_SERVER['REQUEST_URI'], $m)) {
        $lang = $m[1];
    } elseif ($_SERVER['REQUEST_URI'] === '/bienvenue') {
        $lang = 'fr';
    } elseif ($_SERVER['REQUEST_URI'] === '/welcome') {
        $lang = 'en';
    } else {
        $lang = 'nl';
    }
}

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
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Producten</title>
    <link rel="stylesheet" href="/zozo-assets/css/zozo-main.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-navbar.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-topbar.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-products.css">
    <style>
        /* Highlight for "in de kijker" products */
        .product-indekijker {
            border: 2px solid #ff9800;
            /* orange border */
            box-shadow: 0 2px 6px rgba(255, 152, 0, 0.08);
        }

        .product-indekijker .product-img-wrap {
            position: relative;
        }

        /* New style: show a simple orange star next to the price when a product is "in de kijker".
           Removed the circular badge in the top-right; instead render a small star without bg.
           The star is absolutely positioned inside the .product-img-wrap so it lines up with the price. */
        .product-img-wrap .product-price-star {
            position: absolute;
            /* keep right distance as requested */
            right: 10px;
            bottom: 45px;
            /* 5px lower than before */
            z-index: 6;
            display: inline-block;
        }

        .product-img-wrap .product-price-star svg {
            width: 36px;
            /* smaller than before */
            height: 36px;
            /* smaller than before */
            fill: #ff9800;
            /* orange star */
            display: block;
        }
    </style>
    <?php
    include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/zozo-vertalingen.php";
    include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/zozo-translations-js.php";
    ?>
    <script src="/zozo-assets/js/zozo-product-shared.js" defer></script>
    <script src="/zozo-assets/js/zozo-product-productpagina.js" defer></script>
</head>

<body>
    <?php
    include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-topbar.php";
    include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-navbar.php";
    ?>


    <?php include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-breadcrumbs.php"; ?>

    <!-- Database producten -->
    <main class="products-main">
        <?php
        // Bepaal paginatitel: gebruik de meest specifieke categorie-naam in de actieve taal wanneer beschikbaar
        $page_title = $translations['Onze producten'][$lang] ?? 'Onze producten';
        if (!empty($ids)) {
            $last_cat_id = end($ids);
            if (!empty($all_cats[$last_cat_id])) {
                $cat = $all_cats[$last_cat_id];
                if ($lang === 'fr' && !empty($cat['cat_naam_fr'])) {
                    $page_title = $cat['cat_naam_fr'];
                } elseif ($lang === 'en' && !empty($cat['cat_naam_en'])) {
                    $page_title = $cat['cat_naam_en'];
                } elseif (!empty($cat['naam'])) {
                    $page_title = $cat['naam'];
                } elseif (!empty($cat['cat_naam'])) {
                    $page_title = $cat['cat_naam'];
                } elseif (!empty($cat['cat_naam_nl'])) {
                    $page_title = $cat['cat_naam_nl'];
                }
            }
        }
        ?>
        <h1 style="margin-bottom:24px;color:#1f2937;font-size:1.5em;"><?= htmlspecialchars($page_title) ?></h1>
        <div class="product-grid">
            <?php
            // Haal producten op uit database
            require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");

            // Haal de huidige categorie-ID uit $ids (laatste element uit centrale include)
            $current_cat_id = !empty($ids) ? end($ids) : null;

            if ($current_cat_id) {
                // Haal alle relevante categorie-IDs op (inclusief subcategorieën)
                $cat_ids = get_all_subcat_ids($current_cat_id, $all_cats);
                $cat_ids_sql = implode(',', array_map('intval', $cat_ids));
                // show products where art_weergeven indicates visible (1),
                // while remaining compatible with legacy values '1' and 'ja'
                // Select the first available image from product_images (order by image_order then id)
                $query = "SELECT p.*, (
                                                    SELECT image_name FROM product_images pi2
                                                    WHERE pi2.product_id = p.art_id
                                                    ORDER BY COALESCE(pi2.image_order, 9999) ASC, pi2.id ASC
                                                    LIMIT 1
                                                ) AS image_name
                                                    FROM products p
                                                    WHERE (p.art_weergeven = 1 OR p.art_weergeven = '1' OR LOWER(p.art_weergeven) = 'ja')
                                                        AND (p.art_weergeven = 1 OR p.art_weergeven = '1' OR LOWER(p.art_weergeven) = 'ja')
                                                        AND p.art_catID IN ($cat_ids_sql)
                                                ORDER BY COALESCE(p.art_order, 0) ASC, p.art_naam ASC";
            } else {
                // Geen categorie in URL: geen producten tonen
                // No category in URL: build a query that returns nothing, but keep image subquery structure
                $query = "SELECT p.*, (
                                                    SELECT image_name FROM product_images pi2
                                                    WHERE pi2.product_id = p.art_id
                                                    ORDER BY COALESCE(pi2.image_order, 9999) ASC, pi2.id ASC
                                                    LIMIT 1
                                                ) AS image_name
                                                    FROM products p
                                                    WHERE 1=0";
            }
            $result = $mysqli->query($query);

            if ($result && $result->num_rows > 0):
                while ($product = $result->fetch_assoc()):
                    // Bepaal productnaam op basis van taal
                    $naam = '';
                    if ($lang === 'fr' && !empty($product['art_naam_fr'])) {
                        $naam = $product['art_naam_fr'];
                    } elseif ($lang === 'en' && !empty($product['art_naam_en'])) {
                        $naam = $product['art_naam_en'];
                    } else {
                        $naam = $product['art_naam'];
                    }

                    // Bepaal kenmerk/feature op basis van taal
                    $feature = '';
                    if ($lang === 'fr' && !empty($product['art_kenmerk_fr'])) {
                        $feature = $product['art_kenmerk_fr'];
                    } elseif ($lang === 'en' && !empty($product['art_kenmerk_en'])) {
                        $feature = $product['art_kenmerk_en'];
                    } else {
                        $feature = $product['art_kenmerk'];
                    }

                    // Afbeelding
                    $img = !empty($product['image_name']) ?
                        '/upload/' . $product['image_name'] :
                        '/zozo-assets/img/placeholder-image.svg';

                    // Prijs formatteren (kostprijs als verkoopprijs)
                    $btw = isset($product['art_BTWtarief']) ? floatval($product['art_BTWtarief']) : 21; // standaard 21% als niet gezet
                    $prijs_excl = floatval($product['art_kostprijs']);
                    $prijs_incl = $prijs_excl * (1 + $btw / 100);
                    // Zorg dat de price helper beschikbaar is vóór elk gebruik (voorkomt "undefined function" wanneer eerdere branch niet include)
                    @include_once $_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/price_helpers.php';
                    if (round($prijs_incl, 2) == 0) {
                        $prijs = $translations['op maat'][$lang] ?? 'op maat';
                    } else {
                        $prijs = format_price_eur_nosymbol(round($prijs_incl, 2));
                    }

                    $oldprijs = '';
                    if (!empty($product['art_oudeprijs']) && $product['art_oudeprijs'] > $product['art_kostprijs']) {
                        $oudeprijs_excl = floatval($product['art_oudeprijs']);
                        $oudeprijs_incl = $oudeprijs_excl * (1 + $btw / 100);
                        // price_helpers.php is al (silently) included boven, dus safe om te formatteren
                        if (function_exists('format_price_eur_nosymbol')) {
                            $oldprijs = format_price_eur_nosymbol(round($oudeprijs_incl, 2));
                        } else {
                            // fallback: eenvoudige formatting (2 decimals, comma)
                            $oldprijs = number_format(round($oudeprijs_incl, 2), 2, ',', '.');
                        }
                    }

                    // Product URL
                    $slug = '';
                    if ($lang === 'fr' && !empty($product['art_afkorting_fr'])) {
                        $slug = $product['art_afkorting_fr'];
                    } elseif ($lang === 'en' && !empty($product['art_afkorting_en'])) {
                        $slug = $product['art_afkorting_en'];
                    } else {
                        $slug = $product['art_afkorting'];
                    }
                    $product_url = '/' . $lang . '/product/' . $product['art_id'] . '/' . $slug;

                    // Bepaal categorie-naam
                    $cat_naam = '';
                    if (!empty($all_cats[$product['art_catID']]['naam'])) {
                        $cat_naam = $all_cats[$product['art_catID']]['naam'];
                    } elseif (!empty($all_cats[$product['art_catID']]['cat_naam'])) {
                        $cat_naam = $all_cats[$product['art_catID']]['cat_naam'];
                    } elseif (!empty($all_cats[$product['art_catID']]['naam_nl'])) {
                        $cat_naam = $all_cats[$product['art_catID']]['naam_nl'];
                    }
            ?>
                    <?php
                    $isIndeKijker = false;
                    if (isset($product['art_indekijker'])) {
                        // support both numeric 1 or string values like '1' or 'ja'
                        $val = $product['art_indekijker'];
                        if ($val === 1 || $val === '1' || strtolower($val) === 'ja') {
                            $isIndeKijker = true;
                        }
                    }
                    ?>
                    <div class="product-card<?= $isIndeKijker ? ' product-indekijker' : '' ?>">
                        <div class="product-img-wrap">
                            <a href="<?= htmlspecialchars($product_url) ?>">
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($naam) ?>">
                            </a>
                            <div class="product-overlay">
                                <span class="product-price"><?= htmlspecialchars($prijs) ?></span>
                                <?php if ($isIndeKijker): ?>
                                    <span class="product-price-star" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <path d="M12 .587l3.668 7.431L23.4 9.75l-5.7 5.566L19.335 24 12 20.013 4.665 24l1.635-8.684L.6 9.75l7.732-1.732L12 .587z" />
                                        </svg>
                                    </span>
                                <?php endif; ?>
                                <?php
                                $cat_pad = get_category_path($product['art_catID'], $all_cats, $lang);
                                echo "<!-- cat_id: {$product['art_catID']} | cat_pad: {$cat_pad} -->";
                                ?>
                            </div>
                        </div>
                        <div class="product-info">
                            <a href="<?= htmlspecialchars($product_url) ?>" class="product-title"><?= htmlspecialchars($naam) ?></a>
                            <div class="product-feature"><?= htmlspecialchars($feature) ?></div>
                            <?php if ($oldprijs): ?>
                                <div class="product-oldprice"><?= htmlspecialchars($oldprijs) ?></div>
                            <?php endif; ?>
                        </div>
                        <!-- Cart button moved out so it's a direct child of .product-card and can be pinned to the corner -->
                        <button class="product-cart-btn"
                            onclick="openProductModal(<?= $product['art_id'] ?>, '<?= htmlspecialchars(addslashes($cat_pad)) ?>')">
                            <!-- Cart-icoon -->
                            <span class="cart-icon-pluswrap">
                                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <circle cx="9" cy="21" r="1" />
                                    <circle cx="20" cy="21" r="1" />
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61l1.38-7.59H6" />
                                </svg>
                                <span class="cart-plus">
                                    <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                                        <circle cx="5.5" cy="5.5" r="5.5" fill="#ff9800" />
                                        <line x1="5.5" y1="3" x2="5.5" y2="8" stroke="#fff" stroke-width="1.5" stroke-linecap="round" />
                                        <line x1="3" y1="5.5" x2="8" y2="5.5" stroke="#fff" stroke-width="1.5" stroke-linecap="round" />
                                    </svg>
                                </span>
                            </span>
                        </button>
                    </div>
                <?php
                endwhile;
            else:
                ?>
                <p style="text-align:center; margin-top:48px; font-size:1.1em; color:#64748b;">
                    <?= $translations['Geen producten voor deze categorie.'][$lang] ?? 'Geen producten voor deze categorie.' ?>
                </p>
            <?php endif; ?>
        </div>
    </main>
    <div style="height: 220px;"></div>

    <?php include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-footer.php"; ?>

    <!-- Product Opties Modal -->
    <div id="product-options-modal" class="cart-modal hidden">
        <div class="cart-modal-content">
            <button class="modal-close" onclick="closeProductModal()" aria-label="Sluiten">&times;</button>
            <h2 id="modal-product-title"></h2>
            <!-- Voeg hier een extra div toe -->
            <div id="modal-required-hint" style="font-size:0.85em;color:#888;margin-bottom:6px;min-height:18px;"></div>
            <form id="modal-options-form">
                <div id="modal-options-fields"></div>
                <div id="modal-stock-info"></div>

                <div class="modal-qty-row">
                    <label for="modal-qty" id="modal-qty-label"></label>
                    <input type="number" id="modal-qty" name="qty" value="1" min="1" style="width:60px;">
                </div>
                <div class="modal-price-row">
                    <span id="modal-final-price"></span>
                </div>
                <div id="modal-error-msg" style="min-height:22px;margin-bottom:8px;"></div>
                <button type="submit" class="modal-add-btn" id="modal-add-btn"></button>
            </form>
        </div>
    </div>


    <script src="/zozo-assets/js/option-rule-overrides.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Guarded init: only initialize when the trigger element for group 58 is present.
            try {
                const selector = `select[name="option_58"], select[data-group-id="58"], input[name="option_58"], input[data-group-id="58"]`;
                const found = document.querySelector(selector);
                if (found && typeof window.initOptionRuleOverrides === 'function') {
                    initOptionRuleOverrides({
                        triggerGroupId: 58,
                        triggerValues: ['180', '209'],
                        targetGroupIds: [59, 60, 61],
                        sentinelValue: '0' // wat de backend als 'geen waarde' mag accepteren
                    });
                } else {
                    // If not found, do nothing. Modal initialization will call init when it injects the modal DOM.
                    console.debug && console.debug('initOptionRuleOverrides: trigger not present on this page, skipping init');
                }
            } catch (e) {
                console.warn('initOptionRuleOverrides guard failed', e);
            }
        });
    </script>


</body>

</html>