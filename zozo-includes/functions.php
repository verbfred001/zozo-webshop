<?php
function activelanguage()
{
    $uri = $_SERVER['REQUEST_URI'];
    if (preg_match('#^/(nl|fr|en)(/|$)#', $uri, $matches)) {
        return $matches[1];
    }
    if (strpos($uri, '/bienvenue') === 0) return 'fr';
    if (strpos($uri, '/welcome') === 0) return 'en';
    if (strpos($uri, '/welkom') === 0) return 'nl';
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
