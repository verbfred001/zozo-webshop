<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");

// Controleer of gebruiker is ingelogd, anders redirect naar login.php
//if (!isset($_SESSION['admin_logged_in'])) {
//   header('Location: login.php');
//   exit;
//}

// Handle event creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? '';
    $type = $_POST['type'] ?? 'general';

    // Here you would save to database
    $success_message = "Event toegevoegd aan kalender!";
}

// Get current month/year
$month = $month = $_GET['month'] ?? date('m');
$year = $year = $_GET['year'] ?? date('Y');
$month = intval($month);
$year = intval($year);

// Calculate calendar data
$first_day = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day);
$start_day = date('w', $first_day); // 0 = Sunday
$month_name = date('F Y', $first_day);

// Previous/next month calculations
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Sample events (normally from database)
$events = [
    '2025-06-29' => [
        ['title' => 'Levering nieuwe producten', 'type' => 'delivery', 'time' => '09:00'],
        ['title' => 'Team meeting', 'type' => 'meeting', 'time' => '14:00']
    ],
    '2025-06-30' => [
        ['title' => 'Inventaris controle', 'type' => 'inventory', 'time' => '10:00']
    ],
    '2025-07-01' => [
        ['title' => 'Klant bezoek', 'type' => 'customer', 'time' => '11:30']
    ]
];

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Kalender</title>
    <link rel="stylesheet" href="/zozo-admin/css/admin-built.css">
    <link rel="stylesheet" href="/zozo-admin/css/navbar.css">
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

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Calendar Section -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-lg shadow-md">
                    <!-- Calendar Header -->
                    <div class="border-b border-gray-200 p-6">
                        <div class="flex justify-between items-center">
                            <h1 class="text-3xl font-bold text-gray-800">
                                <?php
                                $formatter = new IntlDateFormatter(
                                    'nl_NL',
                                    IntlDateFormatter::NONE,
                                    IntlDateFormatter::NONE,
                                    null,
                                    null,
                                    'MMMM y'
                                );
                                echo $formatter->format($first_day);
                                ?>
                            </h1>
                            <div class="flex items-center space-x-4">
                                <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>"
                                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 p-2 rounded-full transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                    </svg>
                                </a>
                                <button onclick="goToToday()"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors">
                                    Vandaag
                                </button>
                                <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>"
                                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 p-2 rounded-full transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Calendar Grid -->
                    <div class="p-6">
                        <!-- Day Headers -->
                        <div class="grid grid-cols-7 gap-1 mb-2">
                            <?php
                            $days = ['Zo', 'Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za'];
                            foreach ($days as $day):
                            ?>
                                <div class="p-3 text-center text-sm font-medium text-gray-500">
                                    <?= $day ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Calendar Days -->
                        <div class="grid grid-cols-7 gap-1">
                            <?php
                            // Empty cells for days before month starts
                            for ($i = 0; $i < $start_day; $i++) {
                                echo '<div class="h-24 bg-gray-50 border border-gray-100"></div>';
                            }

                            // Days of the month
                            for ($day = 1; $day <= $days_in_month; $day++) {
                                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                $is_today = ($date === $today);
                                $day_events = $events[$date] ?? [];

                                echo '<div class="h-24 border border-gray-200 bg-white hover:bg-gray-50 cursor-pointer transition-colors" onclick="openDayModal(\'' . $date . '\')">';
                                echo '<div class="p-1">';

                                // Day number
                                echo '<div class="flex justify-between items-start mb-1">';
                                echo '<span class="text-sm font-medium ' . ($is_today ? 'bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center' : 'text-gray-900') . '">' . $day . '</span>';
                                echo '</div>';

                                // Events
                                foreach (array_slice($day_events, 0, 2) as $event) {
                                    $color_class = '';
                                    switch ($event['type']) {
                                        case 'delivery':
                                            $color_class = 'bg-green-100 text-green-800';
                                            break;
                                        case 'meeting':
                                            $color_class = 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'inventory':
                                            $color_class = 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'customer':
                                            $color_class = 'bg-purple-100 text-purple-800';
                                            break;
                                        default:
                                            $color_class = 'bg-gray-100 text-gray-800';
                                    }
                                    echo '<div class="text-xs p-1 mb-1 rounded ' . $color_class . ' truncate">';
                                    echo htmlspecialchars($event['title']);
                                    echo '</div>';
                                }

                                if (count($day_events) > 2) {
                                    echo '<div class="text-xs text-gray-500">+' . (count($day_events) - 2) . ' meer</div>';
                                }

                                echo '</div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Add Event Form -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Nieuw Event</h2>
                    <form method="post" action="kalender.php" class="space-y-4">
                        <input type="hidden" name="add_event" value="1">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Titel:</label>
                            <input type="text" name="title" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Datum:</label>
                            <input type="date" name="event_date" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tijd:</label>
                            <input type="time" name="event_time"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type:</label>
                            <select name="type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="general">Algemeen</option>
                                <option value="meeting">Vergadering</option>
                                <option value="delivery">Levering</option>
                                <option value="inventory">Inventaris</option>
                                <option value="customer">Klant</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Beschrijving:</label>
                            <textarea name="description" rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>

                        <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md transition-colors">
                            Event toevoegen
                        </button>
                    </form>
                </div>

                <!-- Upcoming Events -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Komende Events</h2>
                    <div class="space-y-3">
                        <?php
                        $upcoming = [];
                        foreach ($events as $date => $day_events) {
                            if ($date >= $today) {
                                foreach ($day_events as $event) {
                                    $upcoming[] = array_merge($event, ['date' => $date]);
                                }
                            }
                        }
                        usort($upcoming, function ($a, $b) {
                            return strcmp($a['date'], $b['date']);
                        });

                        foreach (array_slice($upcoming, 0, 5) as $event):
                            $color_class = '';
                            switch ($event['type']) {
                                case 'delivery':
                                    $color_class = 'border-green-500';
                                    break;
                                case 'meeting':
                                    $color_class = 'border-blue-500';
                                    break;
                                case 'inventory':
                                    $color_class = 'border-yellow-500';
                                    break;
                                case 'customer':
                                    $color_class = 'border-purple-500';
                                    break;
                                default:
                                    $color_class = 'border-gray-500';
                            }
                        ?>
                            <div class="border-l-4 <?= $color_class ?> pl-3 py-2">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($event['title']) ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?= date('d M', strtotime($event['date'])) ?>
                                    <?php if ($event['time']): ?>
                                        om <?= $event['time'] ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($upcoming)): ?>
                            <p class="text-gray-500 text-sm">Geen komende events</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Deze maand</h2>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Totaal events:</span>
                            <span class="font-semibold">
                                <?= array_sum(array_map('count', $events)) ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Vergaderingen:</span>
                            <span class="font-semibold text-blue-600">
                                <?php
                                $meetings = 0;
                                foreach ($events as $day_events) {
                                    foreach ($day_events as $event) {
                                        if ($event['type'] === 'meeting') $meetings++;
                                    }
                                }
                                echo $meetings;
                                ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Leveringen:</span>
                            <span class="font-semibold text-green-600">
                                <?php
                                $deliveries = 0;
                                foreach ($events as $day_events) {
                                    foreach ($day_events as $event) {
                                        if ($event['type'] === 'delivery') $deliveries++;
                                    }
                                }
                                echo $deliveries;
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Day Detail Modal -->
        <div id="day-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 id="modal-date" class="text-2xl font-bold text-gray-800"></h2>
                            <button onclick="closeDayModal()"
                                class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div id="modal-events" class="space-y-3">
                            <!-- Events will be populated by JavaScript -->
                        </div>

                        <div class="mt-6">
                            <button onclick="closeDayModal()"
                                class="w-full bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded-md transition-colors">
                                Sluiten
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const events = <?= json_encode($events) ?>;

        function openDayModal(date) {
            const modalDate = document.getElementById('modal-date');
            const modalEvents = document.getElementById('modal-events');

            // Format date for display
            const dateObj = new Date(date + 'T00:00:00');
            modalDate.textContent = dateObj.toLocaleDateString('nl-NL', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            // Show events for this date
            const dayEvents = events[date] || [];
            modalEvents.innerHTML = '';

            if (dayEvents.length === 0) {
                modalEvents.innerHTML = '<p class="text-gray-500">Geen events op deze dag</p>';
            } else {
                dayEvents.forEach(event => {
                    const eventDiv = document.createElement('div');
                    let colorClass = '';
                    switch (event.type) {
                        case 'delivery':
                            colorClass = 'border-green-500 bg-green-50';
                            break;
                        case 'meeting':
                            colorClass = 'border-blue-500 bg-blue-50';
                            break;
                        case 'inventory':
                            colorClass = 'border-yellow-500 bg-yellow-50';
                            break;
                        case 'customer':
                            colorClass = 'border-purple-500 bg-purple-50';
                            break;
                        default:
                            colorClass = 'border-gray-500 bg-gray-50';
                    }

                    eventDiv.className = `border-l-4 ${colorClass} p-3 rounded-r`;
                    eventDiv.innerHTML = `
                        <div class="font-medium">${event.title}</div>
                        ${event.time ? `<div class="text-sm text-gray-600">Tijd: ${event.time}</div>` : ''}
                    `;
                    modalEvents.appendChild(eventDiv);
                });
            }

            document.getElementById('day-modal').classList.remove('hidden');
        }

        function closeDayModal() {
            document.getElementById('day-modal').classList.add('hidden');
        }

        function goToToday() {
            const today = new Date();
            const month = today.getMonth() + 1;
            const year = today.getFullYear();
            window.location.href = `?month=${month}&year=${year}`;
        }

        // Click outside modal to close
        document.getElementById('day-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDayModal();
            }
        });
    </script>
</body>

</html>