<!-- TOP SECTIE: Foto + Naam (herkenbaar) -->
<div class="artikel-topbox">
    <div class="artikel-topbox-row">
        <!-- Foto sectie -->
        <div class="artikel-foto-col">
            <div class="artikel-foto-box">
                <div class="artikel-foto-header">
                    <h3 class="artikel-foto-title">Artikel foto's</h3>
                    <button type="button" onclick="openModal('foto-modal')" class="btn btn--blue btn--sm">
                        <span>📸</span>
                        <span>Beheer foto's</span>
                    </button>
                </div>
                <div class="artikel-foto-grid" id="foto-grid">
                    <?php for ($i = 0; $i < 6; $i++):
                        $hasImage = isset($images[$i]) && !empty($images[$i]);
                    ?>
                        <div class="artikel-foto-slot" id="foto-slot-<?= $i ?>">
                            <?php if ($hasImage): ?>
                                <img src="/upload/<?= htmlspecialchars($images[$i]['image_name']) ?>"
                                    alt="Foto <?= $i + 1 ?>" class="artikel-foto-img">
                                <?php if ($i === 0 && $hasImage): ?>
                                    <div class="artikel-foto-hoofd">★</div>
                                <?php endif; ?>
                                <div class="artikel-foto-volg"><?= $i + 1 ?></div>
                            <?php else: ?>
                                <div class="artikel-foto-leeg">
                                    <div class="artikel-foto-leeg-icoon">📷</div>
                                    <div class="artikel-foto-leeg-volg"><?= $i + 1 ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <p class="artikel-foto-info">
                    <?= count($images ?? []) ?>/6 foto's • ★ = hoofdfoto
                </p>
            </div>
        </div>

        <!-- Naam sectie rechts -->
        <div class="artikel-naam-col">
            <div class="artikel-naam-header">
                <div>
                    <h2 class="artikel-naam-title" id="display_art_naam">
                        <?= htmlspecialchars($artikel['art_naam'] ?: 'Geen naam') ?>
                    </h2>
                    <div class="artikel-naam-talen">
                        <?php if (in_array('fr', $talen ?? [])): ?>
                            <p class="artikel-naam-taal"><small>FR: <?= htmlspecialchars($artikel['art_naam_fr'] ?: 'Niet vertaald') ?></small></p>
                        <?php endif; ?>
                        <?php if (in_array('en', $talen ?? [])): ?>
                            <p class="artikel-naam-taal"><small>EN: <?= htmlspecialchars($artikel['art_naam_en'] ?: 'Niet vertaald') ?></small></p>
                        <?php endif; ?>
                    </div>
                </div>
                <button onclick="openModal('naam-modal')" class="btn btn--blue btn--sm artikel-naam-btn">
                    <svg class="btn-icon mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    <span>Naam / kenmerk</span>
                </button>
            </div>
            <div class="artikel-naam-info">
                <div>
                    <span class="artikel-naam-label">Kenmerk:</span>
                    <?php if ($artikel['art_kenmerk']): ?>
                        <span class="artikel-naam-value"><?= htmlspecialchars($artikel['art_kenmerk']) ?></span>
                    <?php else: ?>
                        <span class="artikel-naam-value" style="font-style:italic; font-weight:normal; color:#888;">(Niet ingevuld)</span>
                    <?php endif; ?>
                    <div class="artikel-naam-talen">
                        <?php if (in_array('fr', $talen ?? [])): ?>
                            <p class="artikel-naam-taal"><small>FR: <?= $artikel['art_kenmerk_fr'] ? htmlspecialchars($artikel['art_kenmerk_fr']) : '<span style="font-style:italic; color:#888;">Niet vertaald</span>' ?></small></p>
                        <?php endif; ?>
                        <?php if (in_array('en', $talen ?? [])): ?>
                            <p class="artikel-naam-taal"><small>EN: <?= $artikel['art_kenmerk_en'] ? htmlspecialchars($artikel['art_kenmerk_en']) : '<span style="font-style:italic; color:#888;">Niet vertaald</span>' ?></small></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <span class="artikel-naam-label">Kostprijs:</span>
                    <span class="artikel-naam-value"><?= number_format($artikel['art_kostprijs'], 2, ',', '.') ?></span>
                    <small style="font-weight:normal; color:#666;">
                        (<?= number_format($artikel['art_kostprijs'] * (1 + $artikel['art_BTWtarief'] / 100), 2, ',', '.') ?> incl.btw)
                    </small>
                </div>
                <div>
                    <span class="artikel-naam-label">Status:</span>
                    <?php $visible = isset($artikel['art_weergeven']) && ($artikel['art_weergeven'] === 1 || $artikel['art_weergeven'] === '1' || strtolower((string)$artikel['art_weergeven']) === 'ja'); ?>
                    <span class="artikel-naam-value <?= $visible ? 'text-green' : 'text-red' ?>">
                        <?= $visible ? 'Zichtbaar' : 'Verborgen' ?>
                    </span>
                </div>
                <div>
                    <span class="artikel-naam-label">Voorraad:</span>
                    <span class="artikel-naam-value"><?= $artikel['art_aantal'] ?> stuks</span>
                </div>
                <div>
                    <span class="artikel-naam-label">Categorie:</span>
                    <span class="artikel-naam-value">
                        <?php
                        function getCategoryHierarchyTop($categories, $categoryId)
                        {
                            if ($categoryId == 0) return 'Geen categorie';
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
                            if (empty($path)) return 'Onbekende categorie';
                            return implode(' > ', $path);
                        }
                        echo htmlspecialchars(getCategoryHierarchyTop($categories, $artikel['art_catID']));
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Naam & Kenmerk bewerken -->
<div id="naam-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box modal-box--sm">
            <form method="post" action="/admin/detail?id=<?= $art_id ?>&zoek=<?= urlencode($zoek) ?>&cat=<?= urlencode($catFilter) ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Artikel naam &amp; kenmerk bewerken</h2>
                        <button type="button" onclick="closeModal('naam-modal')" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-fields">
                        <!-- NL -->
                        <div>
                            <label class="form-label">Naam (NL) *</label>
                            <input type="text" name="art_naam" value="<?= htmlspecialchars($artikel['art_naam'] ?? '') ?>" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">Kenmerk (NL)</label>
                            <input type="text" name="art_kenmerk" value="<?= htmlspecialchars($artikel['art_kenmerk'] ?? '') ?>" class="form-input">
                        </div>

                        <!-- FR -->
                        <?php if (in_array('fr', $talen ?? [])): ?>
                            <hr>
                            <div style="font-weight:bold; margin-bottom:0.5em;">Frans</div>
                            <div>
                                <label class="form-label">
                                    Naam (FR)
                                    <a href="#" onclick="translateModalField('art_naam', 'art_naam_fr', 'fr', this); return false;" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">vertaal</a>
                                </label>
                                <input type="text" name="art_naam_fr" value="<?= htmlspecialchars($artikel['art_naam_fr'] ?? '') ?>" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">
                                    Kenmerk (FR)
                                    <a href="#" onclick="translateModalField('art_kenmerk', 'art_kenmerk_fr', 'fr', this); return false;" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">vertaal</a>
                                </label>
                                <input type="text" name="art_kenmerk_fr" value="<?= htmlspecialchars($artikel['art_kenmerk_fr'] ?? '') ?>" class="form-input">
                            </div>
                        <?php endif; ?>

                        <!-- EN -->
                        <?php if (in_array('en', $talen ?? [])): ?>
                            <hr>
                            <div style="font-weight:bold; margin-bottom:0.5em;">Engels</div>
                            <div>
                                <label class="form-label">
                                    Naam (EN)
                                    <a href="#" onclick="translateModalField('art_naam', 'art_naam_en', 'en', this); return false;" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">vertaal</a>
                                </label>
                                <input type="text" name="art_naam_en" value="<?= htmlspecialchars($artikel['art_naam_en'] ?? '') ?>" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">
                                    Kenmerk (EN)
                                    <a href="#" onclick="translateModalField('art_kenmerk', 'art_kenmerk_en', 'en', this); return false;" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">vertaal</a>
                                </label>
                                <input type="text" name="art_kenmerk_en" value="<?= htmlspecialchars($artikel['art_kenmerk_en'] ?? '') ?>" class="form-input">
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