<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Controleer of gebruiker is ingelogd, anders redirect naar login.php
//if (!isset($_SESSION['admin_logged_in'])) {
//   header('Location: login.php');
//   exit;
//}

// DB connection
if (!isset($mysqli)) {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/DB_connectie.php');
}

// Query orders for today
$sql = "SELECT b.*,
COALESCE(k.voornaam, '') as klant_voornaam,
COALESCE(k.achternaam, '') as klant_achternaam,
COALESCE(k.email, '') as klant_email
FROM bestellingen b
LEFT JOIN klanten k ON b.klant_id = k.klant_id
WHERE DATE(FROM_UNIXTIME(b.UNIX_bezorgmoment)) = CURDATE()
ORDER BY b.UNIX_bezorgmoment ASC";
$result = $mysqli->query($sql);

// Initialize or update order sequence in session
if (!isset($_SESSION['order_sequence'])) {
    // Create initial sequence based on current order
    $_SESSION['order_sequence'] = [];
    if ($result && $result->num_rows > 0) {
        $result->data_seek(0); // Reset result pointer
        while ($order = $result->fetch_assoc()) {
            $_SESSION['order_sequence'][] = $order['bestelling_id'];
        }
    }
}

// Function to update order sequence in session
function updateOrderSequence($newSequence)
{
    $_SESSION['order_sequence'] = $newSequence;
}

// Function to get orders sorted by session sequence
function getSortedOrders($result, $mysqli)
{
    $orders = [];
    $sequenceMap = [];

    // Create map of order_id to position from session
    if (isset($_SESSION['order_sequence'])) {
        foreach ($_SESSION['order_sequence'] as $index => $orderId) {
            $sequenceMap[$orderId] = $index;
        }
    }

    // Collect all orders
    if ($result) {
        $result->data_seek(0); // Reset result pointer
        while ($order = $result->fetch_assoc()) {
            $orders[] = $order;
        }
    }

    // Sort orders by session sequence, fallback to time
    usort($orders, function ($a, $b) use ($sequenceMap) {
        $aId = $a['bestelling_id'];
        $bId = $b['bestelling_id'];

        $aPos = isset($sequenceMap[$aId]) ? $sequenceMap[$aId] : PHP_INT_MAX;
        $bPos = isset($sequenceMap[$bId]) ? $sequenceMap[$bId] : PHP_INT_MAX;

        if ($aPos !== $bPos) {
            return $aPos - $bPos;
        }

        // Fallback to time-based sorting
        return $a['UNIX_bezorgmoment'] - $b['UNIX_bezorgmoment'];
    });

    return $orders;
}

// Get sorted orders
$sortedOrders = getSortedOrders($result, $mysqli);

// Function to get slot start time for an order
function getSlotStartTime($order, $mysqli)
{
    // First try to get slot from bezorg_slot_id if it exists
    $slotId = isset($order['bezorg_slot_id']) ? (int)$order['bezorg_slot_id'] : 0;
    if ($slotId > 0) {
        $stmt = $mysqli->prepare("SELECT start_time FROM timeslot_fixed_ranges WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $slotId);
            $stmt->execute();
            $res = $stmt->get_result();
            $ts = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($ts && !empty($ts['start_time'])) {
                return $ts['start_time'];
            }
        }
    }

    // Fallback: infer from UNIX_bezorgmoment
    $slotUnix = isset($order['UNIX_bezorgmoment']) ? (int)$order['UNIX_bezorgmoment'] : 0;
    if ($slotUnix > 0) {
        $dayOfWeek = (int)date('N', $slotUnix); // 1 (Mon) - 7 (Sun)
        $timeStr = date('H:i:s', $slotUnix);
        $stmt = $mysqli->prepare("SELECT start_time FROM timeslot_fixed_ranges WHERE day_of_week = ? AND start_time <= ? AND end_time > ? ORDER BY start_time LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('iss', $dayOfWeek, $timeStr, $timeStr);
            $stmt->execute();
            $res = $stmt->get_result();
            $ts = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($ts && !empty($ts['start_time'])) {
                return $ts['start_time'];
            }
        }
    }

    return null;
}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Vandaag</title>
    <link rel="stylesheet" href="/zozo-admin/css/admin-built.css">
    <link rel="stylesheet" href="/zozo-admin/css/navbar.css">
    <link rel="stylesheet" href="/zozo-admin/css/main.css">
    <style>
        /* Hide navbar on mobile dashboard */
        .navbar-container {
            display: none !important;
        }

        /* Drag and drop styles */
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px -1px rgba(0, 0, 0, 0.15);
        }

        .order-card:active {
            cursor: grabbing;
        }

        .order-card[draggable="true"] {
            user-select: none;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">

    <main style="min-height: 100vh; background-color: #f9fafb; padding: 2rem 0;">
        <div style="max-width: 28rem; margin: 0 auto; padding: 0 1rem;">
            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <div>
                        <img src="/mail/header.webp" alt="Logo" style="max-height: 3rem; width: auto; display: block;">
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button id="filter-afhaling" style="display: flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 0.375rem; border: none; cursor: pointer; background-color: #D2B48C; color: #ffffff;" title="Toon alleen afhalingen">
                            <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </button>
                        <button id="filter-levering" style="display: flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 0.375rem; border: none; cursor: pointer; background-color: #8B4513; color: #ffffff;" title="Toon alleen leveringen">
                            <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <?php if (empty($sortedOrders)): ?>
                <div style="background: white; border-radius: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); padding: 2rem; text-align: center; color: #6b7280;">
                    <p>Geen bestellingen voor vandaag.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($sortedOrders as $order): ?>
                        <?php
                        $verzendmethode = strtolower($order['verzendmethode'] ?? '');
                        $delivery_type = ($verzendmethode === 'afhalen' || $verzendmethode === 'pickup') ? 'afhaling' : 'levering';
                        ?>
                        <div class="order-card" data-order-id="<?php echo $order['bestelling_id']; ?>" data-delivery-type="<?php echo $delivery_type; ?>" draggable="true" style="background: white; border-radius: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); padding: 1rem; cursor: grab; transition: transform 0.2s, box-shadow 0.2s;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                <div>
                                    <div style="font-size: 1.125rem; font-weight: 600; color: #111827; display: flex; align-items: center;">
                                        <svg style="width: 1.25rem; height: 1.25rem; margin-right: 0.5rem; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polyline points="12,6 12,12 16,14"></polyline>
                                        </svg>
                                        <?php
                                        // Show slot start time if available, otherwise fall back to delivery moment
                                        $slot_start = getSlotStartTime($order, $mysqli);
                                        $display_time = '';
                                        if ($slot_start) {
                                            $start_ts = strtotime($slot_start);
                                            if ($start_ts !== false) {
                                                $display_time = date('H:i', $start_ts);
                                            }
                                        }
                                        if (empty($display_time)) {
                                            $bezorg = $order['UNIX_bezorgmoment'];
                                            $display_time = $bezorg ? date('H:i', $bezorg) : '-';
                                        }
                                        echo htmlspecialchars($display_time);
                                        ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: #6b7280;">
                                        #<?= htmlspecialchars($order['factuurnummer'] ?: $order['bestelling_id']) ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: #6b7280;">
                                        <?= htmlspecialchars($order['levernaam'] ?: ($order['klant_voornaam'] . ' ' . $order['klant_achternaam'])) ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: #9ca3af;">
                                        <?= htmlspecialchars($order['leverplaats']) ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 1.125rem; font-weight: bold; color: #111827;">
                                        €<?= number_format($order['bestelling_tebetalen'], 2, ',', '.') ?>
                                    </div>
                                    <div style="margin-top: 0.25rem; display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;">
                                        <?php
                                        // Payment status
                                        $reeds_betaald = $order['reeds_betaald'] ?? null;
                                        $voldaan = $order['VOLDAAN'] ?? null;
                                        $is_paid = false;
                                        if ($reeds_betaald && strpos($reeds_betaald, 'ja') === 0) {
                                            $is_paid = true;
                                        } elseif (!$reeds_betaald && $voldaan === 'ja') {
                                            $is_paid = true;
                                        }
                                        if ($is_paid) {
                                            echo '<div title="Betaald">';
                                            echo '<svg style="width: 1.25rem; height: 1.25rem; color: #10b981;" fill="currentColor" viewBox="0 0 20 20">';
                                            echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>';
                                            echo '</svg>';
                                            echo '</div>';
                                        } else {
                                            echo '<div title="Niet betaald">';
                                            echo '<svg style="width: 1.25rem; height: 1.25rem; color: #ef4444;" fill="currentColor" viewBox="0 0 20 20">';
                                            echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>';
                                            echo '</svg>';
                                            echo '</div>';
                                        }

                                        // Delivery status
                                        $status = $order['STATUS_BESTELLING'] ?? '';
                                        $is_delivered = ($status === 'delivered');
                                        if ($is_delivered) {
                                            echo '<div title="Afgehaald/Geleverd">';
                                            echo '<svg style="width: 1.25rem; height: 1.25rem; color: #3b82f6;" fill="currentColor" viewBox="0 0 20 20">';
                                            echo '<path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"></path>';
                                            echo '<path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1V8a1 1 0 00-1-1h-3z"></path>';
                                            echo '</svg>';
                                            echo '</div>';
                                        } else {
                                            echo '<div title="Niet afgehaald/geleverd">';
                                            echo '<svg style="width: 1.25rem; height: 1.25rem; color: #ef4444;" fill="currentColor" viewBox="0 0 20 20">';
                                            echo '<path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"></path>';
                                            echo '<path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1V8a1 1 0 00-1-1h-3z"></path>';
                                            echo '</svg>';
                                            echo '</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.5rem;">
                                <div style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.75rem; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em; <?php
                                                                                                                                                                                        $verzendmethode = strtolower($order['verzendmethode'] ?? '');
                                                                                                                                                                                        if ($verzendmethode === 'afhalen' || $verzendmethode === 'pickup') {
                                                                                                                                                                                            echo 'background-color: #D2B48C; color: #ffffff;';
                                                                                                                                                                                        } else {
                                                                                                                                                                                            echo 'background-color: #8B4513; color: #ffffff;';
                                                                                                                                                                                        }
                                                                                                                                                                                        ?>">
                                    <?php
                                    if ($verzendmethode === 'afhalen' || $verzendmethode === 'pickup') {
                                        echo 'Afhaling';
                                    } else {
                                        echo 'Levering';
                                    }
                                    ?>
                                </div>
                                <button onclick="viewOrder(<?= $order['bestelling_id'] ?>)" style="background-color: #3b82f6; color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; border: none; font-size: 0.875rem; font-weight: 500; cursor: pointer;">
                                    Bekijken
                                </button>
                                <div style="width: 1rem;"></div> <!-- Spacer for balance -->
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Floating button to bestellingen page (mobile navigation) -->
    <a href="/zozo-admin/bestellingen.php" style="position: fixed; bottom: 1rem; left: 1rem; width: 3rem; height: 3rem; background-color: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); z-index: 100; text-decoration: none;" title="Naar bestellingen overzicht">
        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
        </svg>
    </a>

    <!-- Order details modal -->
    <div id="order-detail-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 9999;">
        <div style="display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem;">
            <div style="background: white; border-radius: 0.5rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); width: 100%; max-width: 48rem; max-height: 100vh; overflow-y: auto;">
                <div style="padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h2 style="font-size: 1.5rem; font-weight: bold; color: #1f2937;">Bestelling details <span id="order-detail-order-id" style="font-size: 0.875rem; color: #6b7280; margin-left: 0.5rem; font-weight: normal;"></span></h2>
                        <button id="order-detail-close" style="color: #9ca3af;">
                            <svg style="width: 1.5rem; height: 1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="order-detail-content">
                        <!-- content populated via AJAX -->
                        <div style="color: #6b7280;">Laden…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewOrder(orderId) {
            // Open modal and load details via AJAX
            var modal = document.getElementById('order-detail-modal');
            var content = document.getElementById('order-detail-content');
            modal.style.display = 'block';
            content.innerHTML = '<div style="color: #6b7280;">Laden…</div>';

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
                        content.innerHTML = '<div style="color: #dc2626;">Niet geautoriseerd. Log in als admin.</div>';
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
                        content.innerHTML = '<div style="color: #dc2626;">Kon bestelling niet laden (ongeldig antwoord van server).</div>';
                        throw e;
                    }

                    if (!data || !data.ok) {
                        var errMsg = data && data.error ? escapeHtml(data.error) : 'Kon bestelling niet laden.';
                        content.innerHTML = '<div style="color: #dc2626;">' + errMsg + '</div>';
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
                    html += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">';
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
                        var phoneHtml = '<div style="display: flex; align-items: center;">' +
                            '<svg style="width: 1rem; height: 1rem; margin-right: 0.5rem; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h1.6a1 1 0 01.98.79l.35 1.8a1 1 0 01-.27.88L6.4 8.6a15.05 15.05 0 006.99 6.99l1.28-1.02a1 1 0 01.88-.27l1.8.35a1 1 0 01.79.98V19a2 2 0 01-2 2h-0C7.82 21 3 16.18 3 10V5z"></path>' +
                            '</svg>' +
                            '<span>' + escapeHtml(phone || '-') + '</span>' +
                            '</div>';
                        html += phoneHtml;
                    } catch (e) {
                        html += '<div style="display: flex; align-items: center;"><svg style="width: 1rem; height: 1rem; margin-right: 0.5rem; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h1.6a1 1 0 01.98.79l.35 1.8a1 1 0 01-.27.88L6.4 8.6a15.05 15.05 0 006.99 6.99l1.28-1.02a1 1 0 01.88-.27l1.8.35a1 1 0 01.79.98V19a2 2 0 01-2 2h-0C7.82 21 3 16.18 3 10V5z"></path></svg>-</div>';
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
                        var emailHtml = '<div style="display: flex; align-items: center;">' +
                            '<svg style="width: 1rem; height: 1rem; margin-right: 0.5rem; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8m0 8V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2h14a2 2 0 002-2z"></path>' +
                            '</svg>' +
                            '<span>' + escapeHtml(email || '-') + '</span>' +
                            '</div>';
                        html += emailHtml;
                    } catch (e) {
                        html += '<div style="display: flex; align-items: center;"><svg style="width: 1rem; height: 1rem; margin-right: 0.5rem; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8m0 8V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2h14a2 2 0 002-2z"></path></svg>-</div>';
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
                    html += '<div><span style="display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.875rem; background-color: #f3f4f6; color: #374151;">' + escapeHtml(dateLabel) + '</span></div>';
                    html += '<div><span style="display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.875rem; background-color: #f3f4f6; color: #374151;">' + formatCurrency(o.bestelling_tebetalen ? parseFloat(o.bestelling_tebetalen) : 0) + '</span></div>';
                    html += '</div>';

                    // If items are provided (array), render them in a clean list, else show message
                    if (Array.isArray(data.items) && data.items.length > 0) {
                        html += '<div style="margin-bottom: 1rem;"><h3 style="font-weight: 600; margin-bottom: 0.75rem;">Artikelen</h3>';
                        html += '<div style="display: flex; flex-direction: column; gap: 1rem;">';
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

                            html += '<div style="display: flex; align-items: flex-start; gap: 1rem; padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 0.25rem;">';
                            if (image) {
                                // ensure leading slash
                                var imgSrc = (image.indexOf('/') === -1) ? '/upload/' + image : image;
                                html += '<div style="width: 4rem; height: 4rem; flex-shrink: 0;">';
                                html += '<img src="' + escapeHtml(imgSrc) + '" alt="' + escapeHtml(name) + '" style="width: 4rem; height: 4rem; object-fit: cover; border-radius: 0.25rem;">';
                                html += '</div>';
                            } else {
                                // placeholder
                                html += '<div style="width: 4rem; height: 4rem; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background-color: #f3f4f6; border-radius: 0.25rem; color: #6b7280;">';
                                html += '<svg style="width: 1.5rem; height: 1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7M16 3v4M8 3v4m-4 4h16"/></svg>';
                                html += '</div>';
                            }
                            html += '<div style="flex: 1;">';
                            html += '<div style="font-weight: 500;">' + nl2brSafe(name) + '</div>';
                            if (optsText) html += '<div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">' + escapeHtml(optsText) + '</div>';
                            // compact row: price incl., aantal, subtotal (incl.)
                            html += '<div style="font-size: 0.875rem; color: #374151; margin-top: 0.5rem; display: flex; align-items: center;">';
                            html += '<div style="width: 4.5rem;"><strong>' + escapeHtml(formatPriceShort(unitPriceIncl)) + '</strong></div>';
                            html += '<div style="margin-left: 1rem;">Aantal: <strong>' + escapeHtml(qty) + '</strong></div>';
                            html += '<div style="margin-left: auto; font-weight: 500;"><strong>' + escapeHtml(formatPriceShort(subtotal)) + '</strong></div>';
                            html += '</div></div></div>';
                        });
                        html += '</div></div>';
                    } else {
                        html += '<div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem;">Geen artikeldetails beschikbaar.</div>';
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
                    html += '<div style="display: flex; align-items: center; justify-content: space-between;">';
                    html += '<div><strong>Status:</strong> ' + escapeHtml(statusText) + '</div>';
                    html += '<div><strong>Betaald:</strong> ' + ((o.VOLDAAN === 'ja') ? 'Ja' : 'Nee') + '</div>';
                    html += '</div>';

                    content.innerHTML = html;
                })
                .catch(function(err) {
                    if (err && err.message === 'Unauthorized') return; // already handled
                    console.error(err);
                    content.innerHTML = '<div style="color: #dc2626;">Fout bij laden bestelling.</div>';
                });
        }

        // Close order detail modal
        var orderDetailCloseBtn = document.getElementById('order-detail-close');
        if (orderDetailCloseBtn) {
            orderDetailCloseBtn.addEventListener('click', function() {
                var odm = document.getElementById('order-detail-modal');
                if (odm) odm.style.display = 'none';
            });
        }

        // Click outside modal to close
        var orderDetailModalEl = document.getElementById('order-detail-modal');
        if (orderDetailModalEl) {
            orderDetailModalEl.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        }

        // Filter functionality
        var currentFilter = 'all'; // 'all', 'afhaling', 'levering'

        function filterOrders(filterType) {
            currentFilter = filterType;
            var orders = document.querySelectorAll('.order-card');

            orders.forEach(function(order) {
                var deliveryType = order.getAttribute('data-delivery-type');
                if (filterType === 'all' || deliveryType === filterType) {
                    order.style.display = 'block';
                } else {
                    order.style.display = 'none';
                }
            });

            // Update button styles
            updateFilterButtons();
        }

        function updateFilterButtons() {
            var afhalingBtn = document.getElementById('filter-afhaling');
            var leveringBtn = document.getElementById('filter-levering');

            // Reset all buttons to normal state
            afhalingBtn.style.opacity = '1';
            afhalingBtn.style.transform = 'scale(1)';
            leveringBtn.style.opacity = '1';
            leveringBtn.style.transform = 'scale(1)';

            // Highlight active filter
            if (currentFilter === 'afhaling') {
                afhalingBtn.style.opacity = '0.8';
                afhalingBtn.style.transform = 'scale(0.95)';
            } else if (currentFilter === 'levering') {
                leveringBtn.style.opacity = '0.8';
                leveringBtn.style.transform = 'scale(0.95)';
            }
        }

        // Add event listeners to filter buttons
        document.getElementById('filter-afhaling').addEventListener('click', function() {
            filterOrders(currentFilter === 'afhaling' ? 'all' : 'afhaling');
        });

        document.getElementById('filter-levering').addEventListener('click', function() {
            filterOrders(currentFilter === 'levering' ? 'all' : 'levering');
        });

        // Initialize filter buttons
        updateFilterButtons();

        // Simple drag and drop for session-based reordering
        let draggedOrderId = null;

        // Add notification about session-based ordering
        function showOrderNotification() {
            // Remove any existing notification first
            const existing = document.querySelector('.order-notification');
            if (existing) {
                existing.remove();
            }

            const notification = document.createElement('div');
            notification.className = 'order-notification';
            notification.style.position = 'fixed';
            notification.style.top = '1rem';
            notification.style.left = '50%';
            notification.style.transform = 'translateX(-50%)';
            notification.style.backgroundColor = '#10b981';
            notification.style.color = 'white';
            notification.style.padding = '0.75rem 1rem';
            notification.style.borderRadius = '0.5rem';
            notification.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
            notification.style.zIndex = '1000';
            notification.style.fontSize = '0.875rem';
            notification.style.fontWeight = '500';
            notification.innerHTML = '📋 Volgorde aangepast voor deze sessie - Herlaad pagina om te resetten';

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

        // Placeholder function removed - using simple DOM reordering instead

        // Add drag event listeners to order cards
        document.querySelectorAll('.order-card').forEach(card => {
            card.addEventListener('dragstart', function(e) {
                draggedOrderId = this.getAttribute('data-order-id');
                if (this && this.style) {
                    this.style.opacity = '0.5';
                }
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', draggedOrderId);
            });

            card.addEventListener('dragend', function(e) {
                if (this && this.style) {
                    this.style.opacity = '1';
                }

                // Get new order sequence from DOM
                const orderCards = document.querySelectorAll('.order-card');
                const newSequence = Array.from(orderCards).map(card => card.getAttribute('data-order-id'));

                // Update session via AJAX
                fetch('update_order_sequence.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            sequence: newSequence
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            setTimeout(logOrderSequence, 100);
                        }
                    })
                    .catch(error => {
                        console.error('Error updating order sequence:', error);
                    });

                draggedOrderId = null;
            });

            card.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                if (this.getAttribute('data-order-id') !== draggedOrderId) {
                    const rect = this.getBoundingClientRect();
                    const midpoint = rect.top + rect.height / 2;

                    // Simple reordering: move dragged element before or after this element
                    const draggedElement = document.querySelector(`[data-order-id="${draggedOrderId}"]`);
                    if (draggedElement && draggedElement !== this) {
                        if (e.clientY < midpoint) {
                            this.parentNode.insertBefore(draggedElement, this);
                        } else {
                            this.parentNode.insertBefore(draggedElement, this.nextSibling);
                        }
                    }
                }
            });

        });

        // Global dragover to allow dropping
        document.addEventListener('dragover', function(e) {
            e.preventDefault();
        });

        // Function to log current order sequence to console
        function logOrderSequence() {
            const orderCards = document.querySelectorAll('.order-card');
            const orderSequence = [];

            orderCards.forEach((card, index) => {
                // Try to get order ID from the card content
                const orderIdElement = card.querySelector('div:nth-child(2) div:nth-child(2)');
                const orderId = orderIdElement ? orderIdElement.textContent.trim().replace('#', '') : `Order ${index + 1}`;

                // Get delivery type
                const deliveryType = card.getAttribute('data-delivery-type') || 'unknown';

                // Get time
                const timeElement = card.querySelector('div:first-child div:first-child div:first-child');
                const time = timeElement ? timeElement.textContent.trim() : 'Unknown time';

                orderSequence.push({
                    position: index + 1,
                    orderId: orderId,
                    deliveryType: deliveryType,
                    time: time
                });
            });

            console.log('📋 Huidige volgorde van bestellingen:', orderSequence);
            console.table(orderSequence);
        }

        // Log initial order on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(logOrderSequence, 100); // Small delay to ensure DOM is fully loaded
        });

        // Make function globally available for manual console testing
        window.logOrderSequence = logOrderSequence;
    </script>
</body>

</html>