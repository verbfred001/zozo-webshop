<!-- Bovenste rij: navigatie + cart -->
<div class="topbar-row topbar-row-nav">
    <div class="lang-bar-left">
        <?php
        $currentPath = $_SERVER['REQUEST_URI'];
        // Use only the path portion (ignore query string)
        $currentPathOnly = parse_url($currentPath, PHP_URL_PATH) ?: $currentPath;
        // ensure language variables are present
        @include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/lang.php');
        $activeLang = $lang ?? 'nl';
        if ($currentPath === '/bienvenue') $activeLang = 'fr';
        elseif ($currentPath === '/welcome') $activeLang = 'en';
        elseif ($currentPath === '/welkom') $activeLang = 'nl';

        // haal talen-instellingen op (fallback: toon alle talen als DB niet beschikbaar)
        if (!isset($mysqli)) {
            @include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/DB_connectie.php');
        }
        $show_fr = true;
        $show_en = true;
        if (isset($mysqli)) {
            $row = $mysqli->query("SELECT talen_fr, talen_en FROM instellingen LIMIT 1")->fetch_assoc() ?: [];
            $show_fr = !empty($row['talen_fr']);
            $show_en = !empty($row['talen_en']);
        }

        // ensure $new_paths exists for pages that don't build it
        if (!isset($new_paths) || !is_array($new_paths)) {
            $new_paths = [];
            $path = ltrim($currentPath ?? '', '/');
            $parts = $path === '' ? [] : explode('/', $path);
            if (in_array($parts[0] ?? '', ['nl', 'fr', 'en'])) {
                array_shift($parts);
            }
            $base = implode('/', $parts);
            foreach ($langs as $taal_code => $_) {
                $new_paths[$taal_code] = $base;
            }
        }

        $langLinks = [];
        foreach ($langs as $k => $v) {
            // altijd NL tonen; FR/EN alleen als actief in instellingen
            if ($k === 'fr' && !$show_fr) continue;
            if ($k === 'en' && !$show_en) continue;

            if (preg_match('#^/(nl|fr|en)/cart$#', $currentPath)) {
                $url = '/' . $k . '/cart';
            } elseif (preg_match('#^/(welkom|bienvenue|welcome)$#', $currentPath)) {
                $url = ($k === 'nl') ? '/welkom' : (($k === 'fr') ? '/bienvenue' : '/welcome');
            } else {
                $part = isset($new_paths[$k]) && $new_paths[$k] !== '' ? '/' . ltrim($new_paths[$k], '/') : '';
                $url = '/' . $k . $part;
            }
            $langLinks[] = '<a href="' . htmlspecialchars($url) . '" class="topnav-lang' . ($activeLang === $k ? ' active' : '') . '">' . strtoupper($k) . '</a>';
        }

        // Alleen tonen als er meer dan één taallink is (anders geen pipe/taal tonen)
        if (count($langLinks) > 1) {
            // wrap in a container so we can align it via CSS with the breadcrumb
            echo '<span class="lang-links">' . implode(' ', $langLinks) . '</span>';
        }
        ?>
    </div>
    <div class="lang-bar-right">
        <?php
        // Hide cart button on checkout page (with or without language prefix)
        $show_cart = true;
        if (preg_match('#^/(nl|fr|en)/checkout(/|$)#', $currentPathOnly) || preg_match('#^/checkout(/|$)#', $currentPathOnly)) {
            $show_cart = false;
        }
        if ($show_cart): ?>
            <a href="/<?= $lang ?>/cart" class="cart-btn" aria-label="Winkelwagen" style="position:relative; z-index:10002;">
                <!-- Cart-icoon SVG -->
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <circle cx="9" cy="21" r="1" />
                    <circle cx="20" cy="21" r="1" />
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61l1.38-7.59H6" />
                </svg>
                <span id="cart-badge" class="cart-badge"></span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!--
Onderste rij: logo (verplaatst naar navbar)
<div class="topbar-row topbar-row-logo" style="display: none;">
    <div class="topbar-logo">
        <a href="/">
            <img src="/zozo-assets/img/zozo-webshop.webp" alt="Logo" width="420" height="120" style="height:80px;max-width:280px;">
        </a>
    </div>
</div>
-->

<div id="cart-modal" class="cart-modal hidden">
    <div class="cart-modal-content">
        <button class="cart-modal-close" onclick="closeCartModal()" aria-label="Sluiten">&times;</button>
        <h2>Winkelwagen</h2>
        <div id="cart-modal-body">
            <!-- Hier komen je winkelwagen-items -->
            <p>Je winkelwagen is leeg.</p>
        </div>
    </div>
</div>

<script>
    function openCartModal() {
        let cart = JSON.parse(localStorage.getItem('cart') || '[]');
        let cartBody = document.getElementById('cart-modal-body');
        if (cart.length === 0) {
            cartBody.innerHTML = '<p>Je winkelwagen is leeg.</p>';
        } else {
            let html = `
            <div class="cart-table-wrap">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Naam</th>
                            <th>Aantal</th>
                            <th>Prijs</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            cart.forEach(item => {
                html += `
                    <tr>
                        <td class="cart-item-name">${item.name}</td>
                        <td class="cart-item-qty">${item.qty}</td>
                        <td class="cart-item-price">€ ${item.price.toFixed(2)}</td>
                    </tr>
                `;
            });
            html += `
                    </tbody>
                </table>
                <div class="cart-total-row">
                    <span class="cart-total-label">Totaal:</span>
                    <span class="cart-total-value">
                        € ${cart.reduce((sum, item) => sum + item.price * item.qty, 0).toFixed(2)}
                    </span>
                </div>
            </div>
            `;
            cartBody.innerHTML = html;
        }
        document.getElementById('cart-modal').classList.remove('hidden');
    }

    function closeCartModal() {
        document.getElementById('cart-modal').classList.add('hidden');
    }
</script>

<!-- Zoekresultaten dropdown (verwijderd) -->

<?php
// haal melding op uit instellingen (voeg dit na je bestaande settings-query of nieuw)
$instellingen = $mysqli->query("SELECT * FROM instellingen LIMIT 1")->fetch_assoc() ?: [];
if (!empty($instellingen['melding_actief'])) {
    // select language-specific message when available, fallback to Dutch
    $msg = $instellingen['melding_tekst'] ?? '';
    if (isset($activeLang) && $activeLang === 'fr' && !empty($instellingen['melding_tekst_fr'])) {
        $msg = $instellingen['melding_tekst_fr'];
    } elseif (isset($activeLang) && $activeLang === 'en' && !empty($instellingen['melding_tekst_en'])) {
        $msg = $instellingen['melding_tekst_en'];
    }

    echo '<div style="background:#ff9800;color:#ffffff;padding:8px;text-align:center;border-top:1px solid #ffb74d;font-weight:600;">'
        . htmlspecialchars($msg)
        . '</div>';
}
?>