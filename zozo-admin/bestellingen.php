<?php
session_start();

// Minimal POST handler for status updates from the modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    // Ensure DB connection
    if (!isset($mysqli)) {
        @include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/DB_connectie.php');
    }

    $orderId = intval($_POST['bestelling_id'] ?? 0);
    $newStatus = trim((string)($_POST['new_status'] ?? ''));
    $newPayment = isset($_POST['new_payment']) ? trim((string)$_POST['new_payment']) : '';

    if ($orderId > 0 && $newStatus !== '') {
        // Build update query and params
        $set = ['STATUS_BESTELLING = ?'];
        $types = 's';
        $params = [$newStatus];

        if ($newPayment !== '') {
            $voldaan = (stripos($newPayment, 'ja') === 0) ? 'ja' : 'nee';
            $set[] = 'VOLDAAN = ?';
            $types .= 's';
            $params[] = $voldaan;

            $set[] = 'reeds_betaald = ?';
            $types .= 's';
            $params[] = $newPayment;
        }

        $sql = 'UPDATE bestellingen SET ' . implode(', ', $set) . ' WHERE bestelling_id = ?';
        $types .= 'i';
        $params[] = $orderId;

        $u = $mysqli->prepare($sql);
        if ($u) {
            // bind_param requires references
            $bind_names = [];
            $bind_names[] = $types;
            for ($i = 0; $i < count($params); $i++) {
                $varName = 'p' . $i;
                $$varName = $params[$i];
                $bind_names[] = &$$varName;
            }
            call_user_func_array([$u, 'bind_param'], $bind_names);
            $u->execute();
            $u->close();
        }

        // Redirect back to avoid form resubmission
        header('Location: /admin/bestellingen?updated=1');
        exit;
    }

    // Bad request -> redirect back
    header('Location: /admin/bestellingen?updated=0');
    exit;
}

?>
<?php
// Timeline view removed per request ("Vandaag per uur") - no timeline SQL or modal output anymore.
// If timeline functionality is re-added later, keep in mind this file previously built
// $orders_by_hour from today's orders and timeslot_reservations and rendered a modal/inline view.
// Prepare basic filter variables (may come from GET)
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$date_filter = $_GET['date'] ?? '';

// Ensure DB connection exists before building filters
if (!isset($mysqli)) {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/DB_connectie.php');
}

// Ensure where_conditions array exists
if (!isset($where_conditions) || !is_array($where_conditions)) {
    $where_conditions = [];
}

// Basic switch to handle a few common filters; currently we only need 'today'
switch ($filter) {
    case 'today':
        $where_conditions[] = "DATE(b.bestelling_datum) = CURDATE()";
        break;
    case 'geannuleerd':
        $where_conditions[] = "b.STATUS_BESTELLING IN ('geannuleerd', 'cancelled')";
        break;
    case 'afgehaald':
        $where_conditions[] = "b.STATUS_BESTELLING = 'delivered' AND LOWER(COALESCE(b.verzendmethode,'')) NOT IN ('levering','delivery')";
        break;
    case 'geleverd':
        $where_conditions[] = "b.STATUS_BESTELLING = 'delivered' AND LOWER(COALESCE(b.verzendmethode,'')) IN ('levering','delivery')";
        break;
    case 'af_te_halen':
        $where_conditions[] = "b.STATUS_BESTELLING NOT IN ('delivered','geannuleerd','cancelled') AND LOWER(COALESCE(b.verzendmethode,'')) NOT IN ('levering','delivery')";
        break;
    case 'te_leveren':
        $where_conditions[] = "b.STATUS_BESTELLING NOT IN ('delivered','geannuleerd','cancelled') AND LOWER(COALESCE(b.verzendmethode,'')) IN ('levering','delivery')";
        break;
    case 'onbetaald':
        $where_conditions[] = "(b.reeds_betaald LIKE 'nee%' OR (b.reeds_betaald IS NULL AND b.VOLDAAN = 'nee'))";
        break;
    case 'betaald':
        $where_conditions[] = "(b.reeds_betaald LIKE 'ja%' OR (b.reeds_betaald IS NULL AND b.VOLDAAN = 'ja'))";
        break;
    case 'betaald_online':
        $where_conditions[] = "b.reeds_betaald = 'ja-online'";
        break;
}

$bezorg_date = $_GET['bezorg_date'] ?? '';

if (!empty($search)) {
    $search_term = $mysqli->real_escape_string($search);
    $where_conditions[] = "(b.factuurnummer LIKE '%$search_term%' OR CONCAT_WS(' ', k.voornaam, k.achternaam) LIKE '%$search_term%' OR b.levernaam LIKE '%$search_term%' OR b.leverplaats LIKE '%$search_term%')";
}

if (!empty($date_filter)) {
    $date_esc = $mysqli->real_escape_string($date_filter);
    $where_conditions[] = "DATE(b.bestelling_datum) = '$date_esc'";
}

if (!empty($bezorg_date)) {
    $bezorg_esc = $mysqli->real_escape_string($bezorg_date);
    $where_conditions[] = "DATE(FROM_UNIXTIME(b.UNIX_bezorgmoment)) = '$bezorg_esc'";
}

$where_sql = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get orders with customer info (assuming there's a klanten table)
$sql = "SELECT b.*,
COALESCE(k.voornaam, '') as klant_voornaam,
COALESCE(k.achternaam, '') as klant_achternaam,
COALESCE(k.email, '') as klant_email
FROM bestellingen b
LEFT JOIN klanten k ON b.klant_id = k.klant_id
$where_sql
ORDER BY b.bestelling_datum DESC";
$result = $mysqli->query($sql);

// Build combined list of today's scheduled items (orders + reservations) grouped by hour
// Orders: use UNIX_bezorgmoment when present, otherwise fallback to bestelling_datum
$orders_by_hour = [];

// 1) Today's orders (use delivery/pickup moment if available)
$today_orders_sql = "SELECT b.*, COALESCE(b.UNIX_bezorgmoment, UNIX_TIMESTAMP(b.bestelling_datum)) AS display_unix, HOUR(FROM_UNIXTIME(COALESCE(b.UNIX_bezorgmoment, UNIX_TIMESTAMP(b.bestelling_datum)))) AS order_hour, COALESCE(k.voornaam, '') as klant_voornaam, COALESCE(k.achternaam, '') as klant_achternaam
FROM bestellingen b
LEFT JOIN klanten k ON b.klant_id = k.klant_id
WHERE DATE(FROM_UNIXTIME(COALESCE(b.UNIX_bezorgmoment, UNIX_TIMESTAMP(b.bestelling_datum)))) = CURDATE()
ORDER BY display_unix ASC";
$today_orders_res = $mysqli->query($today_orders_sql);
if ($today_orders_res) {
    while ($order = $today_orders_res->fetch_assoc()) {
        $hour = (int)$order['order_hour'];
        if (!isset($orders_by_hour[$hour])) $orders_by_hour[$hour] = [];
        // determine mode: delivery vs pickup
        $verzend = strtolower(trim($order['verzendmethode'] ?? 'afhalen'));
        $mode = (in_array($verzend, ['levering', 'delivery'])) ? 'levering' : 'afhalen';
        $order['mode'] = $mode;
        $order['item_type'] = 'order';
        $orders_by_hour[$hour][] = $order;
    }
}

// 2) Today's reservations from timeslot_reservations (include reserved and confirmed)
$res_sql = "SELECT r.*, COALESCE(r.reserved_unix, UNIX_TIMESTAMP(r.reserved_at)) AS display_unix, HOUR(FROM_UNIXTIME(COALESCE(r.reserved_unix, UNIX_TIMESTAMP(r.reserved_at)))) AS order_hour, t.type AS slot_type
FROM timeslot_reservations r
LEFT JOIN timeslot_fixed_ranges t ON t.id = r.timeslot_id
WHERE DATE(FROM_UNIXTIME(COALESCE(r.reserved_unix, UNIX_TIMESTAMP(r.reserved_at)))) = CURDATE()
AND r.status IN ('reserved','confirmed')
ORDER BY display_unix ASC";
$res_res = $mysqli->query($res_sql);
if ($res_res) {
    while ($r = $res_res->fetch_assoc()) {
        $hour = (int)$r['order_hour'];
        if (!isset($orders_by_hour[$hour])) $orders_by_hour[$hour] = [];
        // slot_type is 'pickup' or 'delivery' (or similar)
        $stype = strtolower(trim($r['slot_type'] ?? 'pickup'));
        $mode = ($stype === 'delivery' || $stype === 'levering') ? 'levering' : 'afhalen';
        $r['mode'] = $mode;
        $r['item_type'] = 'reservation';
        $orders_by_hour[$hour][] = $r;
    }
}

// sort hours ascending to ensure display order
ksort($orders_by_hour);

// Get order statistics
$stats_sql = "SELECT
COUNT(*) as total_orders,
COUNT(CASE WHEN DATE(bestelling_datum) = CURDATE() THEN 1 END) as today_orders,
COUNT(CASE WHEN STATUS_BESTELLING = 'pending' THEN 1 END) as pending_orders,
COUNT(CASE WHEN STATUS_BESTELLING = 'processing' THEN 1 END) as processing_orders,
SUM(bestelling_tebetalen) as total_revenue,
SUM(CASE WHEN DATE(bestelling_datum) = CURDATE() THEN bestelling_tebetalen ELSE 0 END) as today_revenue,
COUNT(CASE WHEN (STATUS_BESTELLING NOT IN ('delivered','geannuleerd','cancelled') AND LOWER(COALESCE(verzendmethode,'')) NOT IN ('levering','delivery')) THEN 1 END) as af_te_halen_orders,
COUNT(CASE WHEN (STATUS_BESTELLING NOT IN ('delivered','geannuleerd','cancelled') AND LOWER(COALESCE(verzendmethode,'')) IN ('levering','delivery')) THEN 1 END) as te_leveren_orders
FROM bestellingen";
$stats_result = $mysqli->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// (orders_by_hour has already been built above from orders and reservations)
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Bestellingen</title>
    <link rel="stylesheet" href="/zozo-admin/css/admin-built.css">
    <link rel="stylesheet" href="/zozo-admin/css/navbar.css">
    <link rel="stylesheet" href="/zozo-admin/css/main.css">
    <link rel="stylesheet" href="/zozo-admin/css/bestellingen.css">
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-admin/templates/navbar.php'); ?>

    <main class="max-w-7xl mx-auto p-6 mt-8">
        <!-- Success Message -->
        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>



        <div class="bg-white rounded-lg shadow-md">
            <!-- Header -->
            <div class="border-b border-gray-200 p-6 orders-header">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Bestellingen beheren</h1>
                        <p class="text-gray-600 mt-2">Overzicht van alle bestellingen en leveringen</p>
                    </div>
                    <div class="flex space-x-3 header-actions">
                        <button onclick="refreshOrders()"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <span>Vernieuwen</span>
                        </button>
                        <!-- 'Vandaag per uur' button removed -->
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="p-6 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">

                    <a href="/admin/bestellingen?filter=af_te_halen" class="group block">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 hover:shadow-lg transition-shadow">
                            <div class="text-yellow-600 text-sm font-medium">Af te halen</div>
                            <div class="text-2xl font-bold text-yellow-900"><?= intval($stats['af_te_halen_orders'] ?? 0) ?></div>
                        </div>
                    </a>

                    <a href="/admin/bestellingen?filter=te_leveren" class="group block">
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 hover:shadow-lg transition-shadow">
                            <div class="text-purple-600 text-sm font-medium">Te leveren</div>
                            <div class="text-2xl font-bold text-purple-900"><?= intval($stats['te_leveren_orders'] ?? 0) ?></div>
                        </div>
                    </a>


                </div>
            </div>

            <!-- Filters -->
            <div class="p-6 border-b border-gray-200">
                <form method="get" class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-64">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Zoeken:</label>
                        <input type="text" name="search" placeholder="Factuurnummer, klant, plaats..."
                            value="<?= htmlspecialchars($search) ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status:</label>
                        <select name="filter"
                            class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Alle statussen</option>
                            <option value="geannuleerd" <?= $filter === 'geannuleerd' ? 'selected' : '' ?>>Geannuleerd</option>
                            <option value="afgehaald" <?= $filter === 'afgehaald' ? 'selected' : '' ?>>Afgehaald</option>
                            <option value="geleverd" <?= $filter === 'geleverd' ? 'selected' : '' ?>>Geleverd</option>
                            <option value="af_te_halen" <?= $filter === 'af_te_halen' ? 'selected' : '' ?>>Af te halen</option>
                            <option value="te_leveren" <?= $filter === 'te_leveren' ? 'selected' : '' ?>>Te leveren</option>
                            <option value="onbetaald" <?= $filter === 'onbetaald' ? 'selected' : '' ?>>Onbetaald</option>
                            <option value="betaald" <?= $filter === 'betaald' ? 'selected' : '' ?>>Betaald</option>
                            <option value="betaald_online" <?= $filter === 'betaald_online' ? 'selected' : '' ?>>Betaald (online)</option>
                            <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Vandaag</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Besteldatum:</label>
                        <input type="date" name="date" value="<?= htmlspecialchars($date_filter) ?>"
                            class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Datum (afhaling/levering):</label>
                        <input type="date" name="bezorg_date" value="<?= htmlspecialchars($_GET['bezorg_date'] ?? '') ?>"
                            class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors">
                            Filteren
                        </button>
                        <?php if ($search || $filter !== 'all' || $date_filter || $bezorg_date): ?>
                            <a href="/admin/bestellingen" class="ml-2 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md transition-colors">
                                Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="overflow-x-auto">
                <table class="orders-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bestelling</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Klant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Datum</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bedrag</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Betaling</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acties</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($result->num_rows === 0): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <div class="text-lg mb-2">Geen bestellingen gevonden</div>
                                    <div class="text-sm">Pas je filter aan of controleer de datum.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $slot_cache = []; ?>
                            <?php while ($order = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4" data-label="Bestelling">
                                        <div class="text-sm font-medium text-gray-900">
                                            #<?= htmlspecialchars($order['factuurnummer'] ?: $order['bestelling_id']) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php if (!empty($order['bestelling_datum'])): ?>
                                                <?= date('d-m-Y', strtotime($order['bestelling_datum'])) ?><br>
                                                <?= date('H') . 'u' . date('i', strtotime($order['bestelling_datum'])) ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" data-label="Klant">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($order['levernaam'] ?: ($order['klant_voornaam'] . ' ' . $order['klant_achternaam'])) ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?= htmlspecialchars($order['leverplaats']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" data-label="Datum / Slot">
                                        <?php
                                        // show the customer requested slot time (UNIX_bezorgmoment) if present, prefer full interval
                                        $slotUnix = isset($order['UNIX_bezorgmoment']) && $order['UNIX_bezorgmoment'] ? (int)$order['UNIX_bezorgmoment'] : 0;
                                        $slotId = isset($order['bezorg_slot_id']) ? (int)$order['bezorg_slot_id'] : 0;

                                        if ($slotUnix > 0 && $slotId > 0) {
                                            // try cached lookup first
                                            if (!isset($slot_cache[$slotId])) {
                                                $s3 = $mysqli->prepare("SELECT start_time, end_time, preparation_minutes FROM timeslot_fixed_ranges WHERE id = ? LIMIT 1");
                                                if ($s3) {
                                                    $s3->bind_param('i', $slotId);
                                                    $s3->execute();
                                                    $r3 = $s3->get_result();
                                                    $slot_row = $r3 ? $r3->fetch_assoc() : null;
                                                    // If end_time is missing, try to locate the correct timeslot using the stored UNIX_bezorgmoment
                                                    if (($slot_row === null || empty($slot_row['end_time'])) && $slotUnix > 0) {
                                                        $dayOfWeek = (int)date('N', $slotUnix);
                                                        $timeStr = date('H:i:s', $slotUnix);
                                                        $s_alt = $mysqli->prepare("SELECT id, start_time, end_time FROM timeslot_fixed_ranges WHERE day_of_week = ? AND start_time <= ? AND end_time > ? ORDER BY start_time LIMIT 1");
                                                        if ($s_alt) {
                                                            $s_alt->bind_param('iss', $dayOfWeek, $timeStr, $timeStr);
                                                            $s_alt->execute();
                                                            $r_alt = $s_alt->get_result();
                                                            $alt_row = $r_alt ? $r_alt->fetch_assoc() : null;
                                                            if ($alt_row) {
                                                                $slot_row = $alt_row;
                                                            }
                                                            $s_alt->close();
                                                        }
                                                    }
                                                    $slot_cache[$slotId] = $slot_row;
                                                    $s3->close();
                                                } else {
                                                    $slot_cache[$slotId] = null;
                                                }
                                            }

                                            $ts = $slot_cache[$slotId];
                                            echo '<div>' . date('d-m-Y', $slotUnix) . '</div>';
                                            if ($ts && !empty($ts['start_time'])) {
                                                $startTs = strtotime($ts['start_time']);
                                                $endTs = !empty($ts['end_time']) ? strtotime($ts['end_time']) : false;
                                                if ($startTs !== false && $endTs !== false) {
                                                    echo '<div class="text-gray-500">' . date('H', $startTs) . 'u' . date('i', $startTs) . ' - ' . date('H', $endTs) . 'u' . date('i', $endTs) . '</div>';
                                                } else {
                                                    echo '<div class="text-gray-500">' . htmlspecialchars(trim(($ts['start_time'] ?? '') . ' - ' . ($ts['end_time'] ?? ''))) . '</div>';
                                                }
                                            } else {
                                                // fallback: show the time component of the unix timestamp
                                                echo '<div class="text-gray-500">' . date('H', $slotUnix) . 'u' . date('i', $slotUnix) . '</div>';
                                            }
                                        } elseif ($slotUnix > 0) {
                                            // no slot id: try to infer the timeslot row based on the unix moment and show full interval if found
                                            echo '<div>' . date('d-m-Y', $slotUnix) . '</div>';
                                            $dayOfWeek = (int)date('N', $slotUnix); // 1 (Mon) - 7 (Sun)
                                            $timeStr = date('H:i:s', $slotUnix);

                                            // Try to find a timeslot where start_time <= time < end_time for that day
                                            $ts_found = null;
                                            $s4 = $mysqli->prepare("SELECT id, start_time, end_time FROM timeslot_fixed_ranges WHERE day_of_week = ? AND start_time <= ? AND end_time > ? ORDER BY start_time LIMIT 1");
                                            if ($s4) {
                                                $s4->bind_param('iss', $dayOfWeek, $timeStr, $timeStr);
                                                $s4->execute();
                                                $r4 = $s4->get_result();
                                                $ts_found = $r4 ? $r4->fetch_assoc() : null;
                                                $s4->close();
                                            }

                                            if ($ts_found && !empty($ts_found['start_time'])) {
                                                $startTs = strtotime($ts_found['start_time']);
                                                $endTs = !empty($ts_found['end_time']) ? strtotime($ts_found['end_time']) : false;
                                                if ($startTs !== false && $endTs !== false) {
                                                    echo '<div class="text-gray-500">' . date('H', $startTs) . 'u' . date('i', $startTs) . ' - ' . date('H', $endTs) . 'u' . date('i', $endTs) . '</div>';
                                                } else {
                                                    echo '<div class="text-gray-500">' . htmlspecialchars(trim(($ts_found['start_time'] ?? '') . ' - ' . ($ts_found['end_time'] ?? ''))) . '</div>';
                                                }
                                            } else {
                                                // fallback: just show the time component
                                                echo '<div class="text-gray-500">' . date('H', $slotUnix) . 'u' . date('i', $slotUnix) . '</div>';
                                            }
                                        } elseif (!empty($order['bestelling_datum'])) {
                                            echo '<div>' . date('d-m-Y', strtotime($order['bestelling_datum'])) . '</div>';
                                            echo '<div class="text-gray-500">' . date('H', strtotime($order['bestelling_datum'])) . 'u' . date('i', strtotime($order['bestelling_datum'])) . '</div>';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" data-label="Bedrag">
                                        €&nbsp;<?= number_format($order['bestelling_tebetalen'], 2, ',', '.') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap" data-label="Status">
                                        <?php
                                        // Determine display label and color based on verzendmethode and stored status
                                        $dbStatus = $order['STATUS_BESTELLING'];
                                        $verzend = strtolower(trim($order['verzendmethode'] ?? 'afhalen'));

                                        // default mapping
                                        $displayLabel = '';
                                        $color_class = 'bg-gray-100 text-gray-800';

                                        if ($dbStatus === 'cancelled' || $dbStatus === 'geannuleerd') {
                                            $displayLabel = 'Geannuleerd';
                                            $color_class = 'bg-red-100 text-red-800';
                                        } elseif ($dbStatus === 'delivered') {
                                            if ($verzend === 'levering' || $verzend === 'delivery') {
                                                $displayLabel = 'Geleverd';
                                            } else {
                                                $displayLabel = 'Afgehaald';
                                            }
                                            $color_class = 'bg-green-100 text-green-800';
                                        } else {
                                            // not yet completed => show pending pickup or pending delivery
                                            if ($verzend === 'levering' || $verzend === 'delivery') {
                                                $displayLabel = 'Te leveren';
                                                $color_class = 'bg-blue-100 text-blue-800';
                                            } else {
                                                $displayLabel = 'Af te halen';
                                                $color_class = 'bg-yellow-100 text-yellow-800';
                                            }
                                        }
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $color_class ?>">
                                            <?= htmlspecialchars($displayLabel) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap" data-label="Betaling">
                                        <?php
                                        // Determine payment state from 'reeds_betaald' if available, otherwise fallback to VOLDAAN
                                        $pay = $order['reeds_betaald'] ?? null;
                                        if (!$pay) {
                                            // fallback: use VOLDAAN and assume cash
                                            $pay = ($order['VOLDAAN'] === 'ja') ? 'ja-cash' : 'nee-cash';
                                        }

                                        // Map to icon + label
                                        $payParts = explode('-', $pay);
                                        $payStatus = $payParts[0] ?? 'nee';
                                        $payMethod = $payParts[1] ?? 'cash';

                                        $icon = '';
                                        $label = '';
                                        $bg = ($payStatus === 'ja') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';

                                        if ($payMethod === 'online') {
                                            // bank card icon (SVG)
                                            $icon = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="5" width="20" height="14" rx="2" ry="2" stroke-width="1.5"></rect><path d="M2 10h20" stroke-width="1.5"></path></svg>';
                                        } else {
                                            // cash icon (SVG)
                                            $icon = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="4" width="22" height="16" rx="2" ry="2" stroke-width="1.5"></rect><circle cx="12" cy="12" r="2" stroke-width="1.5"></circle></svg>';
                                        }

                                        if ($payStatus === 'ja') {
                                            $label = ($payMethod === 'online') ? 'Betaald (online)' : 'Betaald';
                                        } else {
                                            $label = ($payMethod === 'online') ? 'Onbetaald (online)' : 'Onbetaald';
                                        }
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $bg ?>">
                                            <?= $icon ?><?= htmlspecialchars($label) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2" data-label="Acties">
                                        <button onclick="viewOrder(<?= $order['bestelling_id'] ?>)"
                                            class="text-blue-600 hover:text-blue-900 transition-colors">
                                            Bekijken
                                        </button>
                                        <button onclick="updateStatus(<?= $order['bestelling_id'] ?>, '<?= $order['STATUS_BESTELLING'] ?>', '<?= htmlspecialchars($order['verzendmethode'] ?? 'afhalen') ?>', '<?= htmlspecialchars($order['reeds_betaald'] ?? (($order['VOLDAAN'] === 'ja') ? 'ja-cash' : 'nee-cash')) ?>')"
                                            class="text-green-600 hover:text-green-900 transition-colors">
                                            Status
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Timeline removed -->

        <!-- Status Update Modal -->
        <div id="status-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
                    <form method="post" action="/admin/bestellingen" class="p-6">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="bestelling_id" id="status-order-id">

                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-2xl font-bold text-gray-800">Status wijzigen</h2>
                            <button type="button" onclick="closeStatusModal()"
                                class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nieuwe status:</label>
                                <select name="new_status" id="status-select" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </select>
                            </div>
                            <div id="payment-control" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Betaling:</label>
                                <select name="new_payment" id="payment-select" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    <option value="ja-online">Betaald (online)</option>
                                    <option value="nee-online">Onbetaald (online)</option>
                                    <option value="ja-cash">Betaald (contant)</option>
                                    <option value="nee-cash">Onbetaald (contant)</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex space-x-3 mt-6">
                            <button type="submit"
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md transition-colors">
                                Opslaan
                            </button>
                            <button type="button" onclick="closeStatusModal()"
                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded-md transition-colors">
                                Annuleren
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <!-- Order details modal -->
    <div id="order-detail-modal" class="fixed inset-0 bg-black bg-opacity-50 z-9999 hidden" style="z-index:9999;">
        <div class="flex items-center justify-center min-h-screen p-4" style="z-index:10000;">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-screen overflow-y-auto" style="z-index:10001;">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold text-gray-800">Bestelling details <span id="order-detail-order-id" class="text-sm text-gray-500 ml-2 font-normal"></span></h2>
                        <button id="order-detail-close" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="order-detail-content">
                        <!-- content populated via AJAX -->
                        <div class="text-gray-600">Laden…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openTodayTimeline() {
            document.getElementById('timeline-modal').classList.remove('hidden');
        }

        function closeTodayTimeline() {
            document.getElementById('timeline-modal').classList.add('hidden');
        }

        function filterTimeline(e, mode) {
            if (e) e.preventDefault();
            // highlight active link
            document.querySelectorAll('.timeline-filter').forEach(function(a) {
                a.classList.remove('text-blue-600', 'font-medium');
                a.classList.add('text-gray-700');
            });
            var active = document.querySelector('.timeline-filter[data-filter="' + mode + '"]');
            if (active) {
                active.classList.add('text-blue-600', 'font-medium');
                active.classList.remove('text-gray-700');
            }

            // show/hide items
            document.querySelectorAll('.timeline-item').forEach(function(item) {
                var m = item.getAttribute('data-mode') || 'afhalen';
                if (mode === 'all' || mode === m) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function updateStatus(orderId, currentStatus, verzendmethode, currentPayment) {
            document.getElementById('status-order-id').value = orderId;
            // build options based on verzendmethode
            var select = document.getElementById('status-select');
            select.innerHTML = '';
            var v = (verzendmethode || 'afhalen').toString().toLowerCase();
            var opts = [];
            // common cancel option
            if (v === 'levering' || v === 'delivery') {
                opts = [{
                        value: 'te_leveren',
                        label: 'Te leveren'
                    },
                    {
                        value: 'delivered',
                        label: 'Geleverd'
                    },
                    {
                        value: 'cancelled',
                        label: 'Geannuleerd'
                    }
                ];
            } else {
                opts = [{
                        value: 'af_te_halen',
                        label: 'Af te halen'
                    },
                    {
                        value: 'delivered',
                        label: 'Afgehaald'
                    },
                    {
                        value: 'cancelled',
                        label: 'Geannuleerd'
                    }
                ];
            }
            opts.forEach(function(o) {
                var el = document.createElement('option');
                el.value = o.value;
                el.textContent = o.label;
                select.appendChild(el);
            });

            // try to map currentStatus to one of the new option values
            var mapCurrent = function(cs) {
                if (!cs) return '';
                cs = cs.toString().toLowerCase();
                if (cs === 'cancelled' || cs === 'geannuleerd') return 'cancelled';
                if (cs === 'delivered' || cs === 'afgeleverd' || cs === 'afgehaald') return 'delivered';
                // otherwise assume not delivered yet
                return (v === 'levering' || v === 'delivery') ? 'te_leveren' : 'af_te_halen';
            };

            var mapped = mapCurrent(currentStatus);
            try {
                select.value = mapped;
            } catch (e) {}

            // payment control: show when currentPayment indicates unpaid (starts with 'nee')
            try {
                var payControl = document.getElementById('payment-control');
                var paySelect = document.getElementById('payment-select');
                if (payControl && paySelect) {
                    if (currentPayment && currentPayment.toString().toLowerCase().startsWith('nee')) {
                        payControl.classList.remove('hidden');
                    } else {
                        payControl.classList.add('hidden');
                    }
                    try {
                        paySelect.value = currentPayment || paySelect.value;
                    } catch (e) {}
                }
            } catch (e) {}

            document.getElementById('status-modal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('status-modal').classList.add('hidden');
        }

        function viewOrder(orderId) {
            // Open modal and load details via AJAX
            var modal = document.getElementById('order-detail-modal');
            var content = document.getElementById('order-detail-content');
            modal.classList.remove('hidden');
            content.innerHTML = '<div class="text-gray-600">Laden…</div>';

            // small helpers
            function escapeHtml(str) {
                if (str === null || str === undefined) return '';
                return String(str).replace(/[&"'<>]/g, function(s) {
                    return ({
                        '&': '&amp;',
                        '"': '&quot;',
                        "'": '&#39;',
                        '<': '&lt;',
                        '>': '&gt;'
                    })[s];
                });
            }

            function nl2brSafe(str) {
                return escapeHtml(str).replace(/\r?\n/g, '<br>');
            }

            function formatCurrency(value) {
                try {
                    return new Intl.NumberFormat('nl-BE', {
                        style: 'currency',
                        currency: 'EUR'
                    }).format(value);
                } catch (e) {
                    return '€ ' + (parseFloat(value || 0).toFixed(2));
                }
            }

            fetch('/zozo-admin/get_order.php?id=' + encodeURIComponent(orderId))
                .then(function(resp) {
                    if (resp.status === 401) {
                        content.innerHTML = '<div class="text-red-600">Niet geautoriseerd. Log in als admin.</div>';
                        throw new Error('Unauthorized');
                    }
                    return resp.text();
                })
                .then(function(txt) {
                    var data;
                    try {
                        data = JSON.parse(txt);
                    } catch (e) {
                        console.error('get_order.php returned invalid JSON:', txt);
                        content.innerHTML = '<div class="text-red-600">Kon bestelling niet laden (ongeldig antwoord van server).</div>';
                        throw e;
                    }

                    if (!data || !data.ok) {
                        var errMsg = data && data.error ? escapeHtml(data.error) : 'Kon bestelling niet laden.';
                        content.innerHTML = '<div class="text-red-600">' + errMsg + '</div>';
                        return;
                    }

                    var o = data.order;
                    // set small order id in the modal title
                    try {
                        var idSpan = document.getElementById('order-detail-order-id');
                        if (idSpan) {
                            idSpan.textContent = o.bestelling_id ? ('order ID ' + o.bestelling_id) : '';
                        }
                    } catch (e) {}
                    var html = '';
                    html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';
                    // Build display name: prefer bedrijfsnaam when present
                    try {
                        var nameDisplay = '';
                        if (o.klant_bedrijfsnaam && o.klant_bedrijfsnaam.toString().trim() !== '') {
                            nameDisplay = o.klant_bedrijfsnaam + ' - ' + (o.levernaam || '');
                        } else {
                            nameDisplay = o.levernaam || '';
                        }
                        html += '<div><strong>Naam:</strong> ' + escapeHtml(nameDisplay) + '</div>';
                    } catch (e) {
                        html += '<div><strong>Naam:</strong> ' + escapeHtml(o.levernaam || '') + '</div>';
                    }

                    // Right column: Telefoon (icon) (prefer klant_telefoon which may include guest fallback)
                    try {
                        var phone = o.klant_telefoon || '';
                        var phoneHtml = '<div class="flex items-center">' +
                            '<svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h1.6a1 1 0 01.98.79l.35 1.8a1 1 0 01-.27.88L6.4 8.6a15.05 15.05 0 006.99 6.99l1.28-1.02a1 1 0 01.88-.27l1.8.35a1 1 0 01.79.98V19a2 2 0 01-2 2h-0C7.82 21 3 16.18 3 10V5z"></path>' +
                            '</svg>' +
                            '<span>' + escapeHtml(phone || '-') + '</span>' +
                            '</div>';
                        html += phoneHtml;
                    } catch (e) {
                        html += '<div class="flex items-center"><svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h1.6a1 1 0 01.98.79l.35 1.8a1 1 0 01-.27.88L6.4 8.6a15.05 15.05 0 006.99 6.99l1.28-1.02a1 1 0 01.88-.27l1.8.35a1 1 0 01.79.98V19a2 2 0 01-2 2h-0C7.82 21 3 16.18 3 10V5z"></path></svg>-</div>';
                    }

                    // Combine address into one line: straat, postcode plaats, land (omit land when Belgium)
                    try {
                        var addrParts = [];
                        if (o.leverstraat && o.leverstraat.toString().trim() !== '') addrParts.push(o.leverstraat);
                        var cityParts = [];
                        if (o.leverpostcode && o.leverpostcode.toString().trim() !== '') cityParts.push(o.leverpostcode);
                        if (o.leverplaats && o.leverplaats.toString().trim() !== '') cityParts.push(o.leverplaats);
                        if (cityParts.length) addrParts.push(cityParts.join(' '));
                        var country = (o.leverland || '').toString().trim();
                        var cn = country.toLowerCase();
                        // treat common Belgian variants as Belgium
                        var isBelgium = (cn.indexOf('belg') !== -1 || cn.indexOf('belgië') !== -1 || cn.indexOf('belgie') !== -1 || cn.indexOf('belgium') !== -1);
                        if (country !== '' && !isBelgium) addrParts.push(country);
                        var addrLine = addrParts.join(', ');
                        var vm = (o.verzendmethode || '').toString().toLowerCase();
                        var addressLabel = 'Adres';
                        if (vm === 'afhalen') {
                            addressLabel = 'Afhalen';
                        } else if (vm === 'levering' || vm === 'delivery') {
                            addressLabel = 'Levering';
                        }
                        if (addressLabel === 'Afhalen') {
                            html += '<div><strong>' + escapeHtml(addressLabel) + '</strong></div>';
                        } else {
                            html += '<div><strong>' + escapeHtml(addressLabel) + ':</strong> ' + escapeHtml(addrLine) + '</div>';
                        }
                    } catch (e) {
                        var vm = (o.verzendmethode || '').toString().toLowerCase();
                        var addressLabel = 'Adres';
                        if (vm === 'afhalen') {
                            addressLabel = 'Afhalen';
                        } else if (vm === 'levering' || vm === 'delivery') {
                            addressLabel = 'Levering';
                        }
                        if (addressLabel === 'Afhalen') {
                            html += '<div><strong>' + escapeHtml(addressLabel) + '</strong></div>';
                        } else {
                            html += '<div><strong>' + escapeHtml(addressLabel) + ':</strong> ' + escapeHtml(((o.leverstraat || '') + ' ' + (o.leverpostcode || '') + ' ' + (o.leverplaats || '')).trim()) + '</div>';
                        }
                    }

                    // Email on the right column (icon)
                    try {
                        var email = o.klant_email || '';
                        var emailHtml = '<div class="flex items-center">' +
                            '<svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8m0 8V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2h14a2 2 0 002-2z"></path>' +
                            '</svg>' +
                            '<span>' + escapeHtml(email || '-') + '</span>' +
                            '</div>';
                        html += emailHtml;
                    } catch (e) {
                        html += '<div class="flex items-center"><svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8m0 8V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2h14a2 2 0 002-2z"></path></svg>-</div>';
                    }
                    // Prefer a resolved timeslot label (slot_label) when available, otherwise show bestelling_datum
                    var dateLabel = '';
                    if (o.slot_label) {
                        // show date from UNIX_bezorgmoment if available, else fallback to bestelling_datum
                        var d = o.UNIX_bezorgmoment ? new Date(o.UNIX_bezorgmoment * 1000) : (o.bestelling_datum ? new Date(o.bestelling_datum) : null);
                        if (d) {
                            var dd = ('0' + d.getDate()).slice(-2) + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + d.getFullYear();
                            dateLabel = dd + '\n' + o.slot_label;
                        } else {
                            dateLabel = o.slot_label;
                        }
                    } else if (o.slot_start_time && o.slot_end_time) {
                        var d = o.UNIX_bezorgmoment ? new Date(o.UNIX_bezorgmoment * 1000) : (o.bestelling_datum ? new Date(o.bestelling_datum) : null);
                        var dd = d ? (('0' + d.getDate()).slice(-2) + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + d.getFullYear()) : (o.bestelling_datum || '');
                        // format times with 'u'
                        var st = o.slot_start_time.split(':');
                        var et = o.slot_end_time.split(':');
                        var timeLabel = (st[0] || '') + 'u' + (st[1] || '') + ' - ' + (et[0] || '') + 'u' + (et[1] || '');
                        dateLabel = dd + '\n' + timeLabel;
                    } else {
                        dateLabel = o.bestelling_datum || '';
                    }
                    // Date and total as subtle badges (remove labels)
                    html += '<div><span class="inline-flex items-center px-2 py-1 rounded text-sm bg-gray-100 text-gray-700">' + escapeHtml(dateLabel) + '</span></div>';
                    html += '<div><span class="inline-flex items-center px-2 py-1 rounded text-sm bg-gray-100 text-gray-700">' + formatCurrency(o.bestelling_tebetalen ? parseFloat(o.bestelling_tebetalen) : 0) + '</span></div>';
                    html += '</div>';

                    // If items are provided (array), render them in a clean list, else show message
                    if (Array.isArray(data.items) && data.items.length > 0) {
                        html += '<div class="mb-4"><h3 class="font-semibold mb-3">Artikelen</h3>';
                        html += '<div class="space-y-4">';
                        data.items.forEach(function(it) {
                            var raw = it.raw || it;
                            var name = it.name || it.product_naam || raw.omschrijving || raw.title || '';
                            var qty = parseInt(it.aantal || it.qty || raw.aantal || raw.quantity || 1, 10) || 1;
                            var image = it.image || raw.afbeelding || raw.image || '';

                            // helpers for numbers
                            function parseNum(v) {
                                var n = parseFloat(v);
                                return isNaN(n) ? 0 : n;
                            }
                            var btwRate = parseInt(it.BTWtarief || raw.BTWtarief || it.btw || raw.btw || 21, 10) || 0;

                            // Determine inclusive unit price. Prefer explicit inclusive fields (prijs_incl / price),
                            // otherwise compute from stored kostprijs (assumed excl. BTW).
                            var unitPriceIncl = 0.0;
                            if (it.prijs_incl !== undefined) unitPriceIncl = parseNum(it.prijs_incl);
                            else if (raw.prijs_incl !== undefined) unitPriceIncl = parseNum(raw.prijs_incl);
                            else if (it.price !== undefined) unitPriceIncl = parseNum(it.price);
                            else if (raw.price !== undefined) unitPriceIncl = parseNum(raw.price);
                            else {
                                var unitExcl = parseNum(it.prijs || raw.kostprijs || raw.prijs || 0);
                                if (btwRate === 0) unitPriceIncl = unitExcl;
                                else unitPriceIncl = unitExcl * (1 + (btwRate / 100.0));
                            }

                            // compact price formatter: '42,-' if whole euro, else localized currency
                            function formatPriceShort(value) {
                                var v = Math.round((parseFloat(value || 0) + Number.EPSILON) * 100) / 100;
                                var roundedInt = Math.round(v);
                                if (Math.abs(v - roundedInt) < 0.005) {
                                    // show as '42,-' (no euro sign)
                                    return String(roundedInt) + ',-';
                                }
                                // show with comma as decimal separator (nl-BE)
                                try {
                                    return v.toLocaleString('nl-BE', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                } catch (e) {
                                    return (v.toFixed(2)).replace('.', ',');
                                }
                            }

                            // compact options text
                            var optsText = '';
                            try {
                                var opts = raw.options || raw.opties || [];
                                if (Array.isArray(opts) && opts.length > 0) {
                                    optsText = opts.map(function(o) {
                                        return o.label || o.option_name || o.name || (o.group_name ? (o.group_name + ': ' + (o.label || o.option_name || o.value)) : '');
                                    }).filter(Boolean).join(' · ');
                                }
                            } catch (e) {
                                optsText = '';
                            }

                            var subtotal = (unitPriceIncl * qty) || 0;

                            html += '<div class="flex items-start space-x-4 border p-3 rounded">';
                            if (image) {
                                // ensure leading slash
                                var imgSrc = (image.indexOf('/') === -1) ? '/upload/' + image : image;
                                html += '<div class="w-16 h-16 flex-shrink-0">';
                                html += '<img src="' + escapeHtml(imgSrc) + '" alt="' + escapeHtml(name) + '" class="w-16 h-16 object-cover rounded">';
                                html += '</div>';
                            } else {
                                // placeholder
                                html += '<div class="w-16 h-16 flex-shrink-0 flex items-center justify-center bg-gray-100 rounded text-gray-500">';
                                html += '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7M16 3v4M8 3v4m-4 4h16"/></svg>';
                                html += '</div>';
                            }
                            html += '<div class="flex-1">';
                            html += '<div class="font-medium">' + nl2brSafe(name) + '</div>';
                            if (optsText) html += '<div class="text-xs text-gray-600 mt-1">' + escapeHtml(optsText) + '</div>';
                            // compact row: price incl., aantal, subtotal (incl.)
                            html += '<div class="text-sm text-gray-700 mt-2 flex items-center">';
                            html += '<div class="w-28"><strong>' + escapeHtml(formatPriceShort(unitPriceIncl)) + '</strong></div>';
                            html += '<div class="ml-4">Aantal: <strong>' + escapeHtml(qty) + '</strong></div>';
                            html += '<div class="ml-auto font-medium"><strong>' + escapeHtml(formatPriceShort(subtotal)) + '</strong></div>';
                            html += '</div></div></div>';
                        });
                        html += '</div></div>';
                    } else {
                        html += '<div class="text-sm text-gray-600 mb-4">Geen artikeldetails beschikbaar.</div>';
                    }

                    // Status and payment info
                    var statusLabels = {
                        'pending': 'In behandeling',
                        'processing': 'Wordt verwerkt',
                        'shipped': 'Verzonden',
                        'delivered': 'Geleverd',
                        'cancelled': 'Geannuleerd'
                    };
                    var statusText = statusLabels[o.STATUS_BESTELLING] || o.STATUS_BESTELLING || '';
                    html += '<div class="flex items-center justify-between">';
                    html += '<div><strong>Status:</strong> ' + escapeHtml(statusText) + '</div>';
                    html += '<div><strong>Betaald:</strong> ' + ((o.VOLDAAN === 'ja') ? 'Ja' : 'Nee') + '</div>';
                    html += '</div>';

                    content.innerHTML = html;
                })
                .catch(function(err) {
                    if (err && err.message === 'Unauthorized') return; // already handled
                    console.error(err);
                    content.innerHTML = '<div class="text-red-600">Fout bij laden bestelling.</div>';
                });
        }

        // Close order detail modal
        var orderDetailCloseBtn = document.getElementById('order-detail-close');
        if (orderDetailCloseBtn) {
            orderDetailCloseBtn.addEventListener('click', function() {
                var odm = document.getElementById('order-detail-modal');
                if (odm) odm.classList.add('hidden');
            });
        }

        function refreshOrders() {
            location.reload();
        }

        // Click outside modal to close (only wire handlers if the elements exist)
        var timelineModalEl = document.getElementById('timeline-modal');
        if (timelineModalEl) {
            timelineModalEl.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (typeof closeTodayTimeline === 'function') closeTodayTimeline();
                }
            });
        }

        var statusModalEl = document.getElementById('status-modal');
        if (statusModalEl) {
            statusModalEl.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (typeof closeStatusModal === 'function') closeStatusModal();
                }
            });
        }
    </script>
</body>

</html>