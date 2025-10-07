<!-- Modal: Naam bewerken -->
<div id="naam-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box modal-box--sm">
            <form method="post" action="artikel_bewerk.php?id=<?= $art_id ?>">
                <input type="hidden" name="art_id" value="<?= (int)($artikel['art_id'] ?? $art_id) ?>">
                <input type="hidden" name="save_naam" value="1">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Artikel naam bewerken</h2>
                        <button type="button" onclick="closeModal('naam-modal')" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-fields">
                        <div>
                            <label class="form-label">Nederlands *</label>
                            <?php $art_naam = isset($artikel['art_naam']) ? $artikel['art_naam'] : ''; ?>
                            <input type="text" name="art_naam" value="<?= htmlspecialchars($art_naam) ?>"
                                class="form-input" required>
                        </div>
                        <?php if (in_array('fr', $talen)): ?>
                            <div>
                                <label class="form-label">
                                    Frans
                                    <button type="button" onclick="translateModalField('art_naam', 'art_naam_fr', 'fr', this)"
                                        class="modal-btn modal-btn-blue">🌍 Vertaal</button>
                                </label>
                                <input type="text" name="art_naam_fr" value="<?= htmlspecialchars($artikel['art_naam_fr'] ?? '') ?>"
                                    class="form-input">
                            </div>
                        <?php endif; ?>
                        <?php if (in_array('en', $talen)): ?>
                            <div>
                                <label class="form-label">
                                    Engels
                                    <button type="button"
                                        onclick="translateModalField('art_naam', 'art_naam_en', 'en', this)"
                                        class="modal-btn modal-btn-blue">
                                        🌍 Vertaal
                                    </button>
                                </label>
                                <input type="text" name="art_naam_en" value="<?= htmlspecialchars($artikel['art_naam_en'] ?? '') ?>"
                                    class="form-input">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" name="save_naam" class="btn btn--main flex-1">Opslaan</button>
                        <button type="button" onclick="closeModal('naam-modal')" class="btn btn--gray flex-1">Annuleren</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Kenmerk -->
<div id="kenmerk-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box modal-box--sm">

            <form method="post" action="/admin/detail?id=<?= $art_id ?>&zoek=<?= urlencode($zoek) ?>&cat=<?= urlencode($catFilter) ?>">
                <input type="hidden" name="art_id" value="<?= (int)$art_id ?>">
                <input type="hidden" name="save_kenmerk" value="1">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Kenmerk bewerken</h2>
                        <button type="button" onclick="closeModal('kenmerk-modal')" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-fields">
                        <div>
                            <label class="form-label">Kenmerk (NL)</label>
                            <input type="text" name="art_kenmerk" value="<?= htmlspecialchars($artikel['art_kenmerk'] ?? '') ?>"
                                class="form-input">
                        </div>
                        <?php if (in_array('fr', $talen)): ?>
                            <div>
                                <label class="form-label">Kenmerk (FR)</label>
                                <div class="flex-row gap-1">
                                    <input type="text" name="art_kenmerk_fr" value="<?= htmlspecialchars($artikel['art_kenmerk_fr'] ?? '') ?>"
                                        class="form-input flex-1">
                                    <button type="button" onclick="translateModalField('art_kenmerk', 'art_kenmerk_fr', 'fr', this)"
                                        class="btn btn--translate">🌍</button>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (in_array('en', $talen)): ?>
                            <div>
                                <label class="form-label">Kenmerk (EN)</label>
                                <div class="flex-row gap-1">
                                    <input type="text" name="art_kenmerk_en" value="<?= htmlspecialchars($artikel['art_kenmerk_en'] ?? '') ?>"
                                        class="form-input flex-1">
                                    <button type="button" onclick="translateModalField('art_kenmerk', 'art_kenmerk_en', 'en', this)"
                                        class="btn btn--translate">🌍</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn btn--main flex-1">Opslaan</button>
                        <button type="button" onclick="closeModal('kenmerk-modal')" class="btn btn--gray flex-1">Annuleren</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Omschrijving -->
<div id="omschrijving-modal" class="modal-overlay hidden">
    <form method="post" action="/admin/detail?id=<?= $art_id ?>&zoek=<?= urlencode($zoek) ?>&cat=<?= urlencode($catFilter) ?>#anchor-omschrijving">
        <input type="hidden" name="art_id" value="<?= (int)$art_id ?>">
        <input type="hidden" name="save_omschrijving" value="1">
        <div class="modal-center">
            <div class="modal-box modal-box--lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Omschrijving bewerken</h2>
                        <button onclick="closeModal('omschrijving-modal')" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-fields">
                        <div>
                            <label class="form-label">Omschrijving (NL)</label>
                            <textarea name="art_omschrijving" id="modal_art_omschrijving" rows="4" class="form-input"><?= htmlspecialchars($artikel['art_omschrijving'] ?? '') ?></textarea>
                        </div>
                        <?php if (in_array('fr', $talen)): ?>
                            <div>
                                <label class="form-label">Omschrijving (FR)</label>
                                <div class="flex-col gap-1">
                                    <textarea name="art_omschrijving_fr" id="modal_art_omschrijving_fr" rows="4" class="form-input"><?= htmlspecialchars($artikel['art_omschrijving_fr'] ?? '') ?></textarea>
                                    <button type="button" onclick="translateModalField('modal_art_omschrijving', 'modal_art_omschrijving_fr', 'fr', this)" class="btn btn--translate">🌍 Vertaal omschrijving</button>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (in_array('en', $talen)): ?>
                            <div>
                                <label class="form-label">Omschrijving (EN)</label>
                                <div class="flex-col gap-1">
                                    <textarea name="art_omschrijving_en" id="modal_art_omschrijving_en" rows="4" class="form-input"><?= htmlspecialchars($artikel['art_omschrijving_en'] ?? '') ?></textarea>
                                    <button type="button" onclick="translateModalField('modal_art_omschrijving', 'modal_art_omschrijving_en', 'en', this)" class="btn btn--translate">🌍 Vertaal omschrijving</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn btn--main flex-1">Opslaan</button>
                        <button onclick="closeModal('omschrijving-modal')" class="btn btn--gray flex-1">Annuleren</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
// Voorbeeld: (zet dit boven je include van artikel_modals.php)
$kostprijs_excl = $artikel['art_kostprijs'] ?? 0;
$btw = $artikel['art_BTWtarief'] ?? 21;
$kostprijs_incl = $kostprijs_excl * (1 + $btw / 100);

$oudeprijs_excl = $artikel['art_oudeprijs'] ?? 0;
$oudeprijs_incl = $oudeprijs_excl * (1 + $btw / 100);

?>
<!-- Modal: Prijzen -->
<div id="prijzen-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box modal-box--sm">
            <form id="prijzen-form" method="post" action="/admin/detail?id=<?= $art_id ?>&zoek=<?= urlencode($zoek) ?>&cat=<?= urlencode($catFilter) ?>#anchor-prijzen">
                <input type="hidden" name="art_id" value="<?= (int)$art_id ?>">
                <input type="hidden" name="save_prijzen" value="1">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Prijzen &amp; BTW bewerken</h2>
                        <button type="button" onclick="closeModal('prijzen-modal')" class="modal-close">&times;</button>
                    </div>
                    <div class="form-group">
                        <label for="kostprijs_incl">Kostprijs incl. BTW (€)</label>
                        <input type="number" step="0.01" id="kostprijs_incl" name="kostprijs_incl" class="form-input"
                            value="<?= rtrim(rtrim(number_format((float)$kostprijs_incl, 2, '.', ''), '0'), '.') ?>">
                    </div>
                    <div class="form-group">
                        <label for="kostprijs_excl">Kostprijs excl. BTW (€)</label>
                        <input type="number" step="0.000001" id="kostprijs_excl" name="kostprijs_excl" class="form-input"
                            value="<?= rtrim(rtrim(number_format((float)$kostprijs_excl, 6, '.', ''), '0'), '.') ?>">
                    </div>
                    <div class="form-group">
                        <label for="oudeprijs_incl">Oude prijs incl. BTW (€)</label>
                        <input type="number" step="0.01" id="oudeprijs_incl" name="oudeprijs_incl" class="form-input"
                            value="<?= rtrim(rtrim(number_format((float)$oudeprijs_incl, 2, '.', ''), '0'), '.') ?>">
                    </div>
                    <div class="form-group">
                        <label for="oudeprijs_excl">Oude prijs excl. BTW (€)</label>
                        <input type="number" step="0.000001" id="oudeprijs_excl" name="oudeprijs_excl" class="form-input"
                            value="<?= rtrim(rtrim(number_format((float)$oudeprijs_excl, 6, '.', ''), '0'), '.') ?>">
                    </div>
                    <div class="form-group">
                        <label for="btw_tarief">BTW tarief (%)</label>
                        <select id="btw_tarief" name="btw_tarief" class="form-input">
                            <option value="21" <?= $btw == 21 ? 'selected' : '' ?>>21%</option>
                            <option value="12" <?= $btw == 12 ? 'selected' : '' ?>>12%</option>
                            <option value="6" <?= $btw == 6 ? 'selected' : '' ?>>6%</option>
                            <option value="0" <?= $btw == 0 ? 'selected' : '' ?>>0%</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn btn--main flex-1">Opslaan</button>
                        <button type="button" onclick="closeModal('prijzen-modal')" class="btn btn--gray flex-1">Annuleren</button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- Modal: Voorraad -->
<div id="voorraad-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box modal-box--sm">
            <form method="post" action="/admin/detail?id=<?= $art_id ?>&zoek=<?= urlencode($zoek) ?>&cat=<?= urlencode($catFilter) ?>#anchor-voorraad">
                <input type="hidden" name="art_id" value="<?= (int)$art_id ?>">
                <input type="hidden" name="save_voorraad" value="1">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Voorraad &amp; Verwerking bewerken</h2>
                        <button type="button" onclick="closeModal('voorraad-modal')" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-fields">
                        <?php
                        // Bepaal of er voorraad-varianten bestaan voor dit artikel
                        $has_variants = false;
                        if (!empty($art_id)) {
                            $rv = $mysqli->prepare("SELECT 1 FROM voorraad WHERE art_id = ? LIMIT 1");
                            if ($rv) {
                                $rv->bind_param('i', $art_id);
                                $rv->execute();
                                $rv->store_result();
                                if ($rv->num_rows > 0) $has_variants = true;
                                $rv->close();
                            }
                        }

                        if ($has_variants):
                            $searchName = urlencode($artikel['art_naam'] ?? '');
                        ?>
                            <div>
                                <label class="form-label">Voorraad</label>
                                <a href="/admin/voorraad?search=<?= $searchName ?>&filter=all" target="_blank" class="btn btn--sub" style="font-size:1em; padding:10px 14px; display:inline-block;">
                                    Voorraad beheren per variant
                                </a>
                                <div style="margin-top:8px;"><small style="color:#6b7280;">Dit artikel bevat voorraad volgens opties/varianten</small></div>
                            </div>
                        <?php else: ?>
                            <div>
                                <label class="form-label">Voorraad aantal</label>
                                <input type="number" name="art_aantal" value="<?= htmlspecialchars($artikel['art_aantal'] ?? '') ?>" class="form-input">
                            </div>
                        <?php endif; ?>

                        <div>
                            <label class="form-label">Levertijd (dagen)</label>
                            <input type="number" name="art_levertijd" value="<?= htmlspecialchars($artikel['art_levertijd'] ?? '') ?>" class="form-input">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn btn--main flex-1">Opslaan</button>
                        <button type="button" onclick="closeModal('voorraad-modal')" class="btn btn--gray flex-1">Annuleren</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Status -->
<div id="status-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box modal-box--sm">
            <form method="post" action="/admin/detail?id=<?= $art_id ?>&zoek=<?= urlencode($zoek) ?>&cat=<?= urlencode($catFilter) ?>#anchor-status">
                <input type="hidden" name="art_id" value="<?= (int)$art_id ?>">
                <input type="hidden" name="save_status" value="1">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Status bewerken</h2>
                        <button type="button" onclick="closeModal('status-modal')" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-fields">
                        <div>
                            <label class="form-label">Zichtbaarheid</label>
                            <select name="art_weergeven" class="form-input">
                                <option value="1" <?= (isset($artikel['art_weergeven']) && ($artikel['art_weergeven'] === 1 || $artikel['art_weergeven'] === '1' || strtolower((string)$artikel['art_weergeven']) === 'ja')) ? 'selected' : '' ?>>Zichtbaar</option>
                                <option value="0" <?= (!isset($artikel['art_weergeven']) || $artikel['art_weergeven'] === 0 || $artikel['art_weergeven'] === '0' || strtolower((string)$artikel['art_weergeven']) === 'nee') ? 'selected' : '' ?>>Verborgen</option>
                            </select>
                        </div>
                        <div class="grid-2 gap-1">
                            <div>
                                <label class="form-label">
                                    <!-- Hidden input ensures we send 0 when unchecked; checkbox overrides with 1 when checked -->
                                    <input type="hidden" name="art_indekijker" value="0">
                                    <input type="checkbox" name="art_indekijker" value="1"
                                        <?= (isset($artikel['art_indekijker']) && ($artikel['art_indekijker'] == 1 || strtolower((string)$artikel['art_indekijker']) == 'ja')) ? 'checked' : '' ?> class="mr-2">
                                    In de kijker
                                </label>
                            </div>
                            <div>
                                <label class="form-label">
                                    <input type="hidden" name="art_suggestie" value="0">
                                    <input type="checkbox" name="art_suggestie" value="1"
                                        <?= (isset($artikel['art_suggestie']) && ($artikel['art_suggestie'] == 1 || strtolower((string)$artikel['art_suggestie']) == 'ja' || $artikel['art_suggestie'] === '1')) ? 'checked' : '' ?> class="mr-2">
                                    Suggestie
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn btn--main flex-1">Opslaan</button>
                        <button type="button" onclick="closeModal('status-modal')" class="btn btn--gray flex-1">Annuleren</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Categorie -->
<div id="categorie-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box modal-box--md">
            <form method="post" action="/admin/detail?id=<?= $art_id ?>&zoek=<?= urlencode($zoek) ?>&cat=<?= urlencode($catFilter) ?>#anchor-categorie">
                <input type="hidden" name="art_id" value="<?= (int)$art_id ?>">
                <input type="hidden" name="save_categorie" value="1">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Categorie bewerken</h2>
                        <button type="button" onclick="closeModal('categorie-modal')" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-fields">
                        <div>
                            <label class="form-label">Categorie</label>
                            <select name="art_catID" class="form-input">
                                <option value="0">Geen categorie</option>
                                <?php
                                function buildCategoryPathFixed($categories, $categoryId)
                                {
                                    $path = [];
                                    $currentId = $categoryId;
                                    while ($currentId > 0) {
                                        foreach ($categories as $cat) {
                                            if ($cat['cat_id'] == $currentId) {
                                                array_unshift($path, $cat['cat_naam']);
                                                $currentId = intval($cat['cat_top_sub']);
                                                break;
                                            }
                                        }
                                        if (count($path) > 10) break;
                                    }
                                    return implode(' > ', $path);
                                }
                                $categoryPaths = [];
                                foreach ($categories as $cat) {
                                    $fullPath = buildCategoryPathFixed($categories, $cat['cat_id']);
                                    $categoryPaths[] = [
                                        'id' => $cat['cat_id'],
                                        'path' => $fullPath,
                                        'top_sub' => $cat['cat_top_sub']
                                    ];
                                }
                                usort($categoryPaths, function ($a, $b) {
                                    return strcmp($a['path'], $b['path']);
                                });
                                foreach ($categoryPaths as $catPath): ?>
                                    <option value="<?= $catPath['id'] ?>"
                                        <?= (isset($artikel['art_catID']) && $artikel['art_catID'] == $catPath['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($catPath['path']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn btn--main flex-1">Opslaan</button>
                        <button type="button" onclick="closeModal('categorie-modal')" class="btn btn--gray flex-1">Annuleren</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Nieuw artikel -->
<div id="nieuw-artikel-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box modal-box--md">
            <form method="post" action="/admin/artikelen">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Nieuw artikel toevoegen</h2>
                        <button type="button" onclick="closeModal('nieuw-artikel-modal')" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-fields">
                        <!-- Naam NL -->
                        <div class="modal-field">
                            <label for="nieuw_art_naam_nl" class="form-label">Naam (NL):</label>
                            <input type="text" name="art_naam" id="nieuw_art_naam_nl" class="form-input" required>
                        </div>
                        <!-- Naam FR -->
                        <?php if (in_array('fr', $talen)): ?>
                            <div class="modal-field">
                                <label for="nieuw_art_naam_fr" class="form-label">Naam (FR):</label>
                                <div class="modal-row">
                                    <input type="text" name="art_naam_fr" id="nieuw_art_naam_fr" class="form-input">
                                    <button type="button"
                                        onclick="translateModalField('art_naam', 'art_naam_fr', 'fr', this)"
                                        class="modal-btn modal-btn-blue">
                                        🌍 Vertaal
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        <!-- Naam EN -->
                        <?php if (in_array('en', $talen)): ?>
                            <div class="modal-field">
                                <label for="nieuw_art_naam_en" class="form-label">Naam (EN):</label>
                                <div class="modal-row">
                                    <input type="text" name="art_naam_en" id="nieuw_art_naam_en" class="form-input">
                                    <button type="button"
                                        onclick="translateModalField('art_naam', 'art_naam_en', 'en', this)"
                                        class="modal-btn modal-btn-blue">
                                        🌍 Vertaal
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Categorie -->
                        <div class="modal-field">
                            <label for="nieuw_art_catID" class="form-label">Categorie</label>
                            <select name="art_catID" id="nieuw_art_catID" class="form-input">
                                <option value="0">Geen categorie</option>
                                <?php
                                // Functie om het volledige pad van een categorie op te bouwen
                                function buildCategoryPath($categories, $cat, $lang = 'nl')
                                {
                                    $naam_field = 'cat_naam';
                                    if ($lang === 'fr') $naam_field = 'cat_naam_fr';
                                    if ($lang === 'en') $naam_field = 'cat_naam_en';

                                    $path = [];
                                    $current = $cat;
                                    while ($current && $current['cat_top_sub'] != 0) {
                                        array_unshift($path, $current[$naam_field]);
                                        // Zoek parent
                                        $parent = null;
                                        foreach ($categories as $c) {
                                            if ($c['cat_id'] == $current['cat_top_sub']) {
                                                $parent = $c;
                                                break;
                                            }
                                        }
                                        $current = $parent;
                                    }
                                    // Voeg root toe
                                    if ($current) array_unshift($path, $current[$naam_field]);
                                    return implode(' - ', $path);
                                }

                                // Toon alle categorieën met hiërarchie
                                foreach ($categories as $cat) {
                                    // Kies taal (hier NL, pas aan naar $talen indien gewenst)
                                    $cat_path = buildCategoryPath($categories, $cat, 'nl');
                                    echo '<option value="' . $cat['cat_id'] . '">' . htmlspecialchars($cat_path) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <!-- Voeg hier meer velden toe indien gewenst -->
                    </div><input type="hidden" name="nieuw_artikel" value="1">
                    <div class="modal-actions">
                        <button type="submit" class="btn btn--main flex-1">Opslaan</button>
                        <button type="button" onclick="closeModal('nieuw-artikel-modal')" class="btn btn--gray flex-1">Annuleren</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!isset($categories)) {
    echo "<!-- categories niet beschikbaar -->";
} ?>

<script>
    // Functie om slug te genereren uit naam
    function generateSlugFromName() {
        const nameInput = document.getElementById('modal_art_naam');
        const slugInput = document.getElementById('modal_art_afkorting');
        if (nameInput && slugInput) {
            const name = nameInput.value;
            const slug = generateSlug(name);
            slugInput.value = slug;
        }
    }

    function generateSlug(text) {
        return text
            .toLowerCase()
            .trim()
            .replace(/[àáâãäå]/g, 'a')
            .replace(/[èéêë]/g, 'e')
            .replace(/[ìíîï]/g, 'i')
            .replace(/[òóôõö]/g, 'o')
            .replace(/[ùúûü]/g, 'u')
            .replace(/[ýÿ]/g, 'y')
            .replace(/[ñ]/g, 'n')
            .replace(/[ç]/g, 'c')
            .replace(/[ß]/g, 'ss')
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/[\s-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function round2(val) {
        return Math.round(val * 100) / 100;
    }

    function round6(val) {
        return Math.round(val * 1000000) / 1000000;
    }

    function stripZeros(num) {
        // Verwijder overbodige nullen na de komma/punt
        return ('' + num).replace(/(\.\d*?[1-9])0+$/, '$1').replace(/\.0+$/, '').replace(/,$/, '');
    }

    function updateFromIncl() {
        let incl = parseFloat(document.getElementById('kostprijs_incl').value.replace(',', '.')) || 0;
        let btw = parseFloat(document.getElementById('btw_tarief').value) || 0;
        let excl = incl / (1 + btw / 100);
        document.getElementById('kostprijs_excl').value = stripZeros(round6(excl));
    }

    function updateFromExcl() {
        let excl = parseFloat(document.getElementById('kostprijs_excl').value.replace(',', '.')) || 0;
        let btw = parseFloat(document.getElementById('btw_tarief').value) || 0;
        let incl = excl * (1 + btw / 100);
        document.getElementById('kostprijs_incl').value = stripZeros(round2(incl));
    }

    function updateOudeFromIncl() {
        let incl = parseFloat(document.getElementById('oudeprijs_incl').value.replace(',', '.')) || 0;
        let btw = parseFloat(document.getElementById('btw_tarief').value) || 0;
        let excl = incl / (1 + btw / 100);
        document.getElementById('oudeprijs_excl').value = stripZeros(round6(excl));
    }

    function updateOudeFromExcl() {
        let excl = parseFloat(document.getElementById('oudeprijs_excl').value.replace(',', '.')) || 0;
        let btw = parseFloat(document.getElementById('btw_tarief').value) || 0;
        let incl = excl * (1 + btw / 100);
        document.getElementById('oudeprijs_incl').value = stripZeros(round2(incl));
    }

    function updateAllFromBtw() {
        updateFromExcl();
        updateOudeFromExcl();
    }

    document.getElementById('kostprijs_incl').addEventListener('input', updateFromIncl);
    document.getElementById('kostprijs_excl').addEventListener('input', updateFromExcl);
    document.getElementById('oudeprijs_incl').addEventListener('input', updateOudeFromIncl);
    document.getElementById('oudeprijs_excl').addEventListener('input', updateOudeFromExcl);
    document.getElementById('btw_tarief').addEventListener('change', updateAllFromBtw);

    // Logging zonder de submit te blokkeren (verwijder dit blok als je geen logging wilt)
    document.querySelector('#naam-modal form').addEventListener('submit', function(e) {
        const fd = new FormData(e.target);
        for (const pair of fd.entries()) console.log(pair[0], ':', pair[1]);
        // geen e.preventDefault() zodat het formulier normaal wordt verstuurd
    });
</script>