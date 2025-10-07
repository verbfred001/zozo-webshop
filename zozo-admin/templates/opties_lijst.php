<div id="optiegroepen-lijst" class="optiegroepen-lijst">
    <?php foreach ($groups as $group): ?>
        <div class="optiegroep optiegroep--main" data-id="<?= $group['group_id'] ?>" id="groep-<?= $group['group_id'] ?>">
            <div class="optiegroep-header">
                <div class="optiegroep-info">
                    <span class="optiegroep-drag">⋮⋮</span>
                    <div>
                        <h2 class="optiegroep-title">
                            <?= htmlspecialchars($group['group_name']) ?>
                            <?php if (!empty($group['info'])): ?>
                                <span class="option-info" style="color:#ff9800;font-size:0.95em;margin-left:8px;">
                                    > <?= htmlspecialchars($group['info']) ?>
                                </span>
                            <?php endif; ?>
                        </h2>
                        <div class="optiegroep-labels">
                            <span class="optiegroep-type">
                                <?= htmlspecialchars($group['type']) ?>
                            </span>
                            <?php if ($group['affects_stock']): ?>
                                <span class="optiegroep-stock">
                                    Beïnvloedt voorraad
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="optiegroep-actions">
                    <?php if (!in_array($group['type'], ['text', 'textarea'])): ?>
                        <button type="button" onclick="showAddOptionForm(<?= $group['group_id'] ?>)"
                            class="btn btn--sub">
                            + Optie
                        </button>
                    <?php endif; ?>
                    <button onclick="showEditGroupForm(<?= $group['group_id'] ?>)"
                        class="btn btn--edit">
                        <svg class="icon icon--edit" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-2.828 0L9 13zm0 0V17h4"></path>
                        </svg>
                    </button>
                    <button onclick="deleteGroup(<?= $group['group_id'] ?>)"
                        class="btn btn--delete"
                        title="Verwijderen">
                        <svg class="icon icon--delete" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <ul class="opties-lijst" id="opties-lijst-<?= $group['group_id'] ?>">
                <?php if ($group['type'] == 'text'): ?>
                    <li class="optie-info optie-info--text">
                        📝 Gebruikers kunnen hier een korte tekst invullen (bijv. naam, opmerking)
                    </li>
                <?php elseif ($group['type'] == 'textarea'): ?>
                    <li class="optie-info optie-info--textarea">
                        📄 Gebruikers kunnen hier een lange tekst invullen (bijv. uitgebreide opmerking, wensen)
                    </li>
                <?php elseif (empty($group['options'])): ?>
                    <li class="optie-info optie-info--empty">
                        Nog geen opties toegevoegd. Klik op "+Optie" om de eerste toe te voegen.
                    </li>
                <?php else: ?>
                    <?php foreach ($group['options'] as $opt): ?>
                        <li class="optie" data-id="<?= $opt['option_id'] ?>" id="optie-<?= $opt['option_id'] ?>">
                            <div class="optie-info-row">
                                <span class="optie-drag" title="Versleep om te sorteren">⋮⋮</span>
                                <span class="optie-title">
                                    <?= htmlspecialchars($opt['option_name']) ?>
                                </span>
                                <?php if ($opt['price_delta'] != 0): ?>
                                    <span class="optie-price <?= $opt['price_delta'] > 0 ? 'optie-price--plus' : 'optie-price--min' ?>">
                                        <?= $opt['price_delta'] > 0 ? '+' : '' ?>€ <?= number_format($opt['price_delta'], 2, ',', '.') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="optie-actions">
                                <button onclick="showEditOptionForm(<?= $opt['option_id'] ?>, <?= $group['group_id'] ?>)"
                                    class="btn btn--edit">
                                    <svg class="icon icon--edit" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-2.828 0L9 13zm0 0V17h4"></path>
                                    </svg>
                                </button>
                                <button onclick="deleteOption(<?= $opt['option_id'] ?>)"
                                    class="btn btn--delete"
                                    title="Verwijderen">
                                    <svg class="icon icon--delete" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    <?php endforeach; ?>

    <?php if (empty($groups)): ?>
        <div class="optiegroepen-empty">
            <p class="optiegroepen-empty-title">Nog geen optiegroepen aangemaakt</p>
            <button onclick="showAddGroupForm()" class="btn btn--add">
                Eerste optiegroep aanmaken
            </button>
        </div>
    <?php endif; ?>
</div>