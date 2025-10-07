<div id="tab-melding" class="tab-content <?= isset($active_tab) && $active_tab === 'melding' ? '' : 'hidden' ?>">
    <div class="settings-card">
        <form method="post" action="/admin/instellingen/tijdelijke-melding" class="form-section">
            <div class="form-section-box">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                    <h3 class="form-section-title" style="margin:0;">Site melding</h3>
                    <label style="display:flex; align-items:center; gap:8px; margin:0; font-weight:normal;">
                        <input type="checkbox" name="melding_actief" value="1" <?= !empty($instellingen['melding_actief']) ? 'checked' : '' ?>>
                        <span>Actief</span>
                    </label>
                </div>

                <div class="form-grid" style="margin-top:12px;">
                    <div style="grid-column: 1 / -1;">
                        <label class="form-label">Meldingstekst</label>
                        <input type="text" id="melding_tekst" name="melding_tekst" value="<?= htmlspecialchars($instellingen['melding_tekst'] ?? '') ?>" class="form-input" placeholder="Eén regel tekst">
                    </div>

                    <?php if (!empty($instellingen['talen_fr'])): ?>
                        <div style="display:flex; gap:12px; align-items:flex-start; grid-column: 1 / -1;">
                            <div style="flex:1;">
                                <label class="form-label">Meldingstekst (FR)</label>
                                <input type="text" id="melding_tekst_fr" name="melding_tekst_fr" value="<?= htmlspecialchars($instellingen['melding_tekst_fr'] ?? '') ?>" class="form-input" placeholder="Franse vertaling (kan automatisch gevuld worden)">
                            </div>
                            <div style="width:120px; display:flex; align-items:end;">
                                <button type="button" class="btn btn--translate" style="width:100%;" onclick="translateModalField('melding_tekst','melding_tekst_fr','fr', this);">🌍 Vertaal</button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($instellingen['talen_en'])): ?>
                        <div style="display:flex; gap:12px; align-items:flex-start; grid-column: 1 / -1; margin-top:6px;">
                            <div style="flex:1;">
                                <label class="form-label">Meldingstekst (EN)</label>
                                <input type="text" id="melding_tekst_en" name="melding_tekst_en" value="<?= htmlspecialchars($instellingen['melding_tekst_en'] ?? '') ?>" class="form-input" placeholder="Engelse vertaling (kan automatisch gevuld worden)">
                            </div>
                            <div style="width:120px; display:flex; align-items:end;">
                                <button type="button" class="btn btn--translate" style="width:100%;" onclick="translateModalField('melding_tekst','melding_tekst_en','en', this);">🌍 Vertaal</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="save_melding" class="btn btn--add">Opslaan</button>
            </div>
        </form>
    </div>

    <!-- Ensure translation helper is available on this page -->
    <script src="/zozo-admin/js/artikel-modals.js"></script>
</div>