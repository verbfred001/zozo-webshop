<div id="tab-talen" class="tab-content <?= isset($active_tab) && $active_tab === 'talen' ? '' : 'hidden' ?>">
    <div class="settings-card">
        <form method="post" action="/admin/instellingen/talen" class="form-section">
            <div class="form-section-box">
                <h3 class="form-section-title">Beschikbare talen</h3>
                <div class="form-checkbox-list">
                    <label><input type="checkbox" checked disabled> Nederlands (altijd actief)</label>
                    <label><input type="checkbox" name="talen_fr" value="1" <?= $instellingen['talen_fr'] ? 'checked' : '' ?>> Frans</label>
                    <label><input type="checkbox" name="talen_en" value="1" <?= $instellingen['talen_en'] ? 'checked' : '' ?>> Engels</label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="save_talen" class="btn btn--add">Opslaan</button>
            </div>
        </form>
    </div>
</div>