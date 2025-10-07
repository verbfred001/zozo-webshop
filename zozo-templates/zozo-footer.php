<?php
// filepath: e:\meettoestel.be\zozo-templates\zozo-footer.php

require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");
$res = $mysqli->query("SELECT * FROM instellingen LIMIT 1");
$inst = $res ? $res->fetch_assoc() : null;
?>

<!-- Footer -->
<footer class="zozo-footer">
    <div class="footer-content">
        <!-- Left column: bedrijfsgegevens + privacy -->
        <div>
            <!-- Decoratieve afbeelding links in footer (boven de bedrijfsnaam) -->
            <div class="footer-deco-wrap" aria-hidden="true">
                <img src="/zozo-assets/img/myriam-maxice.webp" alt="" class="footer-deco-img" loading="lazy">
            </div>
            <div class="footer-company-text">
                <strong><?= htmlspecialchars($inst['bedrijfsnaam'] ?? 'Bedrijfsnaam BV') ?></strong><br>
                <?= htmlspecialchars($inst['adres'] ?? 'Straatnaam 123, 1000 Brussel') ?><br>
                <?= htmlspecialchars($inst['telefoon'] ?? '012/34.56.78') ?><br>
                BTW <?= htmlspecialchars($inst['btw_nummer'] ?? 'BE0123.456.789') ?><br>
                <span id="zozo-email"></span>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        var u = "<?= htmlspecialchars(explode('@', $inst['email'] ?? '')[0]) ?>";
                        var d = "<?= htmlspecialchars(explode('@', $inst['email'] ?? '')[1] ?? '') ?>";
                        if (u && d) {
                            var e = u + "@" + d;
                            var a = document.createElement('a');
                            a.href = "mailto:" + e;
                            a.textContent = e;
                            document.getElementById('zozo-email').appendChild(a);
                        }
                    });
                </script>
            </div>

            <div class="footer-links">
                <a href="#">Privacy</a> | <a href="#">Contact</a>
            </div>
        </div>

        <!-- Middle column: openingsuren -->
        <div class="footer-hours">
            <table style="font-size:0.95em;">
                <tr>
                    <td>Ma:</td>
                    <td><?= htmlspecialchars($inst['openingsuren_maandag'] ?? '') ?></td>
                </tr>
                <tr>
                    <td>Di:</td>
                    <td><?= htmlspecialchars($inst['openingsuren_dinsdag'] ?? '') ?></td>
                </tr>
                <tr>
                    <td>Wo:</td>
                    <td><?= htmlspecialchars($inst['openingsuren_woensdag'] ?? '') ?></td>
                </tr>
                <tr>
                    <td>Do:</td>
                    <td><?= htmlspecialchars($inst['openingsuren_donderdag'] ?? '') ?></td>
                </tr>
                <tr>
                    <td>Vr:</td>
                    <td><?= htmlspecialchars($inst['openingsuren_vrijdag'] ?? '') ?></td>
                </tr>
                <tr>
                    <td>Za:</td>
                    <td><?= htmlspecialchars($inst['openingsuren_zaterdag'] ?? '') ?></td>
                </tr>
                <tr>
                    <td>Zo:</td>
                    <td><?= htmlspecialchars($inst['openingsuren_zondag'] ?? '') ?></td>
                </tr>
            </table>
        </div>

        <!-- Right column: marketing, payment, allergen note, social icons and copyright -->
        <div>
            <div class="footer-marketing">
                <p><strong>Bij ons stel je je broodje zelf samen.</strong><br>
                    Geheel naar eigen smaak!<br>
                    Meer dan 1000 combinaties ..., eenvoudig samenstellen via het bestelscript.</p>

                <p><strong>Betaal eenvoudig met:</strong><br>
                    bancontact · Payconiq · Pluxee<br>
                    Monizze · Eden Red · Cash</p>
            </div>

            <p class="footer-small" style="margin-top:8px;">Voor vragen mbt allergenen kan je ons contacteren via mail of via telefoon.</p>

            <div class="footer-social" style="margin-top:8px;">
                <a href="https://www.facebook.com/people/Maxice/100063613179216/" aria-label="Facebook" class="footer-social-link" target="_blank" rel="noopener">
                    <!-- Facebook SVG -->
                    <svg class="footer-social-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M22 12.07C22 6.48 17.52 2 11.93 2S1.86 6.48 1.86 12.07c0 4.99 3.66 9.13 8.44 9.92v-7.03H8.08v-2.9h2.22V9.41c0-2.2 1.31-3.41 3.32-3.41.96 0 1.96.17 1.96.17v2.15h-1.09c-1.07 0-1.4.66-1.4 1.34v1.6h2.38l-.38 2.9h-2v7.03c4.78-.79 8.44-4.93 8.44-9.92z" />
                    </svg>
                </a>
                <a href="https://www.instagram.com/maxiceroeselare/" aria-label="Instagram" class="footer-social-link" target="_blank" rel="noopener">
                    <!-- Instagram SVG -->
                    <svg class="footer-social-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7zm5 3.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 0 1 12 7.5zm0 2A2.5 2.5 0 1 0 14.5 12 2.5 2.5 0 0 0 12 9.5zM18.5 6a1 1 0 1 1-1 1 1 1 0 0 1 1-1z" />
                    </svg>
                </a>
            </div>

            <div style="margin-top:12px;">&copy; <?= date('Y') ?> <a href="https://zozo.be" target="_blank" rel="noopener noreferrer">ZoZo</a></div>
        </div>
    </div>
</footer>

<!-- Scroll to top knop (indien nog niet toegevoegd) -->
<button id="scroll-to-top" class="scroll-to-top" type="button" aria-label="Terug naar boven" title="Terug naar boven">↑</button>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Scroll-to-top
        (function() {
            const btn = document.getElementById('scroll-to-top');
            if (!btn) return;
            const threshold = 200;
            let ticking = false;

            function updateScrollBtn() {
                const sc = window.scrollY || window.pageYOffset;
                btn.classList.toggle('show', sc > threshold);
            }
            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(function() {
                        updateScrollBtn();
                        ticking = false;
                    });
                    ticking = true;
                }
            }, {
                passive: true
            });
            btn.addEventListener('click', function() {
                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    window.scrollTo(0, 0);
                } else {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            });
            updateScrollBtn();
        })();

        // Cart: sticky on scroll (voegt/verwijdert class .cart-btn--fixed)
        (function() {
            const cart = document.querySelector('.cart-btn');
            const topbarRow = document.querySelector('.topbar-row');
            if (!cart) return;
            const threshold = (topbarRow ? topbarRow.offsetHeight : 80);
            let ticking = false;

            function onScrollCart() {
                const sc = window.scrollY || window.pageYOffset;
                if (sc > threshold) {
                    cart.classList.add('cart-btn--fixed');
                    if (topbarRow) topbarRow.classList.add('header-has-fixed-cart');
                } else {
                    cart.classList.remove('cart-btn--fixed');
                    if (topbarRow) topbarRow.classList.remove('header-has-fixed-cart');
                }
                ticking = false;
            }
            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(onScrollCart);
                    ticking = true;
                }
            }, {
                passive: true
            });
            onScrollCart();
        })();
    });
</script>