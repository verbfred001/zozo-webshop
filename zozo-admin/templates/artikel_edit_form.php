<form method="post" id="artikel-form" class="hidden">
    <input type="hidden" name="art_naam" id="form_art_naam" value="<?= htmlspecialchars($artikel['art_naam']) ?>">
    <input type="hidden" name="art_naam_fr" id="form_art_naam_fr" value="<?= htmlspecialchars($artikel['art_naam_fr'] ?? '') ?>">
    <input type="hidden" name="art_naam_en" id="form_art_naam_en" value="<?= htmlspecialchars($artikel['art_naam_en'] ?? '') ?>">
    <input type="hidden" name="art_afkorting" id="form_art_afkorting" value="<?= htmlspecialchars($artikel['art_afkorting']) ?>">
    <input type="hidden" name="art_afkorting_fr" id="form_art_afkorting_fr" value="<?= htmlspecialchars($artikel['art_afkorting_fr'] ?? '') ?>">
    <input type="hidden" name="art_afkorting_en" id="form_art_afkorting_en" value="<?= htmlspecialchars($artikel['art_afkorting_en'] ?? '') ?>">
    <input type="hidden" name="art_kenmerk" id="form_art_kenmerk" value="<?= htmlspecialchars($artikel['art_kenmerk']) ?>">
    <input type="hidden" name="art_kenmerk_fr" id="form_art_kenmerk_fr" value="<?= htmlspecialchars($artikel['art_kenmerk_fr'] ?? '') ?>">
    <input type="hidden" name="art_kenmerk_en" id="form_art_kenmerk_en" value="<?= htmlspecialchars($artikel['art_kenmerk_en'] ?? '') ?>">
    <input type="hidden" name="art_omschrijving" id="form_art_omschrijving" value="<?= htmlspecialchars($artikel['art_omschrijving']) ?>">
    <input type="hidden" name="art_omschrijving_fr" id="form_art_omschrijving_fr" value="<?= htmlspecialchars($artikel['art_omschrijving_fr'] ?? '') ?>">
    <input type="hidden" name="art_omschrijving_en" id="form_art_omschrijving_en" value="<?= htmlspecialchars($artikel['art_omschrijving_en'] ?? '') ?>">
    <input type="hidden" name="art_kostprijs" id="form_art_kostprijs" value="<?= $artikel['art_kostprijs'] ?>">
    <input type="hidden" name="art_oudeprijs" id="form_art_oudeprijs" value="<?= $artikel['art_oudeprijs'] ?>">
    <input type="hidden" name="art_BTWtarief" id="form_art_BTWtarief" value="<?= $artikel['art_BTWtarief'] ?>">
    <input type="hidden" name="art_aantal" id="form_art_aantal" value="<?= $artikel['art_aantal'] ?>">
    <input type="hidden" name="art_catID" id="form_art_catID" value="<?= $artikel['art_catID'] ?>">
    <?php
    // Normaliseer waarden voor hidden inputs: output 1/0 (but keep original if null)
    $hidden_weergeven = isset($artikel['art_weergeven']) && ($artikel['art_weergeven'] === 1 || $artikel['art_weergeven'] === '1' || strtolower((string)$artikel['art_weergeven']) === 'ja') ? 1 : 0;
    $hidden_indekijker = isset($artikel['art_indekijker']) && ($artikel['art_indekijker'] === 1 || $artikel['art_indekijker'] === '1' || strtolower((string)$artikel['art_indekijker']) === 'ja') ? 1 : 0;
    $hidden_suggestie = isset($artikel['art_suggestie']) && ($artikel['art_suggestie'] === 1 || $artikel['art_suggestie'] === '1' || strtolower((string)$artikel['art_suggestie']) === 'ja') ? 1 : 0;
    ?>
    <input type="hidden" name="art_weergeven" id="form_art_weergeven" value="<?= $hidden_weergeven ?>">
    <input type="hidden" name="art_indekijker" id="form_art_indekijker" value="<?= $hidden_indekijker ?>">
    <input type="hidden" name="art_levertijd" id="form_art_levertijd" value="<?= $artikel['art_levertijd'] ?>">
    <input type="hidden" name="art_suggestie" id="form_art_suggestie" value="<?= $hidden_suggestie ?>">
</form>