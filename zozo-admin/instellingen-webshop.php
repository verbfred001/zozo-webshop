<div id="tab-shop" class="tab-content <?= isset($active_tab) && $active_tab === 'shop' ? '' : 'hidden' ?>">
    <div class="settings-card">
        <?php if (!isset($instellingen['form_voorraadbeheer']) || (int)$instellingen['form_voorraadbeheer'] === 1): ?>
            <form method="post" action="/admin/instellingen/webshop" class="form-section">
                <div class="form-section-box">
                    <h3 class="form-section-title">Webshop instellingen</h3>
                    <div class="form-grid">
                        <div>
                            <label class="form-label">Voorraadbeheer</label>
                            <div class="form-checkbox-list">
                                <label>
                                    <input type="checkbox" name="voorraadbeheer" value="1" <?= !empty($instellingen['voorraadbeheer']) ? 'checked' : '' ?>>
                                    Voorraadbeheer actief
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="save_webshop" class="btn btn--add">Opslaan</button>
                </div>
            </form>
        <?php endif; ?>

        <!-- Separate form for tijd override -->
        <form method="post" action="/admin/instellingen/webshop" class="form-section" style="margin-top:18px;position:relative">
            <div class="form-section-box">
                <h3 class="form-section-title">Tijd override (testen)</h3>
                <?php
                // Prepare current value for the datetime-local input
                // Use only the session-scoped override (per-browser). Do NOT read the DB-persisted value here.
                $dt_val = '';
                if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                if (!empty($_SESSION['tijd_override'])) {
                    $try = $_SESSION['tijd_override'];
                    $d1 = DateTime::createFromFormat('Y-m-d H:i', $try);
                    if (!$d1) $d1 = DateTime::createFromFormat('d/m/Y-H:i', $try);
                    if ($d1) $dt_val = $d1->format('Y-m-d\\TH:i');
                }
                ?>
                <div style="display:flex;gap:12px;align-items:center">
                    <input type="datetime-local" name="tijd_override" class="form-input" value="<?= htmlspecialchars($dt_val) ?>" style="max-width:260px">
                    <div style="display:flex;gap:8px">
                        <button type="submit" name="set_tijd" class="btn btn--add">Tijd instellen</button>
                        <button type="submit" name="clear_tijd" class="btn">Tijd verwijderen</button>
                    </div>
                </div>
                <div class="hint" style="margin-top:8px">Test die enkel op jouw PC actief is</div>
            </div>
        </form>

        <?php // Session badge for active session override (small top-left) 
        ?>
        <?php if (session_status() !== PHP_SESSION_ACTIVE) session_start(); ?>
        <?php if (!empty($_SESSION['tijd_override'])): ?>
            <?php $sdt = DateTime::createFromFormat('Y-m-d H:i', $_SESSION['tijd_override']) ?: DateTime::createFromFormat('d/m/Y-H:i', $_SESSION['tijd_override']); ?>
            <?php if ($sdt): ?>
                <?php
                $weekdays = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
                $wd = $weekdays[(int)$sdt->format('w')];
                $label = $wd . ' ' . $sdt->format('d/m/Y H:i');
                ?>
                <div style="position:fixed;left:12px;top:12px;background:#111;color:#fff;padding:6px 10px;border-radius:6px;font-size:0.85rem;z-index:9999"><?= htmlspecialchars($label) ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>