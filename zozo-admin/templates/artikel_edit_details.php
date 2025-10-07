<!-- Artikel details grid -->


<!-- Omschrijving -->
<div class="details-card" id="anchor-omschrijving">
    <div class="details-header">
        <h3 class="details-title">Omschrijving</h3>
        <button onclick="openModal('omschrijving-modal')" class="btn btn--blue btn--sm">Bewerken</button>
    </div>
    <div class="details-content">
        <p class="details-value" id="display_art_omschrijving"><?= nl2br(htmlspecialchars($artikel['art_omschrijving'] ?? '')) ?></p>
        <?php if (in_array('fr', $talen)): ?>
            <p class="details-value details-value--sub" id="display_art_omschrijving_fr"><?= nl2br(htmlspecialchars($artikel['art_omschrijving_fr'] ?? '')) ?></p>
        <?php endif; ?>
        <?php if (in_array('en', $talen)): ?>
            <p class="details-value details-value--sub" id="display_art_omschrijving_en"><?= nl2br(htmlspecialchars($artikel['art_omschrijving_en'] ?? '')) ?></p>
        <?php endif; ?>
    </div>
</div>


<!-- Prijzen -->
<div class="details-card" id="anchor-prijzen">
    <div class="details-header">
        <h3 class="details-title">Prijzen & BTW</h3>
        <button onclick="openModal('prijzen-modal')" class="btn btn--blue btn--sm">
            Bewerken
        </button>
    </div>
    <div class="details-content">
        <p class="details-value" id="display_art_kostprijs">
            <?php
            $btw_rate = isset($artikel['art_BTWtarief']) ? $artikel['art_BTWtarief'] : 21;
            $kost_excl = isset($artikel['art_kostprijs']) ? floatval($artikel['art_kostprijs']) : 0.0;
            $kost_incl = $kost_excl * (1 + $btw_rate / 100);
            ?>
            Kostprijs: <?= number_format($kost_incl, 2, ',', '.') ?>
            <small style="font-weight:normal; color:#666;">
                (<?= number_format($kost_excl, 2, ',', '.') ?> excl.btw)
            </small>
        </p>
        <p class="details-value details-value--sub" id="display_art_oudeprijs">
            <?php
            $oude_excl = isset($artikel['art_oudeprijs']) ? floatval($artikel['art_oudeprijs']) : 0.0;
            $oude_incl = $oude_excl * (1 + $btw_rate / 100);
            ?>
            Oude prijs: <?= number_format($oude_incl, 2, ',', '.') ?>
            <small style="font-weight:normal; color:#666;">
                (<?= number_format($oude_excl, 2, ',', '.') ?> excl.btw)
            </small>
        </p>
        <p class="details-value details-value--sub" id="display_art_BTWtarief">BTW: <?= $artikel['art_BTWtarief'] ?>%</p>
    </div>
</div>

<!-- Voorraad -->
<?php
// Toon voorraad & levertijd alleen als voorraadbeheer actief is
if (!isset($voorraadbeheer)) {
    $inst = $mysqli->query("SELECT voorraadbeheer FROM instellingen LIMIT 1")->fetch_assoc() ?: [];
    $voorraadbeheer = !empty($inst['voorraadbeheer']) && $inst['voorraadbeheer'] != 0;
}
if ($voorraadbeheer): ?>
    <div class="details-card" id="anchor-voorraad">
        <div class="details-header">
            <h3 class="details-title">Voorraad & Levertijd</h3>
            <button onclick="openModal('voorraad-modal')" class="btn btn--blue btn--sm">
                Bewerken
            </button>
        </div>
        <div class="details-content">
            <?php
            if (!function_exists('tel_voorraad_varianten')) {
                @include_once($_SERVER['DOCUMENT_ROOT'] . '/zozo-admin/includes/function_tel_voorraad_varianten.php');
            }
            $voorraad_total = isset($artikel['art_id']) ? tel_voorraad_varianten($mysqli, $artikel['art_id']) : ($artikel['art_aantal'] ?? 0);
            ?>
            <p class="details-value" id="display_art_aantal">Voorraad: <?= $voorraad_total ?> stuks</p>
            <p class="details-value details-value--sub" id="display_art_levertijd">Levertijd: <?= htmlspecialchars($artikel['art_levertijd'] ?? 0) ?> dagen</p>
        </div>
    </div>
<?php endif; ?>

<!-- Status -->
<div class="details-card" id="anchor-status">
    <div class="details-header">
        <h3 class="details-title">Status & Weergave</h3>
        <button onclick="openModal('status-modal')" class="btn btn--blue btn--sm">
            Bewerken
        </button>
    </div>
    <div class="details-content">
        <?php $visible = isset($artikel['art_weergeven']) && ($artikel['art_weergeven'] === 1 || $artikel['art_weergeven'] === '1' || strtolower((string)$artikel['art_weergeven']) === 'ja'); ?>
        <p class="details-value" id="display_art_weergeven">
            Zichtbaarheid: <span class="<?= $visible ? 'text-green' : 'text-red' ?>"><?= $visible ? 'Zichtbaar' : 'Verborgen' ?></span>
        </p>
        <?php
        $displayInde = 'Nee';
        if (isset($artikel['art_indekijker'])) {
            $v = $artikel['art_indekijker'];
            if ($v === 1 || $v === '1' || strtolower((string)$v) === 'ja') $displayInde = 'Ja';
            elseif ($v === null || $v === '') $displayInde = 'Nee';
        }
        ?>
        <p class="details-value details-value--sub" id="display_art_indekijker">In de kijker: <?= $displayInde ?></p>
        <?php
        $displaySugg = 'Nee';
        if (isset($artikel['art_suggestie'])) {
            $v2 = $artikel['art_suggestie'];
            if ($v2 === 1 || $v2 === '1' || strtolower((string)$v2) === 'ja') $displaySugg = 'Ja';
            elseif ($v2 === null || $v2 === '') $displaySugg = 'Nee';
        }
        ?>
        <p class="details-value details-value--sub" id="display_art_suggestie">Suggestie: <?= $displaySugg ?></p>
    </div>
</div>

<!-- Categorie - MET HIËRARCHIE -->
<div class="details-card" id="anchor-categorie">
    <div class="details-header">
        <h3 class="details-title">Categorie</h3>
        <button onclick="openModal('categorie-modal')" class="btn btn--blue btn--sm">
            Bewerken
        </button>
    </div>
    <div class="details-content">
        <?php
        function getCategoryHierarchyDisplay($categories, $categoryId)
        {
            if ($categoryId == 0) {
                return 'Geen categorie';
            }
            $path = [];
            $currentId = $categoryId;
            $visitedIds = [];
            while ($currentId > 0) {
                if (in_array($currentId, $visitedIds)) {
                    $path[] = "LOOP DETECTED";
                    break;
                }
                $visitedIds[] = $currentId;
                $found = false;
                foreach ($categories as $cat) {
                    if ($cat['cat_id'] == $currentId) {
                        array_unshift($path, $cat['cat_naam']);
                        $currentId = intval($cat['cat_top_sub']);
                        $found = true;
                        break;
                    }
                }
                if (!$found) break;
                if (count($path) > 5) {
                    $path[] = "...";
                    break;
                }
            }
            if (empty($path)) {
                return 'Onbekende categorie';
            }
            return implode(' > ', $path);
        }
        $categoryHierarchy = getCategoryHierarchyDisplay($categories, $artikel['art_catID']);
        ?>
        <p class="details-value" id="display_art_categorie">
            <?= htmlspecialchars($categoryHierarchy) ?>
        </p>
        <?php if ($artikel['art_catID'] > 0): ?>
            <p class="details-value details-value--sub details-value--small">
                Categorie ID: <?= $artikel['art_catID'] ?>
            </p>
        <?php endif; ?>
    </div>
</div>