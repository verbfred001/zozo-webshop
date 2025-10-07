<!-- Breadcrumb -->

<?php
// Detecteer taal als $lang niet gezet is of als we op een van de welkompagina's staan
$uri = $_SERVER['REQUEST_URI'];
if (!isset($lang) || !$lang || in_array($uri, ['/welkom', '/bienvenue', '/welcome'])) {
    if ($uri === '/bienvenue') {
        $lang = 'fr';
    } elseif ($uri === '/welcome') {
        $lang = 'en';
    } else {
        $lang = 'nl';
    }
}

// Nu is $all_cats beschikbaar!
$slug_to_name = [];
foreach ($all_cats as $cat) {
    $slug_to_name[cat_slug($cat, $lang)] = cat_name($cat, $lang);
}

// Vertaling en URL voor de homepage
$home_labels = [
    'nl' => 'Welkom',
    'fr' => 'Bienvenue',
    'en' => 'Welcome'
];
$home_urls = [
    'nl' => '/welkom',
    'fr' => '/bienvenue',
    'en' => '/welcome'
];

// Breadcrumb opbouwen
$breadcrumb = [];
$breadcrumb[] = [
    'label' => $home_labels[$lang] ?? 'Welkom',
    'url' => $home_urls[$lang] ?? '/welkom'
];

$path = '';
foreach ($cat_slugs as $i => $slug) {
    $path .= ($i === 0 ? '' : '/') . $slug;
    $label = $slug_to_name[$slug] ?? ucfirst($slug);
    $breadcrumb[] = [
        'label' => $label,
        'url' => '/' . $lang . '/' . $path
    ];
}
?>
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol>
        <?php foreach ($breadcrumb as $i => $item): ?>
            <li>
                <?php if ($i < count($breadcrumb) - 1): ?>
                    <a href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
                    <span class="breadcrumb-separator">&gt;</span>
                <?php else: ?>
                    <?= htmlspecialchars($item['label']) ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>