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
                            window.location.href = '/admin/detail';
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
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/config.php");

// Controleer of admin is ingelogd
/*if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}*/

// Artikel ID ophalen (eerste uit GET, fallback op POST)
$art_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['art_id']) ? intval($_POST['art_id']) : 0);
if (!$art_id) {
    // header('Location: /admin/artikelen');
    exit;
}

// Include handlers en data
require_once('includes/artikel_edit_handler.php');
require_once('includes/artikel_data.php');

// Super simpele terug link
$zoek = $_GET['zoek'] ?? '';
$catFilter = $_GET['cat'] ?? '';
$backUrl = '/admin/artikelen?zoek=' . urlencode($zoek) . '&cat=' . urlencode($catFilter);

// Als er een anchor is, voeg die toe
$returnAnchor = $_GET['return_anchor'] ?? '';
if ($returnAnchor && strpos($backUrl, 'anchor=') === false) {
    $separator = strpos($backUrl, '?') !== false ? '&' : '?';
    $backUrl .= $separator . 'anchor=' . urlencode($returnAnchor);
}


$instellingen = $mysqli->query("SELECT * FROM instellingen LIMIT 1")->fetch_assoc();
$talen = [];
if (!empty($instellingen['talen_fr']) && $instellingen['talen_fr'] != '0') {
    $talen[] = 'fr';
}
if (!empty($instellingen['talen_en']) && $instellingen['talen_en'] != '0') {
    $talen[] = 'en';
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artikel bewerken - <?= htmlspecialchars($artikel['art_naam']) ?></title>
    <link rel="stylesheet" href="/zozo-admin/css/navbar.css">
    <link rel="stylesheet" href="/zozo-admin/css/main.css">
    <link rel="stylesheet" href="/zozo-admin/css/artikelen.css">
    <link rel="stylesheet" href="/zozo-admin/css/detail.css">
    <style>
        #selected-files:empty {
            display: none;
        }

        #selected-files:not(:empty) {
            display: block;
            border: 1px solid #ddd;
        }
    </style>
</head>

<body class="page-bg">
    <?php include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-admin/templates/navbar.php'); ?>

    <main class="main-container">
        <!-- WITTE CONTENT BOX -->
        <div class="main-card">
            <!-- Header -->
            <?php include('templates/artikel_edit_header.php'); ?>

            <!-- Top sectie foto + naam -->
            <?php include('templates/artikel_edit_top.php'); ?>

            <!-- Artikel details grid -->
            <?php include('templates/artikel_edit_details.php'); ?>


            <!-- TWEEDE TERUG KNOP ONDERAAN -->
            <div class="terug-row">
                <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn--gray">
                    <svg class="btn-icon mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Terug naar artikelen
                </a>
            </div>

            <!-- Verwijder artikel formulier -->
            <form method="post" action="/zozo-admin/includes/artikel_delete_handler.php" onsubmit="return confirm('Weet je zeker dat je dit artikel wilt verwijderen?');" style="margin-top:32px;">
                <input type="hidden" name="art_id" value="<?= $art_id ?>">
                <button type="submit" class="btn btn--red">
                    <svg class="btn-icon mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Verwijder artikel
                </button>
            </form>
        </div>

        <!-- Modals -->
        <?php
        include('templates/artikel_modals.php'); ?>
        <?php include('templates/artikel_foto_modal.php'); ?>
    </main>

    <script>
        window.categories = <?= json_encode($categories) ?>;
        window.artikel = <?= json_encode($artikel) ?>;
        window.currentImageCount = <?= count($images ?? []) ?>;

        // Heropen modal na page reload
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('modal') === 'foto') {
                openModal('foto-modal');
                // Verwijder parameter uit URL zonder page reload
                const newUrl = window.location.pathname + '?id=' + urlParams.get('id');
                window.history.replaceState({}, '', newUrl);
            }
        });

        function submitForm() {
            document.getElementById('artikel-form').submit();
        }

        // Modal functies
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }


        // Foto functies
        function showSelectedFiles(input) {
            // bestaande code...
        }
    </script>
    <script src="/zozo-admin/js/artikel-modals.js"></script>
    <script src="/zozo-admin/js/foto-beheer.js"></script>

    <script>
        // Override the closeModal function from artikel-modals.js
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');

            // Add reload logic for foto modal
            if (modalId === 'foto-modal') {
                window.location.reload();
            }
        }
    </script>
</body>

</html>