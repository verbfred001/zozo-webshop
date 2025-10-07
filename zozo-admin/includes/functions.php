<?php
// Centrale functies voor hergebruik

function generateSlug($text)
{
    if (!$text) return '';

    // Stap 1: Speciale karakters vervangen
    $slug = str_replace(
        ['à', 'á', 'â', 'ã', 'ä', 'å', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'ñ', 'ç', 'ß'],
        ['a', 'a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'n', 'c', 'ss'],
        strtolower($text)
    );

    // Stap 2: Alleen letters, cijfers en spaties behouden
    $slug = preg_replace('/[^a-z0-9\s]/', '', $slug);

    // Stap 3: Spaties vervangen door hyphens
    $slug = preg_replace('/\s+/', '-', $slug);

    // Stap 4: Meerdere hyphens vervangen door één
    $slug = preg_replace('/\-+/', '-', $slug);

    // Stap 5: Leading/trailing hyphens verwijderen
    $slug = trim($slug, '-');

    return $slug;
}
