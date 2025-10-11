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
                            window.location.href = '/admin/opties';
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
if (!isset($_SESSION['admin_logged_in'])) {
    //   header('Location: login.php');
    //   exit;
}

// Include form handlers
require_once('includes/opties_handlers.php');

// Ophalen van alle optiegroepen en opties - GEFIXED met juiste kolomnamen
$sql = "SELECT * FROM option_groups ORDER BY sort_order ASC, group_name ASC";
$result = $mysqli->query($sql);

$groups = [];
while ($row = $result->fetch_assoc()) {
    $group_id = $row['group_id'];

    // Ophalen van opties voor deze groep - GEFIXED met juiste kolomnamen
    $options_sql = "SELECT * FROM options WHERE group_id = ? ORDER BY sort_order ASC, option_name ASC";
    $options_stmt = $mysqli->prepare($options_sql);
    $options_stmt->bind_param("i", $group_id);
    $options_stmt->execute();
    $options_result = $options_stmt->get_result();

    $row['options'] = [];
    while ($option = $options_result->fetch_assoc()) {
        $row['options'][] = $option;
    }

    $groups[] = $row;
}

// Haal actieve talen en voorraadbeheer op uit instellingen
$talen = ['nl'];
$voorraadbeheer = true; // default aan
$res = $mysqli->query("SELECT talen_fr, talen_en, voorraadbeheer FROM instellingen LIMIT 1");
if ($row = $res->fetch_assoc()) {
    if ($row['talen_fr']) $talen[] = 'fr';
    if ($row['talen_en']) $talen[] = 'en';
    // Als voorraadbeheer expliciet 0 staat, zet uit
    $voorraadbeheer = !empty($row['voorraadbeheer']) && $row['voorraadbeheer'] != 0;
}

// Redirect na formulierverwerking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ...verwerk formulier...
    // $option_id = ... (haal uit $_POST of uit insert)
    header("Location: opties.php#optie-" . $option_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opties beheren</title>
    <link rel="stylesheet" href="/zozo-admin/css/navbar.css">
    <link rel="stylesheet" href="/zozo-admin/css/main.css">
    <link rel="stylesheet" href="/zozo-admin/css/opties.css">
</head>

<body class="page-bg">
    <?php include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-admin/templates/navbar.php'); ?>

    <main class="main-container">
        <div class="main-card">
            <div class="main-card-header">
                <h1 class="main-title">Opties beheren</h1>
                <button onclick="showAddGroupForm()" class="btn btn--add">
                    + optiegroep
                </button>
            </div>

            <?php include('templates/opties_lijst.php'); ?>
        </div>

        <?php include('templates/opties_modals.php'); ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script src="/zozo-admin/js/opties.js"></script>
    <script>
        // Type namen mapping
        const typeNames = {
            'select': 'Selectielijst (dropdown)',
            'radio': 'Radio buttons (één keuze)',
            'checkbox': 'Checkboxes (meerdere keuzes)',
            'text': 'Tekstveld (korte tekst)',
            'textarea': 'Tekstvak (lange tekst)'
        };

        // Update de edit functie:
        document.getElementById('editoptiongroup_type_display').textContent = typeNames['<?= $group['type'] ?>'] || '<?= ucfirst($group['type']) ?>';
        // Edit functies met PHP data - TOEGEVOEGD
        <?php foreach ($groups as $group): ?>

            function showEditGroupForm<?= $group['group_id'] ?>(groupId) {
                if (groupId == <?= $group['group_id'] ?>) {
                    document.getElementById('editoptiongroup_id').value = '<?= $group['group_id'] ?>';
                    document.getElementById('editoptiongroup_name_nl').value = '<?= addslashes($group['group_name']) ?>';
                    <?php if (in_array('fr', $talen)): ?>
                        document.getElementById('editoptiongroup_name_fr').value = '<?= addslashes($group['group_name_fr'] ?? '') ?>';
                    <?php endif; ?>
                    <?php if (in_array('en', $talen)): ?>
                        document.getElementById('editoptiongroup_name_en').value = '<?= addslashes($group['group_name_en'] ?? '') ?>';
                    <?php endif; ?>

                    // TYPE ALLEEN TONEN, NIET BEWERKBAAR
                    document.getElementById('editoptiongroup_type_display').textContent = typeNames['<?= $group['type'] ?>'] || '<?= ucfirst($group['type']) ?>';

                    <?php if ($voorraadbeheer): ?>
                        var editAffectsStockElem = document.getElementById('editoptiongroup_affects_stock');
                        if (editAffectsStockElem) editAffectsStockElem.checked = <?= $group['affects_stock'] ? 'true' : 'false' ?>;
                    <?php endif; ?>
                    document.getElementById('editoptiongroup_info').value = '<?= addslashes($group['info'] ?? '') ?>';
                    document.getElementById('editoptiongroup-modal-title').textContent = '<?= addslashes($group['group_name']) ?> bewerken';
                    openModal('editoptiongroup-modal');
                }
            }

            <?php foreach ($group['options'] as $opt): ?>

                function showEditOptionForm<?= $opt['option_id'] ?>(optionId, groupId) {
                    if (optionId == <?= $opt['option_id'] ?>) {
                        document.getElementById('editoption_id').value = '<?= $opt['option_id'] ?>';
                        document.getElementById('editoption_group_id').value = '<?= $group['group_id'] ?>';
                        document.getElementById('editoption_name_nl').value = '<?= addslashes($opt['option_name']) ?>';
                        <?php if (in_array('fr', $talen)): ?>
                            document.getElementById('editoption_name_fr').value = '<?= addslashes($opt['option_name_fr'] ?? '') ?>';
                        <?php endif; ?>
                        <?php if (in_array('en', $talen)): ?>
                            document.getElementById('editoption_name_en').value = '<?= addslashes($opt['option_name_en'] ?? '') ?>';
                        <?php endif; ?>
                        document.getElementById('editoption_price_delta').value = '<?= $opt['price_delta'] ?>';
                        document.getElementById('editoption-modal-title').textContent = '<?= addslashes($opt['option_name']) ?> bewerken';
                        openModal('editoption-modal');
                    }
                }
            <?php endforeach; ?>
        <?php endforeach; ?>

        // Override de functies uit opties.js
        function showEditGroupForm(groupId) {
            <?php foreach ($groups as $group): ?>
                if (groupId == <?= $group['group_id'] ?>) {
                    showEditGroupForm<?= $group['group_id'] ?>(groupId);
                    return;
                }
            <?php endforeach; ?>
        }

        function showEditOptionForm(optionId, groupId) {
            <?php foreach ($groups as $group): ?>
                <?php foreach ($group['options'] as $opt): ?>
                    if (optionId == <?= $opt['option_id'] ?>) {
                        showEditOptionForm<?= $opt['option_id'] ?>(optionId, groupId);
                        return;
                    }
                <?php endforeach; ?>
            <?php endforeach; ?>
        }

        // Initialiseer sortables na pagina load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(reinitializeSortables, 100);
        });
    </script>
</body>

</html>