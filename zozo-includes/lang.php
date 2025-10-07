<?php
// Centralized language variables. Pages can include this to ensure $langs and $lang are defined.
if (!isset($langs) || !is_array($langs)) {
    $langs = ['nl' => 'Nederlands', 'fr' => 'Français', 'en' => 'English'];
}

if (!isset($lang)) {
    if (isset($_GET['l']) && in_array($_GET['l'], array_keys($langs))) {
        $lang = $_GET['l'];
    } elseif (isset($_SERVER['REQUEST_URI']) && preg_match('#^/(nl|fr|en)(/|$)#', $_SERVER['REQUEST_URI'], $m)) {
        $lang = $m[1];
    } else {
        $lang = 'nl';
    }
}

// Convenience alias for templates
$activeLang = $lang;
