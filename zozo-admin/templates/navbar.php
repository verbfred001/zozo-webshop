<?php
// Zorg dat we weten of voorraadbeheer actief is; als $voorraadbeheer nog niet gezet is, haal het op uit DB
if (!isset($voorraadbeheer)) {
    if (!isset($mysqli)) {
        @include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/DB_connectie.php');
    }
    $inst = $mysqli->query("SELECT voorraadbeheer FROM instellingen LIMIT 1")->fetch_assoc() ?: [];
    $voorraadbeheer = !empty($inst['voorraadbeheer']) && $inst['voorraadbeheer'] != 0;
}
?>

<nav class="navbar">
    <div class="navbar-logo">
        <img src="/zozo-assets/img/zozo-webshops.webp" alt="ZoZo Webshops" style="height: 2.5rem; width: auto;">
    </div>

    <!-- Desktop Menu -->
    <button class="navbar-toggle" id="navbar-toggle" aria-label="Menu">
        &#9776;
    </button>
    <div class="navbar-links" id="navbar-links">
        <a href="/admin/bestellingen" class="navbar-link">Bestellingen</a>
        <a href="/admin/artikelen" class="navbar-link">Artikelen</a>
        <?php //  <a href="/admin/kalender" class="navbar-link">Kalender</a> 
        ?>
        <?php if ($voorraadbeheer): ?>
            <a href="/admin/voorraad" class="navbar-link">Voorraad</a>
        <?php endif; ?>
        <div class="navbar-link" style="position: relative;">
            <button id="instellingen-dropdown" class="navbar-link" style="background: none; border: none; padding: 0; display: flex; align-items: center; cursor: pointer;">
                Instellingen
                <svg style="margin-left: 0.25rem; width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div id="instellingen-menu" class="navbar-dropdown" style="display: none; position: absolute; right: 0; top: 2.5rem; border-radius: 0.5rem; min-width: 220px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); z-index: 50;">
                <a href="/admin/categorieen" class="navbar-link" style="display: block; padding: 0.75rem 1rem;">📁 Categorieën</a>
                <a href="/admin/opties" class="navbar-link" style="display: block; padding: 0.75rem 1rem;">⚙️ Opties beheren</a>
                <a href="/admin/opties-toewijzen" class="navbar-link" style="display: block; padding: 0.75rem 1rem;">🔗 Opties toewijzen</a>
                <a href="/admin/instellingen" class="navbar-link" style="display: block; padding: 0.75rem 1rem;">🔧 Algemene instellingen</a>
            </div>
        </div>
        <a href="/admin/logout" class="navbar-link logout">Uitloggen</a>
    </div>
</nav>

<!-- Mobile menu script -->
<script>
    // Hamburger toggle
    document.getElementById('navbar-toggle').onclick = function() {
        document.getElementById('navbar-links').classList.toggle('open');
    };
    // Instellingen dropdown
    document.getElementById('instellingen-dropdown').onclick = function(e) {
        e.preventDefault();
        var menu = document.getElementById('instellingen-menu');
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    };
    // Klik buiten sluit dropdown
    document.addEventListener('click', function(event) {
        var dropdown = document.getElementById('instellingen-menu');
        var button = document.getElementById('instellingen-dropdown');
        if (!button.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });
</script>