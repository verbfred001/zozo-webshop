<!-- Navbar -->
<style>
    /* Maak de navbar sticky (blijft bovenaan tijdens scrollen) */
    .navbar {
        position: -webkit-sticky;
        /* Safari */
        position: sticky;
        top: 0;
        z-index: 9999;
        background: var(--nav-bg, #fff);
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
        transition: box-shadow .22s ease, transform .18s ease;
    }

    /* Schaduw toevoegen zodra er gescrold is voor visuele feedback */
    .navbar.scrolled {
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
    }

    /* Zorg dat de toggle zichtbaar blijft boven andere lagen */
    .navbar .nav-toggle {
        z-index: 10001;
    }
</style>

<nav class="navbar" aria-label="Hoofdmenu">
    <div class="navbar-container">
        <button class="nav-toggle" aria-label="Menu openen" aria-expanded="false" onclick="toggleNavMenu()">☰</button>

        <!-- Logo voor desktop (links) en mobiel (midden) -->
        <div class="navbar-logo">
            <?php
            // Zorg dat branded URLs beschikbaar zijn
            if (!isset($url_nl) || !isset($url_fr) || !isset($url_en)) {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/DB_connectie.php';
                $r = $mysqli->query("SELECT url_welkom, url_welkom_fr, url_welkom_en FROM instellingen LIMIT 1");
                $rowu = $r ? $r->fetch_assoc() : [];
                $url_nl = $rowu['url_welkom'] ?? 'welkom';
                $url_fr = $rowu['url_welkom_fr'] ?? 'bienvenue';
                $url_en = $rowu['url_welkom_en'] ?? 'welcome';
            }
            $logoTarget = '/' . ($activeLang === 'fr' ? $url_fr : ($activeLang === 'en' ? $url_en : $url_nl));
            ?>
            <a href="<?= htmlspecialchars($logoTarget) ?>">
                <img src="/zozo-assets/img/Maxice.webp" alt="Logo" width="420" height="120">
            </a>
        </div>

        <?php render_menu($categories, $lang); ?>
    </div>
</nav>

<script>
    // Hamburger menu
    function toggleNavMenu() {
        var navList = document.querySelector('.nav-list.depth-0');
        var toggleBtn = document.querySelector('.nav-toggle');
        if (!navList) return;
        var isShown = navList.classList.toggle('show');
        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', isShown ? 'true' : 'false');
    }

    // Submenu toggle (works on mobile and desktop) with ARIA + keyboard support
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.has-children > .nav-link').forEach(function(link) {
            // ensure ARIA state starts closed
            link.setAttribute('aria-expanded', 'false');
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var li = link.parentElement;
                li.classList.toggle('open');
                var expanded = li.classList.contains('open');
                link.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            });
            // keyboard support: Enter or Space toggles submenu
            link.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    link.click();
                }
            });
        });

        // Scroll handler: voeg .scrolled toe aan navbar na kleine scroll
        var navbar = document.querySelector('.navbar');
        if (navbar) {
            function onScroll() {
                if (window.pageYOffset > 8) navbar.classList.add('scrolled');
                else navbar.classList.remove('scrolled');
            }
            onScroll();
            window.addEventListener('scroll', onScroll, {
                passive: true
            });
        }
    });

    // Sluit submenu's bij klik buiten menu
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.nav-item.open').forEach(function(li) {
            if (!li.contains(e.target)) li.classList.remove('open');
        });
    });
</script>