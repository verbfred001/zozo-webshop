<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/zozo-vertalingen.php";

// Ensure language variables are available
include_once $_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/lang.php';

// Detecteer taal uit URL of standaard naar 'nl' (gebruik activelanguage voor branded slugs)
if (!isset($lang)) {
    $lang = function_exists('activelanguage') ? activelanguage() : 'nl';
}

// Categorieën en helpers beschikbaar maken
include $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/zozo-categories.php";

// Voorraadinstellingen ophalen
$stmt_settings = $mysqli->prepare("SELECT voorraadbeheer FROM instellingen LIMIT 1");
$stmt_settings->execute();
$settings = $stmt_settings->get_result()->fetch_assoc();
$voorraadbeheer = $settings['voorraadbeheer'] ?? 1;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Winkelwagen</title>
    <link rel="stylesheet" href="/zozo-assets/css/zozo-main.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-navbar.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-topbar.css">
    <link rel="stylesheet" href="/zozo-assets/css/zozo-products.css">
    <script src="/zozo-assets/js/zozo-product-shared.js" defer></script>
    <style>
        .cart-table-wrap {
            width: 100%;
        }

        /* Allow horizontal scrolling for wide tables only on larger screens */
        @media (min-width: 700px) {
            .cart-table-wrap {
                overflow-x: auto;
            }
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 1em;
            background: #fff;
        }

        .cart-table th,
        .cart-table td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: none;
            /* moved to row level to avoid vertical seams */
        }

        .cart-table tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }

        .cart-table th {
            background: #f4f6fa;
            color: #1f2937;
            font-weight: 600;
            font-size: 1.05em;
        }

        .cart-table td.cart-item-price {
            color: #000;
            /* prices should appear black on cart page */
            font-weight: 500;
            text-align: right;
        }

        .cart-table td.cart-item-qty {
            text-align: center;
            color: #64748b;
        }

        .cart-total-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            font-size: 1.08em;
            font-weight: 600;
            margin-top: 8px;
        }

        .cart-total-label {
            color: #232323;
        }

        .cart-total-value {
            color: #000;
            /* cart total in black */
        }

        .cart-qty-input {
            width: 48px;
            text-align: center;
            font-size: 1em;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 3px 6px;
        }

        .cart-remove-btn {
            background: none;
            border: none;
            color: #ef4444;
            font-size: 1.2em;
            cursor: pointer;
        }

        /* Checkout button: use the same green as modal/detail buttons */
        .cart-checkout-btn {
            background: #00b900;
            color: #fff;
            padding: 12px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            justify-content: center;
        }

        .cart-checkout-btn:hover {
            background: #008500;
        }

        .cart-checkout-icon {
            stroke: currentColor;
            fill: none;
        }
    </style>
</head>

<body>
    <?php
    include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-topbar.php";
    include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-navbar.php";
    include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-breadcrumbs.php";
    ?>
    <main class="products-main">
        <h1 style="margin-bottom:24px;color:#000;font-size:1.5em;">
            <?= $translations['Winkelwagen'][$lang] ?? 'Winkelwagen' ?>
        </h1>
        <div id="cart-content"></div>
    </main>
    <div style="height: 220px;"></div>
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/zozo-templates/zozo-footer.php"; ?>

    <!-- Cart-script hier -->
    <script>
        window.cartTranslations = {
            naam: "<?= addslashes($translations['Naam'][$lang] ?? 'Naam') ?>",
            aantal: "<?= addslashes($translations['Aantal'][$lang] ?? 'Aantal') ?>",
            prijs: "<?= addslashes($translations['Prijs'][$lang] ?? 'Prijs') ?>",
            verwijder: "<?= addslashes($translations['Verwijder'][$lang] ?? 'Verwijder') ?>",
            leeg: "<?= addslashes($translations['Je winkelwagen is leeg.'][$lang] ?? 'Je winkelwagen is leeg.') ?>",
            totaal: "<?= addslashes($translations['Totaal:'][$lang] ?? 'Totaal:') ?>"
        };

        window.voorraadbeheerActief = <?= $voorraadbeheer == 1 ? 'true' : 'false' ?>;

        // Formatter die het gedrag van PHP's format_price_eur_nosymbol() nabootst
        function formatPriceEurNoSymbol(amount) {
            amount = Number(amount) || 0;
            // Rond af op 2 decimalen
            amount = Math.round(amount * 100) / 100;

            if (amount === 0) return '0,-';

            var euros = Math.floor(amount);
            var cents = Math.round((amount - euros) * 100);
            if (cents === 0) return euros + ',-';

            // Zorg voor twee decimals en vervang decimale punt door komma
            var parts = amount.toFixed(2).split('.');
            var intPart = parts[0];
            var decPart = parts[1];
            // Voeg duizendtalscheiding toe (punt)
            intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return intPart + ',' + decPart;
        }

        function renderCart() {
            const isMobileView = window.matchMedia('(max-width: 699px)').matches;
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            let container = document.getElementById('cart-content');
            if (cart.length === 0) {
                container.innerHTML = `<p>${window.cartTranslations.leeg}</p>`;
                return;
            }
            // Build both desktop table and mobile cards. Desktop will be visible on wide screens; mobile on small screens.
            let html = `
        <div class="cart-table-wrap">
            <table class="cart-table">
                <tbody>
    `;
            cart.forEach((item, idx) => {
                // Bepaal voorraadOptiesString zoals op de productpagina
                let voorraadOptiesString = (item.options || [])
                    .filter(o => o.affects_stock == 1)
                    .map(o => o.group_name + ':' + (o.option_id || o.value))
                    .sort()
                    .join('|');
                // desktop row (includes a placeholder cell for thumb that will be filled by JS)
                html += `
<tr>
<td class="cart-item-thumb-cell" data-productid="${item.product_id}"><img src="" alt="" loading="lazy"></td>
<td class="cart-item-name">
    ${item.name} <span class="cart-item-unit-inline">(${formatPriceEurNoSymbol(Number(item.price) || 0)})</span>
</td>
<td class="cart-item-qty">
    <input type="number" min="1" value="${item.qty}" class="cart-qty-input" data-idx="${idx}" data-productid="${item.product_id}" data-voorraadopties="${voorraadOptiesString}">
</td>
<td class="cart-item-price">${formatPriceEurNoSymbol((Number(item.price) || 0) * (Number(item.qty) || 0))}</td>
<td><button class="cart-remove-btn" data-idx="${idx}" title="Verwijder">&#10005;</button></td>
</tr>
`;

                // mobile card (only generate when viewport is small)
                if (isMobileView) {
                    html += `
<div class="cart-item-card" data-idx="${idx}" data-productid="${item.product_id}">
    <img class="cart-item-thumb" src="" alt="" loading="lazy">
    <div class="cart-item-main">
        <!-- first line: photo + name -->
        <div class="cart-item-first-row">
            <div style="min-width:0">
        <div class="cart-item-name">${item.name} <span class="cart-item-unit-inline">(${formatPriceEurNoSymbol(Number(item.price) || 0)})</span></div>
            </div>
        </div>
        <!-- second line: unit price (left) and qty + remove (right) -->
        <div class="cart-item-second-row">
            <div class="cart-item-meta"><span class="cart-item-unit-price">${formatPriceEurNoSymbol(Number(item.price) || 0)}</span></div>
            <div class="cart-item-actions">
                <input type="number" min="1" value="${item.qty}" class="cart-qty-input" data-idx="${idx}" data-productid="${item.product_id}" data-voorraadopties="${voorraadOptiesString}" style="width:64px;text-align:center;">
                <button class="cart-remove-btn" data-idx="${idx}" title="Verwijder">&#10005;</button>
            </div>
        </div>
    </div>
</div>
`;
                }
            });
            html += `
                </tbody>
            </table>
            <div class="cart-total-row">
                <span class="cart-total-label">${window.cartTranslations.totaal}</span>
                <span class="cart-total-value">
                    ${formatPriceEurNoSymbol(cart.reduce((sum, item) => sum + (Number(item.price) || 0) * (Number(item.qty) || 0), 0))}
                </span>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                <a href="/<?= $lang ?>/checkout" id="checkout-btn" class="cart-checkout-btn" aria-label="<?= htmlspecialchars($translations['Ik ga bestellen'][$lang] ?? 'Ik ga bestellen', ENT_QUOTES) ?>">
                    <svg class="cart-checkout-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span class="cart-checkout-label"><?= htmlspecialchars($translations['Ik ga bestellen'][$lang] ?? 'Ik ga bestellen') ?></span>
                </a>
            </div>
            </div>
                `;
            container.innerHTML = html;

            // If the viewport might change (resize), re-render when crossing breakpoint
            // (debounced below to avoid thrash)


            // After DOM insert: fetch and populate thumbnails for both table rows and mobile cards
            (function() {
                const placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
                window._zozoCartImageCache = window._zozoCartImageCache || {};
                const cache = window._zozoCartImageCache;

                const els = Array.from(document.querySelectorAll('[data-productid]'));
                const byPid = {};
                els.forEach(el => {
                    const pid = el.dataset.productid;
                    if (!pid) return;
                    if (!byPid[pid]) byPid[pid] = [];
                    byPid[pid].push(el);
                });

                Object.keys(byPid).forEach(pid => {
                    const nodes = byPid[pid];
                    // set placeholder immediately
                    nodes.forEach(el => {
                        const img = el.tagName === 'TD' ? el.querySelector('img') : el.querySelector('img.cart-item-thumb');
                        if (img) img.src = placeholder;
                    });

                    if (typeof cache[pid] !== 'undefined') {
                        if (cache[pid]) {
                            nodes.forEach(el => {
                                const img = el.tagName === 'TD' ? el.querySelector('img') : el.querySelector('img.cart-item-thumb');
                                if (img) {
                                    img.src = cache[pid];
                                    img.alt = '';
                                }
                            });
                        }
                        return;
                    }

                    fetch('/zozo-includes/get_product_image.php?id=' + encodeURIComponent(pid), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(r => {
                        if (r.status === 403) {
                            cache[pid] = null; // avoid retries this page
                            return null;
                        }
                        return r.json();
                    }).then(data => {
                        if (!data) return;
                        if (data && data.image) {
                            cache[pid] = data.image;
                            nodes.forEach(el => {
                                const img = el.tagName === 'TD' ? el.querySelector('img') : el.querySelector('img.cart-item-thumb');
                                if (img) {
                                    img.src = data.image;
                                    img.alt = '';
                                }
                            });
                        } else {
                            cache[pid] = null;
                        }
                    }).catch(e => {
                        cache[pid] = null;
                    });
                });
            })();

            // Zet het juiste max-attribuut en controleer realtime
            document.querySelectorAll('.cart-qty-input').forEach(input => {
                let productId = input.dataset.productid;
                let voorraadOptiesString = input.dataset.voorraadopties;
                let idx = parseInt(input.dataset.idx);

                function setMaxQty() {
                    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
                    let maxQty = cart[idx].qty;
                    if (window.voorraadbeheerActief) {
                        input.max = maxQty;
                        if (parseInt(input.value, 10) > maxQty) input.value = maxQty;
                    } else {
                        input.removeAttribute('max');
                    }
                }

                setMaxQty();

                // Realtime check bij wijzigen — debounced, update only the necessary DOM parts and localStorage
                (function() {
                    function debounce(fn, wait) {
                        let t = null;
                        return function(...args) {
                            clearTimeout(t);
                            t = setTimeout(() => fn.apply(this, args), wait);
                        };
                    }

                    input.addEventListener('input', debounce(function() {
                        let cart = JSON.parse(localStorage.getItem('cart') || '[]');
                        // ensure backward compatibility: if item lacks btw, set default 21
                        if (typeof cart[idx].btw === 'undefined') cart[idx].btw = 21;
                        let newQty = parseInt(input.value, 10);
                        if (isNaN(newQty) || newQty < 1) newQty = 1;
                        cart[idx].qty = newQty;
                        localStorage.setItem('cart', JSON.stringify(cart));
                        setMaxQty();

                        // Keep all qty inputs for this item in sync (table + card)
                        document.querySelectorAll('.cart-qty-input[data-idx="' + idx + '"]').forEach(i => {
                            if (i !== input) i.value = newQty;
                        });

                        // Update subtotal for the corresponding table row (if present)
                        try {
                            const unitPrice = Number(cart[idx].price) || 0;
                            const itemSubtotal = unitPrice * newQty;

                            const tr = input.closest('tr');
                            if (tr) {
                                const priceEl = tr.querySelector('.cart-item-price');
                                if (priceEl) priceEl.textContent = formatPriceEurNoSymbol(itemSubtotal);
                            }

                            // Update mobile card subtotal (if present)
                            const card = document.querySelector('.cart-item-card[data-idx="' + idx + '"]');
                            if (card) {
                                const priceEl = card.querySelector('.cart-item-price');
                                if (priceEl) priceEl.textContent = formatPriceEurNoSymbol(itemSubtotal);
                            }

                            // Update global total
                            const totalEl = document.querySelector('.cart-total-value');
                            if (totalEl) {
                                const newTotal = cart.reduce((sum, it) => sum + (Number(it.price) || 0) * (Number(it.qty) || 0), 0);
                                totalEl.textContent = formatPriceEurNoSymbol(newTotal);
                            }
                        } catch (e) {
                            // fallback: if anything unexpected happens, re-render the cart once
                            try {
                                renderCart();
                            } catch (er) {
                                /* silent */
                            }
                        }

                        updateCartBadge && updateCartBadge();
                    }, 180));
                })();
            });

            // Event listeners voor verwijderen
            document.querySelectorAll('.cart-remove-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    let idx = parseInt(this.dataset.idx);
                    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
                    cart.splice(idx, 1);
                    // ensure remaining items have btw field
                    cart = cart.map(it => (typeof it.btw === 'undefined') ? Object.assign({
                        btw: 21
                    }, it) : it);
                    localStorage.setItem('cart', JSON.stringify(cart));
                    renderCart();
                    updateCartBadge();
                });
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            renderCart();
            updateCartBadge && updateCartBadge();
        });

        // Re-render cart when crossing mobile/desktop breakpoint
        (function() {
            let lastIsMobile = window.matchMedia('(max-width: 699px)').matches;

            function checkResize() {
                const nowIsMobile = window.matchMedia('(max-width: 699px)').matches;
                if (nowIsMobile !== lastIsMobile) {
                    lastIsMobile = nowIsMobile;
                    renderCart();
                }
            }
            let t = null;
            window.addEventListener('resize', function() {
                clearTimeout(t);
                t = setTimeout(checkResize, 120);
            });
        })();
    </script>
</body>

</html>