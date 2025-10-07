<!-- Modal voor categorie toevoegen/bewerken -->
<div id="cat-form-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box">
            <form id="cat-edit-form" method="post" action="/zozo-admin/includes/categorieen_opslaan.php" class="modal-form">
                <input type="hidden" name="cat_id" id="cat_id">
                <input type="hidden" name="cat_top_sub" id="cat_top_sub">
                <h2 id="modal-title" class="modal-title">Categorie toevoegen</h2>

                <div class="modal-fields">
                    <div class="modal-field">
                        <?php
                        // If French and English are not active, show plain "Naam:" otherwise show "Naam (NL):"
                        $showOnlyDutchLabel = !in_array('fr', $talen) && !in_array('en', $talen);
                        ?>
                        <label for="cat_naam"><?php echo $showOnlyDutchLabel ? 'Naam:' : 'Naam (NL):'; ?></label>
                        <input type="text" name="cat_naam" id="cat_naam" required>
                    </div>

                    <?php if (in_array('fr', $talen)): ?>
                        <div class="modal-field">
                            <label for="cat_naam_fr">Naam (FR):</label>
                            <div class="modal-row">
                                <input type="text" name="cat_naam_fr" id="cat_naam_fr">
                                <a href="#" class="translate-btn" data-src="cat_naam" data-dest="cat_naam_fr" data-lang="fr">vertaal</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('en', $talen)): ?>
                        <div class="modal-field">
                            <label for="cat_naam_en">Naam (EN):</label>
                            <div class="modal-row">
                                <input type="text" name="cat_naam_en" id="cat_naam_en">
                                <a href="#" class="translate-btn" data-src="cat_naam" data-dest="cat_naam_en" data-lang="en" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">vertaal</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="cat_afkorting" id="cat_afkorting">
                    <?php if (in_array('fr', $talen)): ?>
                        <input type="hidden" name="cat_afkorting_fr" id="cat_afkorting_fr">
                    <?php endif; ?>
                    <?php if (in_array('en', $talen)): ?>
                        <input type="hidden" name="cat_afkorting_en" id="cat_afkorting_en">
                    <?php endif; ?>
                </div>

                <input type="hidden" name="verborgen" id="verborgen" value="nee">
                <input type="hidden" name="cat_volgorde" id="cat_volgorde" value="999">

                <div class="modal-actions">
                    <button type="submit" class="modal-btn modal-btn-green">Opslaan</button>
                    <button type="button" onclick="closeForm()" class="modal-btn modal-btn-gray">Annuleren</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Slug generatie functie
    function generateSlug(text) {
        return text
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    }

    // Functie om slugs te genereren voor alle velden
    function generateAllSlugs() {
        // Nederlandse slug
        const nlName = document.getElementById('cat_naam').value;
        if (nlName) {
            document.getElementById('cat_afkorting').value = generateSlug(nlName);
        }

        // Franse slug
        <?php if (in_array('fr', $talen)): ?>
            const frName = document.getElementById('cat_naam_fr').value;
            if (frName) {
                document.getElementById('cat_afkorting_fr').value = generateSlug(frName);
            }
        <?php endif; ?>

        // Engelse slug
        <?php if (in_array('en', $talen)): ?>
            const enName = document.getElementById('cat_naam_en').value;
            if (enName) {
                document.getElementById('cat_afkorting_en').value = generateSlug(enName);
            }
        <?php endif; ?>
    }

    // Nederlandse slug automatisch genereren
    document.getElementById('cat_naam').addEventListener('input', function() {
        let slug = generateSlug(this.value);
        document.getElementById('cat_afkorting').value = slug;
    });

    // Franse slug automatisch genereren
    <?php if (in_array('fr', $talen)): ?>
        document.getElementById('cat_naam_fr').addEventListener('input', function() {
            let slug = generateSlug(this.value);
            document.getElementById('cat_afkorting_fr').value = slug;
        });
    <?php endif; ?>

    // Engelse slug automatisch genereren
    <?php if (in_array('en', $talen)): ?>
        document.getElementById('cat_naam_en').addEventListener('input', function() {
            let slug = generateSlug(this.value);
            document.getElementById('cat_afkorting_en').value = slug;
        });
    <?php endif; ?>

    // Bestaande vertaal-knop functionaliteit
    document.body.addEventListener('click', async function(e) {
        if (e.target && e.target.classList.contains('translate-btn')) {
            e.preventDefault();
            const srcId = e.target.getAttribute('data-src');
            const destId = e.target.getAttribute('data-dest');
            const lang = e.target.getAttribute('data-lang');
            const srcField = document.getElementById(srcId);
            const destField = document.getElementById(destId);

            if (!srcField.value.trim()) {
                alert('Vul eerst de Nederlandse naam in');
                return;
            }

            e.target.disabled = true;
            e.target.textContent = '⏳ Bezig...';

            try {
                const translation = await translateText(srcField.value, lang);
                if (translation) {
                    destField.value = translation;
                    // BELANGRIJK: Ook de slug genereren bij automatisch vertalen
                    const slugFieldId = destId.replace('cat_naam', 'cat_afkorting');
                    const slugField = document.getElementById(slugFieldId);
                    if (slugField) {
                        slugField.value = generateSlug(translation);
                    }
                }
            } catch (error) {
                alert('Vertaling mislukt');
            } finally {
                e.target.disabled = false;
                e.target.textContent = 'vertaal';
            }
        }
    });
</script>