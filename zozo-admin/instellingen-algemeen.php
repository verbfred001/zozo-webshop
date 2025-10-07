<div id="tab-algemeen" class="tab-content">
    <div class="settings-card">
        <form method="post" action="instellingen" class="form-section">
            <div class="form-grid">
                <div>
                    <label class="form-label">Bedrijfsnaam</label>
                    <input type="text" name="bedrijfsnaam" value="<?= htmlspecialchars($instellingen['bedrijfsnaam']) ?>" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Adres</label>
                    <input type="text" name="adres" value="<?= htmlspecialchars($instellingen['adres']) ?>" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Telefoon</label>
                    <input type="text" name="telefoon" value="<?= htmlspecialchars($instellingen['telefoon']) ?>" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">BTW-nummer</label>
                    <input type="text" name="btw_nummer" value="<?= htmlspecialchars($instellingen['btw_nummer']) ?>" class="form-input">
                </div>
                <div>
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($instellingen['email']) ?>" class="form-input" required>
                </div>
            </div>
            <div class="form-section-box">
                <h3 class="form-section-title">Openingsuren</h3>
                <div class="form-grid">
                    <?php
                    $dagen = [
                        'maandag',
                        'dinsdag',
                        'woensdag',
                        'donderdag',
                        'vrijdag',
                        'zaterdag',
                        'zondag'
                    ];
                    foreach ($dagen as $dag): ?>
                        <div>
                            <label class="form-label"><?= ucfirst($dag) ?></label>
                            <input type="text" name="openingsuren_<?= $dag ?>" value="<?= htmlspecialchars($instellingen['openingsuren_' . $dag]) ?>" class="form-input" placeholder="bv. 09:00 - 18:00">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="save_general" class="btn btn--add">Opslaan</button>
            </div>
        </form>
    </div>
</div>