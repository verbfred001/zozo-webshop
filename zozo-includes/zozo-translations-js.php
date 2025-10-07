<script>
    window.currentLang = '<?= $lang ?>';
    window.translations = {
        kies: "<?= addslashes($translations['Kies...'][$lang] ?? 'Kies...') ?>",
        vulin: "<?= addslashes($translations['Vul in...'][$lang] ?? 'Vul in...') ?>",
        aantal: "<?= addslashes($translations['Aantal:'][$lang] ?? 'Aantal:') ?>",
        eenheidsprijs: "<?= addslashes($translations['Eenheidsprijs:'][$lang] ?? 'Eenheidsprijs:') ?>",
        toevoegen: "<?= addslashes($translations['Toevoegen aan winkelwagen'][$lang] ?? 'Toevoegen aan winkelwagen') ?>",
        in_mijn_winkelwagen: "<?= addslashes($translations['In mijn winkelwagen'][$lang] ?? ($lang === 'fr' ? 'Dans mon panier' : ($lang === 'en' ? 'In my cart' : 'In mijn winkelwagen'))) ?>",
        niet_voorradig: "<?= addslashes($translations['Niet voorradig'][$lang] ?? 'Niet voorradig') ?>",
        verplichte_velden: "<?= addslashes($translations['verplichte velden'][$lang] ?? 'verplichte velden') ?>",
        vul_verplichte_velden_in: "<?= addslashes($translations['Vul alstublieft alle verplichte velden in.'][$lang] ?? 'Vul alstublieft alle verplichte velden in.') ?>",
        // nieuwe keys voor voorraadmeldingen
        op_stock: "<?= addslashes($translations['op_stock'][$lang] ?? 'Op stock') ?>",
        voorraad_slechts: "<?= addslashes($translations['voorraad_slechts'][$lang] ?? 'Voorraad: slechts %s beschikbaar') ?>",
        op_maat: "<?= addslashes($translations['op maat'][$lang] ?? 'op maat') ?>"
    };
</script>