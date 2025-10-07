<!-- MODAL 1: Nieuwe optiegroep -->
<div id="newoptiongroup-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box">
            <div class="modal-box-inner">
                <div class="modal-header">
                    <h2 class="modal-title">Nieuwe optiegroep toevoegen</h2>
                    <button onclick="closeModal('newoptiongroup-modal')" class="modal-close">
                        <svg class="icon icon--close" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form method="post" action="/admin/opties" class="modal-form">
                    <input type="hidden" name="add_group" value="1">

                    <div class="modal-field">
                        <label for="newoptiongroup_name_nl">Naam (NL):</label>
                        <input type="text" name="group_name_nl" id="newoptiongroup_name_nl" required>
                    </div>

                    <?php if (in_array('fr', $talen)): ?>
                        <div class="modal-field">
                            <label for="newoptiongroup_name_fr">Naam (FR):</label>
                            <div class="modal-row">
                                <input type="text" name="group_name_fr" id="newoptiongroup_name_fr">
                                <a href="#" id="newoptiongroup-translate-fr-btn" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">
                                    vertaal
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('en', $talen)): ?>
                        <div class="modal-field">
                            <label for="newoptiongroup_name_en">Naam (EN):</label>
                            <div class="modal-row">
                                <input type="text" name="group_name_en" id="newoptiongroup_name_en">
                                <a href="#" id="newoptiongroup-translate-en-btn" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">
                                    vertaal
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="modal-field">
                        <label for="newoptiongroup_type">Type:</label>
                        <select name="type" id="newoptiongroup_type" required>
                            <option value="select">Select</option>
                            <option value="radio">Radio</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="text">Tekstveld</option>
                            <option value="textarea">Tekstvak</option>
                        </select>
                    </div>

                    <?php if (!isset($voorraadbeheer) || $voorraadbeheer): ?>
                        <div class="modal-field modal-checkbox" style="display: flex; align-items: center; gap: 0.5em;">
                            <input type="checkbox" name="affects_stock" id="newoptiongroup_affects_stock">
                            <label for="newoptiongroup_affects_stock" style="margin: 0;">
                                Beïnvloedt voorraad
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="modal-field">
                        <label for="newoptiongroup_info">Info <span style="color:#ff9800;font-size:0.95em;">(bv voor categorie fietsen)</span></label>
                        <input type="text" id="newoptiongroup_info" name="info" value="">
                    </div>

                    <div class="modal-actions">
                        <button type="submit" class="modal-btn modal-btn-green">Toevoegen</button>
                        <button type="button" onclick="closeModal('newoptiongroup-modal')" class="modal-btn modal-btn-gray">Annuleren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 2: Nieuwe optie -->
<div id="newoption-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box">
            <div class="modal-box-inner">
                <div class="modal-header">
                    <h2 id="newoption-modal-title" class="modal-title">Optie toevoegen</h2>
                    <button onclick="closeModal('newoption-modal')" class="modal-close">
                        <svg class="icon icon--close" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="newoption-form" method="post" action="/admin/opties" class="modal-form">
                    <input type="hidden" name="add_option" value="1">
                    <input type="hidden" name="group_id" id="newoption_group_id">

                    <div class="modal-field">
                        <label for="newoption_name_nl">Optie (NL):</label>
                        <input type="text" name="option_name_nl" id="newoption_name_nl" required>
                    </div>

                    <?php if (in_array('fr', $talen)): ?>
                        <div class="modal-field">
                            <label for="newoption_name_fr">Optie (FR):</label>
                            <div class="modal-row">
                                <input type="text" name="option_name_fr" id="newoption_name_fr">
                                <a href="#" id="newoption-translate-fr-btn" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">
                                    vertaal
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('en', $talen)): ?>
                        <div class="modal-field">
                            <label for="newoption_name_en">Optie (EN):</label>
                            <div class="modal-row">
                                <input type="text" name="option_name_en" id="newoption_name_en">
                                <a href="#" id="newoption-translate-en-btn" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">
                                    vertaal
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="modal-field">
                        <label for="newoption_price_delta">Meerprijs:</label>
                        <input type="number" step="0.01" name="price_delta" id="newoption_price_delta" value="0">
                    </div>

                    <div class="modal-actions">
                        <button type="submit" id="newoption-submit-btn" class="modal-btn modal-btn-green">Toevoegen</button>
                        <button type="button" onclick="closeModal('newoption-modal')" class="modal-btn modal-btn-gray">Annuleren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 3: Edit optiegroep -->
<div id="editoptiongroup-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box">
            <div class="modal-box-inner">
                <div class="modal-header">
                    <h2 id="editoptiongroup-modal-title" class="modal-title">Optiegroep bewerken</h2>
                    <button onclick="closeModal('editoptiongroup-modal')" class="modal-close">
                        <svg class="icon icon--close" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="editoptiongroup-form" method="post" action="/admin/opties" class="modal-form">
                    <input type="hidden" name="update_group" value="1">
                    <input type="hidden" name="group_id" id="editoptiongroup_id">

                    <div class="modal-field">
                        <label for="editoptiongroup_name_nl">Naam (NL):</label>
                        <input type="text" name="group_name_nl" id="editoptiongroup_name_nl" required>
                    </div>

                    <?php if (in_array('fr', $talen)): ?>
                        <div class="modal-field">
                            <label for="editoptiongroup_name_fr">Naam (FR):</label>
                            <div class="modal-row">
                                <input type="text" name="group_name_fr" id="editoptiongroup_name_fr">
                                <a href="#" id="editoptiongroup-translate-fr-btn" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">
                                    vertaal
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('en', $talen)): ?>
                        <div class="modal-field">
                            <label for="editoptiongroup_name_en">Naam (EN):</label>
                            <div class="modal-row">
                                <input type="text" name="group_name_en" id="editoptiongroup_name_en">
                                <a href="#" id="editoptiongroup-translate-en-btn" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">
                                    vertaal
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="modal-field">
                        <label for="editoptiongroup_type_display">Type:</label>
                        <div class="modal-readonly">
                            <span id="editoptiongroup_type_display"></span>
                        </div>
                    </div>

                    <?php if (!isset($voorraadbeheer) || $voorraadbeheer): ?>
                        <div class="modal-field modal-checkbox" style="display: flex; align-items: center; gap: 0.5em;">
                            <input type="checkbox" name="affects_stock" id="editoptiongroup_affects_stock">
                            <label for="editoptiongroup_affects_stock" style="margin: 0;">
                                Beïnvloedt voorraad
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="modal-field">
                        <label for="editoptiongroup_info">Info <span style="color:#ff9800;font-size:0.95em;">(bv voor categorie fietsen)</span></label>
                        <input type="text" id="editoptiongroup_info" name="info" value="">
                    </div>

                    <div class="modal-actions">
                        <button type="submit" id="editoptiongroup-submit-btn" class="modal-btn modal-btn-green">Bijwerken</button>
                        <button type="button" onclick="closeModal('editoptiongroup-modal')" class="modal-btn modal-btn-gray">Annuleren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 4: Edit optie -->
<div id="editoption-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box">
            <div class="modal-box-inner">
                <div class="modal-header">
                    <h2 id="editoption-modal-title" class="modal-title">Optie bewerken</h2>
                    <button onclick="closeModal('editoption-modal')" class="modal-close">
                        <svg class="icon icon--close" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="editoption-form" method="post" action="/admin/opties" class="modal-form">
                    <input type="hidden" name="update_option" value="1">
                    <input type="hidden" name="group_id" id="editoption_group_id">
                    <input type="hidden" name="option_id" id="editoption_id">

                    <div class="modal-field">
                        <label for="editoption_name_nl">Optie (NL):</label>
                        <input type="text" name="option_name_nl" id="editoption_name_nl" required>
                    </div>

                    <?php if (in_array('fr', $talen)): ?>
                        <div class="modal-field">
                            <label for="editoption_name_fr">Optie (FR):</label>
                            <div class="modal-row">
                                <input type="text" name="option_name_fr" id="editoption_name_fr">
                                <a href="#" id="editoption-translate-fr-btn" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">
                                    vertaal
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('en', $talen)): ?>
                        <div class="modal-field">
                            <label for="editoption_name_en">Optie (EN):</label>
                            <div class="modal-row">
                                <input type="text" name="option_name_en" id="editoption_name_en">
                                <a href="#" id="editoption-translate-en-btn" style="text-decoration:underline; font-size:0.95em; margin-left:8px; cursor:pointer;">
                                    vertaal
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="modal-field">
                        <label for="editoption_price_delta">Meerprijs:</label>
                        <input type="number" step="0.01" name="price_delta" id="editoption_price_delta" value="0">
                    </div>

                    <div class="modal-actions">
                        <button type="submit" id="editoption-submit-btn" class="modal-btn modal-btn-green">Bijwerken</button>
                        <button type="button" onclick="closeModal('editoption-modal')" class="modal-btn modal-btn-gray">Annuleren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>