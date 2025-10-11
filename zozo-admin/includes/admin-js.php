<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/config.php");

$stmt = $mysqli->prepare("SELECT magic_link FROM instellingen LIMIT 1");
$stmt->execute();
$instellingen = $stmt->get_result()->fetch_assoc();
$magic_link = $instellingen['magic_link'] ?? '9523aP/MyRi@m/8_xr';
?>
<script>
    // Admin login via localStorage
    const TOKEN_KEY = '<?php echo ADMIN_TOKEN_KEY; ?>';
    const EXPIRY_KEY = '<?php echo ADMIN_EXPIRY_KEY; ?>';
    const MAGIC_TOKEN = '<?php echo ADMIN_MAGIC_TOKEN; ?>';
    const wasLoggedIn = <?php echo isset($_SESSION['admin_logged_in']) ? 'true' : 'false'; ?>;

    // Function to login via token
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
                    console.log('Logged in via token');
                    if (!wasLoggedIn) {
                        window.location.reload();
                    }
                } else {
                    console.error('Login failed:', data.error);
                }
            })
            .catch(error => console.error('Error:', error));
    }

    // Check localStorage on page load
    const storedToken = localStorage.getItem(TOKEN_KEY);
    const storedExpiry = localStorage.getItem(EXPIRY_KEY);
    const now = Date.now();

    if (storedToken === MAGIC_TOKEN && storedExpiry && now < parseInt(storedExpiry)) {
        // Token is valid, login if not already logged in
        if (!wasLoggedIn) {
            loginViaToken();
        }
    } else if (storedToken) {
        // Token expired, remove it
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(EXPIRY_KEY);
    }

    // Check for magic link parameters
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('magic') === '<?php echo addslashes($magic_link); ?>') {
        // Set localStorage with 30 days expiry
        const expiry = now + (30 * 24 * 60 * 60 * 1000);
        localStorage.setItem(TOKEN_KEY, MAGIC_TOKEN);
        localStorage.setItem(EXPIRY_KEY, expiry.toString());
        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
</script>