<?php
function activelanguage()
{
    $uri = $_SERVER['REQUEST_URI'];
    if (preg_match('#^/(nl|fr|en)(/|$)#', $uri, $matches)) {
        return $matches[1];
    }

    // Try to detect branded welcome slugs from the instellingen table
    // Fallback to old hardcoded values if DB not available
    $fallbacks = ['/welkom' => 'nl', '/bienvenue' => 'fr', '/welcome' => 'en'];
    try {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/DB_connectie.php')) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/zozo-includes/DB_connectie.php';
            $res = $mysqli->query("SELECT url_welkom, url_welkom_fr, url_welkom_en FROM instellingen LIMIT 1");
            if ($res) {
                $row = $res->fetch_assoc();
                $u_nl = isset($row['url_welkom']) ? '/' . ltrim($row['url_welkom'], '/') : '/welkom';
                $u_fr = isset($row['url_welkom_fr']) ? '/' . ltrim($row['url_welkom_fr'], '/') : '/bienvenue';
                $u_en = isset($row['url_welkom_en']) ? '/' . ltrim($row['url_welkom_en'], '/') : '/welcome';
                $fallbacks = [$u_nl => 'nl', $u_fr => 'fr', $u_en => 'en'];
            }
        }
    } catch (Throwable $e) {
        // ignore DB errors and fallback to defaults
    }

    foreach ($fallbacks as $path => $code) {
        if (strpos($uri, $path) === 0) return $code;
    }

    return 'nl';
}

function t($key)
{
    global $translations, $activeLang;
    return $translations[$key][$activeLang] ?? $translations[$key]['nl'] ?? $key;
}

function cat_translate($mysqli, $cat_id, $lang)
{
    if (!$cat_id) return '';
    $slug = null;
    $slugField = 'cat_afkorting';
    if ($lang === 'fr') $slugField = 'cat_afkorting_fr';
    if ($lang === 'en') $slugField = 'cat_afkorting_en';
    $stmt = $mysqli->prepare("SELECT $slugField FROM category WHERE cat_id = ?");
    $stmt->bind_result($slug);
    $stmt->bind_param("i", $cat_id);
    $stmt->execute();
    $stmt->bind_result($slug);
    $stmt->fetch();
    $stmt->close();
    return $slug ?: '';
}
