<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Controleer of gebruiker is ingelogd, anders redirect naar login.php
//if (!isset($_SESSION['admin_logged_in'])) {
//   header('Location: login.php');
//   exit;
//}
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/zozo-admin/css/navbar.css">
    <link rel="stylesheet" href="/zozo-admin/css/main.css">
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-admin/templates/navbar.php'); ?>

    <main class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 mt-4 sm:mt-8">
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 lg:p-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-4">Welkom in het beheer</h1>
            <p class="text-gray-600 text-base sm:text-lg">Kies een onderdeel in het menu.</p>
        </div>
    </main>
</body>

</html>