<?php
session_start();

// Unset admin session
unset($_SESSION['admin_logged_in']);

// Output page to clear localStorage and redirect
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/config.php");
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <title>Logging out...</title>
</head>

<body>
    <p>Logging out...</p>
    <script>
        // Clear localStorage
        localStorage.removeItem('<?php echo ADMIN_TOKEN_KEY; ?>');
        localStorage.removeItem('<?php echo ADMIN_EXPIRY_KEY; ?>');

        // Redirect to home
        window.location.href = '/';
    </script>
</body>

</html>