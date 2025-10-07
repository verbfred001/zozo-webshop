<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");

// Ophalen van instellingen
$stmt = $mysqli->prepare("SELECT * FROM instellingen LIMIT 1");
$stmt->execute();
$instellingen = $stmt->get_result()->fetch_assoc();

// Opslaan van wijzigingen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_general'])) {
        $sql = "UPDATE instellingen SET 
            bedrijfsnaam=?, adres=?, telefoon=?, btw_nummer=?, email=?,
            openingsuren_maandag=?, openingsuren_dinsdag=?, openingsuren_woensdag=?, 
            openingsuren_donderdag=?, openingsuren_vrijdag=?, openingsuren_zaterdag=?, openingsuren_zondag=?
            WHERE id=?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "ssssssssssssi",
            $_POST['bedrijfsnaam'],
            $_POST['adres'],
            $_POST['telefoon'],
            $_POST['btw_nummer'],
            $_POST['email'],
            $_POST['openingsuren_maandag'],
            $_POST['openingsuren_dinsdag'],
            $_POST['openingsuren_woensdag'],
            $_POST['openingsuren_donderdag'],
            $_POST['openingsuren_vrijdag'],
            $_POST['openingsuren_zaterdag'],
            $_POST['openingsuren_zondag'],
            $instellingen['id']
        );
        $stmt->execute();
        $success_message = "Bedrijfsgegevens opgeslagen!";
        // Herladen na opslaan
        header("Location: /admin/instellingen?success=1");
        exit;
    }
    if (isset($_POST['save_talen'])) {
        $sql = "UPDATE instellingen SET talen_fr=?, talen_en=? WHERE id=?";
        $stmt = $mysqli->prepare($sql);
        $fr = isset($_POST['talen_fr']) ? 1 : 0;
        $en = isset($_POST['talen_en']) ? 1 : 0;
        $stmt->bind_param("iii", $fr, $en, $instellingen['id']);
        $stmt->execute();
        $success_message = "Talen opgeslagen!";
        header("Location: /admin/instellingen/talen?success=1");
        exit;
    }

    if (isset($_POST['save_melding'])) {
        // Ensure columns exist? We assume DB migration/ALTER has been applied separately.
        $sql = "UPDATE instellingen SET melding_tekst = ?, melding_tekst_fr = ?, melding_tekst_en = ?, melding_actief = ? WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $tekst = trim($_POST['melding_tekst'] ?? '');
        $tekst_fr = trim($_POST['melding_tekst_fr'] ?? '');
        $tekst_en = trim($_POST['melding_tekst_en'] ?? '');
        $actief = isset($_POST['melding_actief']) ? 1 : 0;
        if ($stmt) {
            $stmt->bind_param("sssii", $tekst, $tekst_fr, $tekst_en, $actief, $instellingen['id']);
            $stmt->execute();
        }
        $success_message = "Melding opgeslagen!";
        header("Location: /admin/instellingen/tijdelijke-melding?success=1");
        exit;
    }

    // Set or clear tijd_override via dedicated buttons
    if (isset($_POST['set_tijd']) || isset($_POST['clear_tijd'])) {
        // Ensure column exists
        $colStmt = $mysqli->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'instellingen' AND COLUMN_NAME = 'tijd_override'");
        $colExists = false;
        if ($colStmt) {
            $colStmt->execute();
            $cres = $colStmt->get_result();
            if ($cres && $cres->num_rows > 0) $colExists = true;
            $colStmt->close();
        }
        if (!$colExists) {
            $mysqli->query("ALTER TABLE instellingen ADD COLUMN tijd_override VARCHAR(64) DEFAULT NULL");
        }

        if (isset($_POST['set_tijd'])) {
            // Only activate override for this browser session (do NOT persist to DB here).
            $raw_t = trim((string)($_POST['tijd_override'] ?? ''));
            $tijd_override = null;
            if ($raw_t !== '') {
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw_t);
                if (!$dt) $dt = DateTime::createFromFormat('d/m/Y-H:i', $raw_t);
                if (!$dt) $dt = DateTime::createFromFormat('Y-m-d H:i', $raw_t);
                if ($dt) $tijd_override = $dt->format('Y-m-d H:i');
            }
            // Do NOT persist this override to the DB for all users; scope it to this browser session.
            // Also proactively clear any previously persisted override in the database so it
            // doesn't affect other browsers.
            $clear = $mysqli->prepare("UPDATE instellingen SET tijd_override = NULL WHERE id = ?");
            if ($clear) {
                $clear->bind_param('i', $instellingen['id']);
                $clear->execute();
                $clear->close();
            }
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            if ($tijd_override !== null) {
                $_SESSION['tijd_override'] = $tijd_override;
                $_SESSION['tijd_override_source'] = 'admin';
            }
        } else {
            // clear_tijd
            $upd = $mysqli->prepare("UPDATE instellingen SET tijd_override = NULL WHERE id = ?");
            if ($upd) {
                $upd->bind_param('i', $instellingen['id']);
                $upd->execute();
            }
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            unset($_SESSION['tijd_override']);
            unset($_SESSION['tijd_override_source']);
        }

        header('Location: /admin/instellingen/webshop?success=1');
        exit;
    }

    // Webshop instellingen (voorraadbeheer)
    if (isset($_POST['save_webshop'])) {
        $vb = isset($_POST['voorraadbeheer']) ? 1 : 0;
        // Ensure 'tijd_override' column exists (nullable varchar)
        $colStmt = $mysqli->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'instellingen' AND COLUMN_NAME = 'tijd_override'");
        $colExists = false;
        if ($colStmt) {
            $colStmt->execute();
            $cres = $colStmt->get_result();
            if ($cres && $cres->num_rows > 0) $colExists = true;
            $colStmt->close();
        }
        if (!$colExists) {
            // add the column to store admin-configured override time (text, nullable)
            $mysqli->query("ALTER TABLE instellingen ADD COLUMN tijd_override VARCHAR(64) DEFAULT NULL");
        }

        // Normalize tijd_override input (accept datetime-local format 'YYYY-MM-DDTHH:MM')
        $raw_t = trim((string)($_POST['tijd_override'] ?? ''));
        $tijd_override = null;
        if ($raw_t !== '') {
            // handle HTML datetime-local (YYYY-MM-DDTHH:MM)
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw_t);
            if (!$dt) {
                // try common admin text formats
                $dt = DateTime::createFromFormat('d/m/Y-H:i', $raw_t);
            }
            if (!$dt) $dt = DateTime::createFromFormat('Y-m-d H:i', $raw_t);
            if ($dt) $tijd_override = $dt->format('Y-m-d H:i');
            else $tijd_override = null; // invalid -> treat as empty
        }

        $sql = "UPDATE instellingen SET voorraadbeheer = ?, tijd_override = ? WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("isi", $vb, $tijd_override, $instellingen['id']);
            $stmt->execute();
        }

        // Also store override in THIS session so only this browser uses the fake time
        // Session will persist until browser closed (default PHP session cookie behavior)
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if ($tijd_override !== null) {
            $_SESSION['tijd_override'] = $tijd_override;
            $_SESSION['tijd_override_source'] = 'admin';
        } else {
            unset($_SESSION['tijd_override']);
            unset($_SESSION['tijd_override_source']);
        }
        $success_message = "Webshop instellingen opgeslagen!";
        header("Location: /admin/instellingen/webshop?success=1");
        exit;
    }

    // Save number of days to show in checkout date picker
    if (isset($_POST['save_tijdsloten_days'])) {
        $days = isset($_POST['tijdsloten_dagen']) ? intval($_POST['tijdsloten_dagen']) : 14;
        if ($days < 1) $days = 1;
        if ($days > 365) $days = 365;

        // Ensure column exists: check INFORMATION_SCHEMA
        $colStmt = $mysqli->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'instellingen' AND COLUMN_NAME = 'tijdsloten_dagen'");
        $colExists = false;
        if ($colStmt) {
            $colStmt->execute();
            $cres = $colStmt->get_result();
            if ($cres && $cres->num_rows > 0) $colExists = true;
            $colStmt->close();
        }

        if (!$colExists) {
            // try to add the column (safe default 14)
            $mysqli->query("ALTER TABLE instellingen ADD COLUMN tijdsloten_dagen INT DEFAULT 14");
        }

        // Update value for the single instellingen row
        $upd = $mysqli->prepare("UPDATE instellingen SET tijdsloten_dagen = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param('ii', $days, $instellingen['id']);
            $upd->execute();
            $upd->close();
        }

        // Redirect to the pretty subroute so the ts-days panel is active after saving
        header('Location: /admin/instellingen/tijdsloten-aantal-dagen?success=1');
        exit;
    }

    // Add a single fixed timeslot (unified for pickup/delivery)
    if (isset($_POST['add_timeslot'])) {
        $type = ($_POST['type'] === 'delivery') ? 'delivery' : 'pickup';
        $day = isset($_POST['day']) ? (int)$_POST['day'] : 1;
        $start = $_POST['start_time'] ?? null;
        $end = $_POST['end_time'] ?? null;
        $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : null;
        $preparation = isset($_POST['preparation_minutes']) && $_POST['preparation_minutes'] !== '' ? (int)$_POST['preparation_minutes'] : null;
        // 'active' field removed from schema; we don't use it anymore

        if ($start && $end) {

            // Insert directly into unified timeslot_fixed_ranges (no 'active' column)
            // include preparation_minutes if provided (nullable)
            if ($preparation !== null) {
                $sql = "INSERT INTO timeslot_fixed_ranges (type, day_of_week, start_time, end_time, capacity, preparation_minutes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $mysqli->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('sissii', $type, $day, $start, $end, $capacity, $preparation);
                    $stmt->execute();
                }
            } else {
                $sql = "INSERT INTO timeslot_fixed_ranges (type, day_of_week, start_time, end_time, capacity, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $mysqli->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('sisss', $type, $day, $start, $end, $capacity);
                    $stmt->execute();
                }
            }
        }

        header('Location: /admin/instellingen?tab=tijdsloten&success=1');
        exit;
    }

    // Add a holiday period
    if (isset($_POST['add_holiday'])) {
        $start_date = $_POST['start_date'] ?? null;
        $start_time = $_POST['start_time'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $end_time = $_POST['end_time'] ?? null;

        if ($start_date && $start_time && $end_date && $end_time) {
            $ins = $mysqli->prepare("INSERT INTO timeslot_holidays (start_date, start_time, end_date, end_time) VALUES (?, ?, ?, ?)");
            if ($ins) {
                $ins->bind_param('ssss', $start_date, $start_time, $end_date, $end_time);
                $ins->execute();
                $ins->close();
            }
        }

        // Redirect back to the holiday subpage
        header('Location: /admin/instellingen/tijdsloten-vakantie?success=1');
        exit;
    }

    // AJAX delete holiday
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_holiday_ajax'])) {
        $hid = isset($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : 0;
        $ok = 0;
        if ($hid) {
            $d = $mysqli->prepare("DELETE FROM timeslot_holidays WHERE id = ?");
            if ($d) {
                $d->bind_param('i', $hid);
                $d->execute();
                if ($d->affected_rows > 0) $ok = 1;
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok]);
        exit;
    }

    // Delete holiday (fallback)
    if (isset($_POST['delete_holiday'])) {
        $hid = isset($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : 0;
        if ($hid) {
            $d = $mysqli->prepare("DELETE FROM timeslot_holidays WHERE id = ?");
            if ($d) {
                $d->bind_param('i', $hid);
                $d->execute();
            }
        }
        header('Location: /admin/instellingen/tijdsloten-vakantie?success=1');
        exit;
    }

    // Delete a fixed range
    // AJAX delete handler (returns JSON) - used by frontend to delete without page reload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_range_ajax'])) {
        $rid = isset($_POST['range_id']) ? (int)$_POST['range_id'] : 0;
        $ok = 0;
        if ($rid) {
            $d = $mysqli->prepare("DELETE FROM timeslot_fixed_ranges WHERE id = ?");
            if ($d) {
                $d->bind_param('i', $rid);
                $d->execute();
                if ($d->affected_rows > 0) $ok = 1;
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok]);
        exit;
    }

    // Delete a fixed range (fallback for non-AJAX)
    if (isset($_POST['delete_range'])) {
        $rid = isset($_POST['range_id']) ? (int)$_POST['range_id'] : 0;
        if ($rid) {
            $d = $mysqli->prepare("DELETE FROM timeslot_fixed_ranges WHERE id = ?");
            $d->bind_param('i', $rid);
            $d->execute();
        }
        header('Location: /admin/instellingen?tab=tijdsloten&success=1');
        exit;
    }
}

// Herlaad instellingen na opslaan
$stmt = $mysqli->prepare("SELECT * FROM instellingen LIMIT 1");
$stmt->execute();
$instellingen = $stmt->get_result()->fetch_assoc();

// Determine active tab and subtab (server-side). Priority: explicit GET params, then pretty path segments.
$active_tab = 'algemeen';
$active_sub = null;
if (isset($_GET['tab']) && $_GET['tab']) {
    $active_tab = $_GET['tab'];
}
if (isset($_GET['sub']) && $_GET['sub']) {
    $active_sub = $_GET['sub'];
} else {
    // try to infer from path like /instellingen/tijdsloten-aantal-dagen
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $parts = explode('/', trim($path, '/'));
    $idx = array_search('instellingen', $parts);
    if ($idx !== false && isset($parts[$idx + 1])) {
        $seg = $parts[$idx + 1];
        switch ($seg) {
            case 'webshop':
                $active_tab = 'shop';
                break;
            case 'tijdelijke-melding':
                $active_tab = 'melding';
                break;
            case 'talen':
                $active_tab = 'talen';
                break;
            case 'tijdsloten-instellen':
                $active_tab = 'tijdsloten';
                $active_sub = 'ts-main';
                break;
            case 'tijdsloten-aantal-dagen':
                $active_tab = 'tijdsloten';
                $active_sub = 'ts-days';
                break;
            case 'tijdsloten-vakantie':
                $active_tab = 'tijdsloten';
                $active_sub = 'ts-holiday';
                break;
            default:
                // leave defaults
                break;
        }
    }
}
// Fallback: some server setups don't preserve the original REQUEST_URI or query
// parameters when internally rewriting. Make detection more robust by also
// inspecting the raw REQUEST_URI for known pretty-route segments.
$req_uri = $_SERVER['REQUEST_URI'] ?? '';
if (!$active_sub) {
    $r = strtolower($req_uri);
    if (strpos($r, 'tijdsloten-aantal-dagen') !== false) {
        $active_tab = 'tijdsloten';
        $active_sub = 'ts-days';
    } elseif (strpos($r, 'tijdsloten-instellen') !== false) {
        $active_tab = 'tijdsloten';
        $active_sub = 'ts-main';
    } elseif (strpos($r, 'tijdsloten-vakantie') !== false) {
        $active_tab = 'tijdsloten';
        $active_sub = 'ts-holiday';
    }
}

if (!$active_sub && $active_tab === 'tijdsloten') $active_sub = 'ts-main';

// --- Timeslot helpers: unified table `timeslot_fixed_ranges` used directly ---
function get_timeslots($mysqli, $type, $day_of_week)
{
    $out = [];
    $sql = "SELECT id, start_time, end_time, capacity, type, day_of_week, preparation_minutes FROM timeslot_fixed_ranges WHERE type = ? AND day_of_week = ? ORDER BY start_time";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return $out;
    $stmt->bind_param('si', $type, $day_of_week);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $out[] = $r;
    return $out;
}

// Load all timeslot rows for days 1..7 (1=maandag .. 7=zondag)
$timeslot_config = ['pickup' => [], 'delivery' => []];
for ($d = 1; $d <= 7; $d++) {
    // pickup: treat every row as an independent timeslot (all rows go into ranges)
    $rows = get_timeslots($mysqli, 'pickup', $d);
    $ranges = [];
    foreach ($rows as $row) {
        $ranges[] = $row;
    }
    $timeslot_config['pickup'][$d] = [
        'start_time' => null,
        'end_time' => null,
        'preparation_minutes' => null,
        'capacity' => null,
        'ranges' => $ranges
    ];

    // delivery: treat all rows as fixed ranges
    $drows = get_timeslots($mysqli, 'delivery', $d);
    $timeslot_config['delivery'][$d] = [
        'ranges' => $drows,
        // 'active' => $delivery_active // Removed active aggregation
    ];
}

// Load existing holiday periods for display in the admin UI
$timeslot_holidays = [];
$hres = $mysqli->query("SELECT id, start_date, start_time, end_date, end_time FROM timeslot_holidays ORDER BY start_date, start_time");
if ($hres) {
    while ($hr = $hres->fetch_assoc()) $timeslot_holidays[] = $hr;
}

// Helper to format times like '08:00:00' -> '8:00'
function fmt_time($t)
{
    if (!$t) return '';
    // try H:i:s
    $dt = DateTime::createFromFormat('H:i:s', $t);
    if ($dt) return $dt->format('G:i');
    // fallback: try H:i
    $dt2 = DateTime::createFromFormat('H:i', $t);
    if ($dt2) return $dt2->format('G:i');
    return $t;
}

// Handle saving of tijdsloten (unified table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tijdsloten'])) {
    $day = isset($_POST['day']) ? (int)$_POST['day'] : 1;
    // Pickup (interval mode)
    // 'active' removed from schema; we no longer store an active flag per range
    $pickup_start = $_POST['pickup_start'] ?? null;
    $pickup_end = $_POST['pickup_end'] ?? null;
    $pickup_interval = isset($_POST['pickup_interval']) ? (int)$_POST['pickup_interval'] : null;
    $pickup_capacity = isset($_POST['pickup_capacity']) ? (int)$_POST['pickup_capacity'] : null;

    // Delivery (fixed ranges) - ranges passed as JSON
    $delivery_ranges_json = $_POST['delivery_ranges_json'] ?? '[]';
    $delivery_ranges = json_decode($delivery_ranges_json, true) ?: [];

    // Upsert pickup interval row: update if exists, otherwise insert
    if ($pickup_interval) {
        // update existing interval row for this day
        $sql = "UPDATE timeslot_fixed_ranges SET start_time = ?, end_time = ?, capacity = ?, preparation_minutes = ? WHERE type='pickup' AND day_of_week = ? AND preparation_minutes IS NOT NULL";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            // start_time (s), end_time (s), capacity (i), preparation_minutes (i), day (i)
            $stmt->bind_param('ssiii', $pickup_start, $pickup_end, $pickup_capacity, $pickup_interval, $day);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                $sql2 = "INSERT INTO timeslot_fixed_ranges (type, day_of_week, start_time, end_time, capacity, preparation_minutes, created_at) VALUES ('pickup', ?, ?, ?, ?, ?, NOW())";
                $stmt2 = $mysqli->prepare($sql2);
                if ($stmt2) {
                    // day (i), start_time (s), end_time (s), capacity (i), preparation_minutes (i)
                    $stmt2->bind_param('issii', $day, $pickup_start, $pickup_end, $pickup_capacity, $pickup_interval);
                    $stmt2->execute();
                }
            }
        }
    } else {
        // remove any interval rows for this pickup day
        $sql = "DELETE FROM timeslot_fixed_ranges WHERE type='pickup' AND day_of_week = ? AND preparation_minutes IS NOT NULL";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $day);
            $stmt->execute();
        }
    }

    // Replace delivery ranges for this day
    if (is_array($delivery_ranges)) {
        $del = $mysqli->prepare("DELETE FROM timeslot_fixed_ranges WHERE type='delivery' AND day_of_week = ?");
        if ($del) {
            $del->bind_param('i', $day);
            $del->execute();
        }

        $ins = $mysqli->prepare("INSERT INTO timeslot_fixed_ranges (type, day_of_week, start_time, end_time, capacity, created_at) VALUES ('delivery', ?, ?, ?, ?, NOW())");
        if ($ins) {
            foreach ($delivery_ranges as $r) {
                $st = $r['start'] ?? null;
                $et = $r['end'] ?? null;
                $cap = isset($r['capacity']) ? (int)$r['capacity'] : null;
                if (!$st || !$et) continue;
                $ins->bind_param('issi', $day, $st, $et, $cap);
                $ins->execute();
            }
        }
    }

    // After saving, reload the page to reflect changes and keep tijdsloten tab
    header('Location: /admin/instellingen?tab=tijdsloten&success=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Instellingen</title>
    <link rel="stylesheet" href="/zozo-admin/css/main.css">
    <link rel="stylesheet" href="/zozo-admin/css/navbar.css">
    <style>
        /* Modal tweaks */
        .modal {
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            border-radius: 8px;
        }

        /* Center table cells */
        .table td,
        .table th {
            text-align: center;
            vertical-align: middle;
        }

        .range-row-fade {
            transition: opacity 200ms ease, height 200ms ease;
        }
    </style>
</head>

<body class="page-bg">
    <?php include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-admin/templates/navbar.php'); ?>
    <main class="main-container">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert--success">Instellingen opgeslagen!</div>
        <?php endif; ?>

        <div class="main-card">
            <div class="main-card-header">
                <h1 class="main-title">Instellingen</h1>
            </div>
            <div class="tabs">
                <nav class="tabs-nav" aria-label="Tabs">
                    <a class="tab-button <?= $active_tab === 'algemeen' ? 'tab-button--active' : '' ?>" data-tab="algemeen" href="/admin/instellingen">Algemeen</a>
                    <a class="tab-button <?= $active_tab === 'melding' ? 'tab-button--active' : '' ?>" data-tab="melding" href="/admin/instellingen/tijdelijke-melding">Tijdelijke melding</a>
                    <?php /*a class="tab-button <?= $active_tab === 'talen' ? 'tab-button--active' : '' ?>" data-tab="talen" href="/admin/instellingen/talen">Talen</a>*/ ?>
                    <a class="tab-button <?= $active_tab === 'tijdsloten' ? 'tab-button--active' : '' ?>" data-tab="tijdsloten" href="/admin/instellingen/tijdsloten-instellen">Tijdsloten</a>
                    <?php if (!empty($instellingen['form_timeoverride']) && (int)$instellingen['form_timeoverride'] === 1): ?>
                        <a class="tab-button <?= $active_tab === 'shop' ? 'tab-button--active' : '' ?>" data-tab="shop" href="/admin/instellingen/webshop">TEST - tijdoverride</a>
                    <?php endif; ?>
                </nav>
            </div>
            <div class="tabs-content">
                <?php
                // Include only the active tab server-side
                if ($active_tab === 'algemeen') {
                    include __DIR__ . '/instellingen-algemeen.php';
                } elseif ($active_tab === 'shop') {
                    include __DIR__ . '/instellingen-webshop.php';
                } elseif ($active_tab === 'melding') {
                    include __DIR__ . '/instellingen-tijdelijke-melding.php';
                } elseif ($active_tab === 'talen') {
                    include __DIR__ . '/instellingen-talen.php';
                } elseif ($active_tab === 'tijdsloten') {
                    // make $active_sub available to the included file
                    include __DIR__ . '/instellingen-tijdsloten.php';
                } else {
                    // fallback
                    include __DIR__ . '/instellingen-algemeen.php';
                }
                ?>
            </div>
        </div>
    </main>
    <script>
        // Modal logic for add timeslot
        (function() {
            const openBtn = document.getElementById('open_add_timeslot');
            const modal = document.getElementById('addTimeslotModal');
            const closeBtn = document.getElementById('close_add_timeslot');
            const cancelBtn = document.getElementById('modal_cancel');
            const backdrop = document.getElementById('modalBackdrop');
            const firstInput = modal ? modal.querySelector('input[name="start_time"]') : null;

            function openModal() {
                if (!modal) return;
                modal.style.display = 'flex';
                if (firstInput) firstInput.focus();
            }

            function closeModal() {
                if (!modal) return;
                modal.style.display = 'none';
            }
            if (openBtn) openBtn.addEventListener('click', openModal);
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (backdrop) backdrop.addEventListener('click', closeModal);
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeModal();
            });
        })();

        // AJAX delete handler for ranges
        (function() {
            function postJSON(url, data) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams(data)
                });
            }
            document.querySelectorAll('.ajax-delete').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    // prevent default and stop propagation so the document-level
                    // handler (in instellingen-tijdsloten.php) does not run a
                    // second deletion request for the same button click.
                    e.preventDefault();
                    e.stopPropagation();

                    const id = this.dataset.id;
                    if (!id) return;
                    const row = this.closest('tr');
                    postJSON('instellingen', {
                        delete_range_ajax: 1,
                        range_id: id
                    }).then(r => r.json()).then(j => {
                        if (j.ok) {
                            // fade and remove row
                            if (row) {
                                row.style.opacity = '0';
                                setTimeout(() => row.remove(), 220);
                            }
                        } else {
                            // fallback: reload
                            window.location.reload();
                        }
                    }).catch(() => window.location.reload());
                });
            });
        })();

        // Timeslot UI: load config from server-side PHP variable and allow editing per day
        const timeslotConfig = <?= json_encode($timeslot_config) ?>;

        function el(sel) {
            return document.querySelector(sel);
        }

        function elAll(sel) {
            return Array.from(document.querySelectorAll(sel));
        }

        const daySelect = el('#ts_day');
        const pickupStart = el('#pickup_start');
        const pickupEnd = el('#pickup_end');
        const pickupInterval = el('#pickup_interval');
        const pickupCapacity = el('#pickup_capacity');
        const rangesContainer = el('#ranges_container');
        const addRangeBtn = el('#add_range');
        const rangesHidden = el('#delivery_ranges_json');

        function clearRanges() {
            if (!rangesContainer) return;
            rangesContainer.innerHTML = '';
        }

        function addRangeRow(range) {
            if (!rangesContainer) return;
            const idx = Date.now() + Math.floor(Math.random() * 1000);
            const div = document.createElement('div');
            div.className = 'range-row';
            div.style.display = 'flex';
            div.style.gap = '8px';
            div.style.alignItems = 'center';
            div.innerHTML = `
                <input type="time" class="range-start" value="${range.start || ''}">
                <input type="time" class="range-end" value="${range.end || ''}">
                <input type="number" class="range-cap" min="1" placeholder="cap" value="${range.capacity || ''}" style="width:90px;">
                <button type="button" class="btn btn--danger btn-remove">Verwijder</button>
            `;
            rangesContainer.appendChild(div);
            div.querySelector('.btn-remove').addEventListener('click', () => div.remove());
        }

        if (addRangeBtn) addRangeBtn.addEventListener('click', () => addRangeRow({}));

        function loadDay(d) {
            // days in PHP config are 1..7
            const pickup = (timeslotConfig.pickup && timeslotConfig.pickup[d]) ? timeslotConfig.pickup[d] : null;
            const delivery = (timeslotConfig.delivery && timeslotConfig.delivery[d]) ? timeslotConfig.delivery[d] : null;

            pickupStart.value = pickup ? (pickup['start_time'] ?? '') : '';
            pickupEnd.value = pickup ? (pickup['end_time'] ?? '') : '';
            pickupInterval.value = pickup ? (pickup['preparation_minutes'] ?? '') : '';
            pickupCapacity.value = pickup ? (pickup['capacity'] ?? '') : '';

            clearRanges();
            if (delivery && Array.isArray(delivery.ranges)) {
                delivery.ranges.forEach(r => addRangeRow({
                    start: r.start_time,
                    end: r.end_time,
                    capacity: r.capacity
                }));
            }
        }

        function boolFrom(v) {
            return (v === '1' || v === 1 || v === 'true' || v === true);
        }

        if (daySelect) daySelect.addEventListener('change', () => loadDay(parseInt(daySelect.value)));

        // On form submit: collect ranges into hidden JSON
        const tijdslotenForm = document.getElementById('tijdsloten_form');
        if (tijdslotenForm && rangesContainer && rangesHidden) {
            tijdslotenForm.addEventListener('submit', function(e) {
                const rows = Array.from(rangesContainer.querySelectorAll('.range-row'));
                const arr = rows.map(r => {
                    return {
                        start: r.querySelector('.range-start').value,
                        end: r.querySelector('.range-end').value,
                        capacity: r.querySelector('.range-cap').value || null
                    };
                }).filter(r => r.start && r.end);
                rangesHidden.value = JSON.stringify(arr);
            });
        }

        // initial load for day 1 (only if daySelect exists)
        if (daySelect) loadDay(parseInt(daySelect.value));
    </script>

</body>

</html>