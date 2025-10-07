<!-- Wie zijn wij / Welkom bij Maxice -->
<section class="welkom-about">
    <div class="welkom-block welkom-about-block">
        <h2>Welkom bij Maxice.</h2>

        <div class="about-row">
            <div class="about-text">
                <p><strong>uw broodjeszaak, eethuis, take away</strong></p>

                <p>Maxice is een hedendaags, stijlvol en trendy eethuisje met 50 zitplaatsen binnen. Wij serveren voor u dagelijks verse soep, vers belegde broodjes, salades, vers gemaakte burgers, croques,... Al onze bereidingen worden vers gemaakt!</p>

                <p>Wij verzorgen receptiebroodjes voor vergaderingen, verjaardag, feestjes,... die worden gratis aan huis gebracht in groot Roeselare.</p>

                <p>Bestellingen kunnen kosteloos geleverd worden (regio Roeselare) vanaf 5 belegde broodjes... ideaal voor bedrijven!</p>

                <p>Maxice is gekend voor zijn <b>"broodjes op maat"</b> ... meer dan 1000 combinaties mogelijk. Stel zelf je perfecte broodje samen via ons handige bestelsysteem.</p>

                <div class="callout-box takeaway">
                    <h4>Take Away</h4>
                    <p class="callout-important">Bestellingen om te leveren doorgeven voor <strong>10u30</strong>.</p>
                    <p class="callout-important">Bestellingen om af te halen doorgeven voor <strong>11u00</strong>.</p>
                    <p>... vlot bestellen via deze webshop...</p>
                </div>

                <div class="callout-box">
                    <h4>Ter plaatse eten</h4>
                    <p class="callout-important">Reserveer je tafeltje telefonisch via <strong>051/80 13 80</strong>.</p>
                    <p class="callout-important">Wegens de drukte over de middag kan een tafeltje gereserveerd worden tot 11u.</p>
                    <p><a href="/zozo-assets/files/PrijslijstTerPlaatse2025.pdf" class="prijslijst-link">Bekijk hier de prijslijst en ons assortiment. PRIJSLIJST voor ter plaatse.</a></p>
                </div>


                <p class="handtekening">Het Maxice team</p>
            </div>

            <div class="about-image">
                <div class="about-gallery">
                    <div class="about-main">
                        <img src="/zozo-assets/img/maxice_interieur1.webp" alt="Maxice interieur hoofd">
                    </div>
                    <div class="about-thumbs">
                        <img src="/zozo-assets/img/maxice_interieur2.webp" alt="Maxice interieur 2">
                        <img src="/zozo-assets/img/maxice_interieur3.webp" alt="Maxice interieur 3">
                        <img src="/zozo-assets/img/maxice_interieur4.webp" alt="Maxice interieur 4">
                        <img src="/zozo-assets/img/maxice_interieur5.webp" alt="Maxice interieur 5">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="welkom-block welkom-suggesties">
        <div class="suggestions-bleed">
            <h3>Suggesties</h3>
            <p>Onze huidige suggesties — laat je verrassen door dé suggesties van het huis.</p>
            <div class="suggestions-container">
                <div class="suggestion-grid">
                    <?php
                    // Dynamic suggesties: haal maximaal 4 producten op die als suggestie gemarkeerd zijn
                    require_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/DB_connectie.php');
                    // Ensure price helper is available
                    @include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/price_helpers.php');

                    $suggSql = "SELECT p.*, pi.image_name
                        FROM products p
                        LEFT JOIN product_images pi ON p.art_id = pi.product_id AND pi.image_order = 1
                        WHERE (p.art_suggestie = 1 OR p.art_suggestie = '1' OR LOWER(p.art_suggestie) = 'ja')
                          AND (p.art_weergeven = 1 OR p.art_weergeven = '1' OR LOWER(p.art_weergeven) = 'ja')
                          AND (p.art_weergeven = 1 OR p.art_weergeven = '1' OR LOWER(p.art_weergeven) = 'ja')
                        ORDER BY COALESCE(p.art_order, 0) ASC, p.art_naam ASC
                        LIMIT 4";
                    $suggRes = $mysqli->query($suggSql);
                    if ($suggRes && $suggRes->num_rows > 0) {
                        while ($s = $suggRes->fetch_assoc()) {
                            $naam = $s['art_naam'];
                            $btw = isset($s['art_BTWtarief']) ? floatval($s['art_BTWtarief']) : 21;
                            $prijs_excl = floatval($s['art_kostprijs']);
                            $prijs_incl = $prijs_excl * (1 + $btw / 100);
                            // If price is zero or not set, show 'op maat' instead of a numeric price
                            if ($prijs_excl <= 0) {
                                $prijs_display = 'op maat';
                            } else {
                                $prijs_display = function_exists('format_price_eur_nosymbol') ? format_price_eur_nosymbol(round($prijs_incl, 2)) : number_format(round($prijs_incl, 2), 2, ',', '.');
                            }
                            $img = !empty($s['image_name']) ? '/upload/' . $s['image_name'] : '/zozo-assets/img/placeholder.png';
                            $slug = $s['art_afkorting'] ?? '';
                            $product_url = '/' . ($lang ?? 'nl') . '/product/' . $s['art_id'] . '/' . $slug;
                            // indekijker badge
                            $isInde = false;
                            if (isset($s['art_indekijker'])) {
                                $v = $s['art_indekijker'];
                                if ($v === 1 || $v === '1' || strtolower((string)$v) === 'ja') $isInde = true;
                            }
                    ?>
                            <div class="suggestion-card">
                                <a href="<?= htmlspecialchars($product_url) ?>" class="product-link">
                                    <div class="product-img-wrap" style="position:relative;">
                                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($naam) ?>" style="width:100%;height:140px;object-fit:cover;border-radius:6px;">
                                        <!-- star badge intentionally removed for welcome suggestions to avoid visual clutter -->
                                        <div class="product-overlay" style="position:absolute;left:8px;bottom:8px;background:rgba(0,0,0,0.6);color:#fff;padding:6px 8px;border-radius:4px;font-weight:600;">
                                            <?= htmlspecialchars($prijs_display) ?>
                                        </div>
                                    </div>
                                    <div class="suggestion-body" style="padding-top:8px;">
                                        <div class="suggestion-title" style="font-weight:700;color:#1f2937;"><?= htmlspecialchars($naam) ?></div>
                                        <div class="suggestion-desc" style="color:#64748b;font-size:0.95em;"><?= htmlspecialchars($s['art_kenmerk'] ?? '') ?></div>
                                    </div>
                                </a>
                            </div>
                        <?php
                        }
                    } else {
                        // Fallback: toon placeholders (voormalige static content)
                        ?>
                        <div class="suggestion-card">Suggestie 1<br><small>Beschrijving kort</small></div>
                        <div class="suggestion-card">Suggestie 2<br><small>Beschrijving kort</small></div>
                        <div class="suggestion-card">Suggestie 3<br><small>Beschrijving kort</small></div>
                        <div class="suggestion-card">Suggestie 4<br><small>Beschrijving kort</small></div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
    // Simple thumbnail -> main image swap
    (function() {
        document.addEventListener('DOMContentLoaded', function() {
            var thumbs = document.querySelectorAll('.about-thumbs img');
            var main = document.querySelector('.about-main img');
            if (!thumbs.length || !main) return;

            thumbs.forEach(function(t) {
                t.addEventListener('click', function() {
                    // swap src
                    main.src = t.src;
                    // mark active
                    thumbs.forEach(function(x) {
                        x.classList.remove('thumb-active');
                    });
                    t.classList.add('thumb-active');
                });
            });
        });
    })();
</script>