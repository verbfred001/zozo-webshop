// Globale variabelen
let currentProduct = null;
let currentOptions = [];
let currentBasePrice = 0;
// voorkom duplicate-declare errors door op window te zetten als het nog niet bestaat
if (typeof window.voorraadbeheerActief === 'undefined') {
    window.voorraadbeheerActief = false;
}

document.addEventListener('DOMContentLoaded', function() {
    // Laad product-opties direct in de detail-pagina
    loadProductOptions();
    
    // Update cart badge
    updateCartBadge && updateCartBadge();
    
    // Thumbnail click handlers
    setupImageHandlers();
    
    // Set localized label and ensure icon color for detail add button
    try {
        const detailAddBtn = document.getElementById('detail-add-btn');
        if (detailAddBtn) {
            // set label text from translations if available
            const label = window.translations && (window.translations.in_mijn_winkelwagen || window.translations.toevoegen) ? (window.translations.in_mijn_winkelwagen || window.translations.toevoegen) : detailAddBtn.querySelector('.detail-add-label')?.textContent || detailAddBtn.textContent;
            const labelEl = detailAddBtn.querySelector('.detail-add-label');
            if (labelEl) labelEl.textContent = label;
            // ensure svg uses currentColor for stroke
            const svg = detailAddBtn.querySelector('svg');
            if (svg) {
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('fill', 'none');
                svg.style.verticalAlign = 'middle';
            }
        }
    } catch (e) { /* ignore */ }
});

// Laad product-opties
function loadProductOptions() {
    let lang = window.currentLang || 'nl';
    fetch('/zozo-includes/get_product_options.php?id=' + productId + '&lang=' + lang)
        .then(r => r.json())
        .then(data => {
            currentProduct = data.product;
            currentOptions = data.options;
            currentBasePrice = basePrice;
            // zet de window-property (geen nieuwe let declaratie)
            window.voorraadbeheerActief = data.voorraadbeheer == 1;

            // Render opties in detail-container
            renderProductOptions(data.options);
            updateDetailPrice();
            setupDetailForm();
        })
        .catch(err => {
            console.error('Fout bij laden opties:', err);
        });
}
function renderProductOptions(options) {
    const container = document.getElementById('detail-options-container');
    if (!container) return;
    let html = '';
    options.forEach(opt => {
        // include data-group-id so we can reliably target this group when validating
        html += `<div class="detail-option-group" data-group-id="${opt.group_id}" data-group-name-nl="${opt.group_name_nl}">`;
        html += `<label>${opt.group_name}${opt.is_required == 1 ? ' <span style="color:#1f2937;">*</span>' : ''}</label>`;

        if (opt.type === 'select') {
            html += `<select name="option_${opt.group_id}" data-group-id="${opt.group_id}">`;
            html += `<option value="">${window.translations.kies}</option>`;
                opt.options.forEach(o => {
                let delta = Number(o.price_delta) || 0;
                let deltaText = delta > 0 ? ` (+${window.formatPriceNoSymbol(delta)})` : '';
                html += `<option value="${o.option_id}" data-price="${delta}" data-affects-stock="${o.affects_stock || 0}" data-label="${(o.label||o.option_name||'').replace(/"/g,'&quot;')}">${o.label || o.option_name}${deltaText}</option>`;
            });
            html += `</select>`;
        }
        else if (opt.type === 'radio') {
            html += `<div class="detail-radio-group">`;
                opt.options.forEach((o, idx) => {
                let delta = Number(o.price_delta) || 0;
                let deltaText = delta > 0 ? ` (+${window.formatPriceNoSymbol(delta)})` : '';
                // default the first radio to checked so a radio group has a value by default
                const checkedAttr = idx === 0 ? ' checked' : '';
                html += `
<label class="detail-radio-label">
    <input type="radio" name="option_${opt.group_id}" value="${o.option_id}" data-price="${delta}" data-group-id="${opt.group_id}" data-affects-stock="${o.affects_stock || 0}" data-label="${(o.label||o.option_name||'').replace(/"/g,'&quot;')}"${checkedAttr}>
    ${o.label || o.option_name}${deltaText}
</label>
`;
            });
            html += `</div>`;
        }
        else if (opt.type === 'checkbox') {
            html += `<div class="detail-checkbox-group">`;
                opt.options.forEach((o, idx) => {
                let delta = Number(o.price_delta) || 0;
                let deltaText = delta > 0 ? ` (+${window.formatPriceNoSymbol(delta)})` : '';
                html += `
<label class="detail-checkbox-label">
    <input type="checkbox" name="option_${opt.group_id}" value="${o.option_id}" data-price="${delta}" data-group-id="${opt.group_id}" data-label="${(o.label||o.option_name||'').replace(/"/g,'&quot;')}">
    ${o.label || o.option_name}${deltaText}
</label>
`;
            });
            html += `</div>`;
        }
        else if (opt.type === 'text') {
            html += `<input type="text" name="option_${opt.group_id}" placeholder="${window.translations.vulin}" data-group-id="${opt.group_id}">`;
        }
        else if (opt.type === 'textarea') {
            html += `<textarea name="option_${opt.group_id}" placeholder="${window.translations.vulin}" data-group-id="${opt.group_id}"></textarea>`;
        }

        html += `</div>`;
    });
    
    container.innerHTML = html;
    // Initialize option-rule overrides for the detail page if the trigger exists.
    try {
        if (typeof window.initOptionRuleOverrides === 'function') {
            // look for trigger inside the just-rendered container (supports select or inputs)
            const triggerSelector = `select[name="option_58"], select[data-group-id="58"], input[name="option_58"], input[data-group-id="58"]`;
            const found = container.querySelector(triggerSelector);
            if (found) {
                window.initOptionRuleOverrides({ root: container, triggerGroupId: 58, triggerValues: ['180','209'], targetGroupIds: ['59','60','61'], sentinelValue: '0' });
            } else {
                // nothing to do for this product
                console.debug && console.debug('initOptionRuleOverrides: trigger not present in detail options for this product');
            }
        }
    } catch (e) { if (window.VOORRAAD_DEBUG) console.warn('detail initOptionRuleOverrides failed', e); }
    // Apply default green border to required fields so users see they're required
    try {
        options.forEach(opt => {
            if (opt.is_required == 1) {
                const groupEl = container.querySelector(`.detail-option-group[data-group-id="${opt.group_id}"]`);
                if (groupEl) {
                    groupEl.querySelectorAll('input,select,textarea').forEach(el => {
                        try {
                            el.style.border = '1px solid #10b981';
                            el.style.boxShadow = '0 0 0 3px rgba(16,185,129,0.06)';
                            el.classList && el.classList.add('input-required');
                            // Mirror to custom display if select is replaced
                            try {
                                if (el.tagName && el.tagName.toLowerCase() === 'select') {
                                    const wrapper = el.nextElementSibling && el.nextElementSibling.classList && el.nextElementSibling.classList.contains('custom-select-wrapper') ? el.nextElementSibling : null;
                                    if (wrapper) {
                                        const disp = wrapper.querySelector('.custom-select-display');
                                        if (disp) {
                                            disp.style.border = '1px solid #10b981';
                                            disp.style.boxShadow = '0 0 0 3px rgba(16,185,129,0.06)';
                                            disp.classList && disp.classList.add('input-required');
                                        }
                                    }
                                }
                            } catch(e){}
                        } catch(e){}
                    });
                }
            }
        });
    } catch(e) { if (window.VOORRAAD_DEBUG) console.warn('failed to apply default required styling (detail)', e); }

    // toon hint voor verplichte velden (zoals in modal) — we keep color out of inline HTML and let CSS override
    const hasRequired = options.some(opt => opt.is_required == 1);
    const requiredHintEl = document.getElementById('detail-required-hint');
    if (requiredHintEl) {
        requiredHintEl.innerHTML = hasRequired ? `<span style="font-size:0.85em;">(* = ${window.translations.verplichte_velden || 'verplichte velden'})</span>` : '';
    }

    // gebruik gedebounce versie om snelle opeenvolgende events te beperken
    // Replace detail selects with the same lightweight custom dropdown used in the modal
    try {
        const selectsToReplace = container.querySelectorAll('select');
        selectsToReplace.forEach(orig => {
            if (orig.closest('.custom-select-wrapper')) return;
            const wrapper = document.createElement('div');
            wrapper.className = 'custom-select-wrapper';
            const display = document.createElement('div');
            display.className = 'custom-select-display';
            display.tabIndex = 0;
            display.textContent = orig.selectedOptions[0]?.text || (orig.querySelector('option')?.text || (window.translations?.kies || 'Kies'));
            const list = document.createElement('div');
            list.className = 'custom-select-list';
            list.style.display = 'none';
            Array.from(orig.options).forEach((opt, idx) => {
                const item = document.createElement('div');
                item.className = 'custom-select-item';
                if (opt.disabled) item.setAttribute('aria-disabled', 'true');
                item.dataset.value = opt.value;
                item.innerHTML = opt.text;
                if (orig.selectedIndex === idx) item.classList.add('selected');
                item.addEventListener('click', function() {
                    orig.value = item.dataset.value;
                    display.textContent = item.textContent;
                    list.querySelectorAll('.custom-select-item').forEach(i => i.classList.remove('selected'));
                    item.classList.add('selected');
                    list.style.display = 'none';
                    orig.dispatchEvent(new Event('change', { bubbles: true }));
                });
                list.appendChild(item);
            });
            display.addEventListener('click', function(e) {
                e.stopPropagation();
                const visible = list.style.display !== 'none';
                document.querySelectorAll('.custom-select-list').forEach(l => l.style.display = 'none');
                if (visible) {
                    list.style.display = 'none';
                } else {
                    try {
                        const dispRect = display.getBoundingClientRect();
                        const desired = Math.round(dispRect.width);
                        list.style.width = desired + 'px';
                        list.style.maxWidth = desired + 'px';
                        list.style.left = '0px';
                    } catch (err) {
                        list.style.width = '';
                        list.style.maxWidth = '';
                        list.style.left = '';
                    }
                    list.style.display = 'block';
                }
            });
            display.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    display.click();
                }
            });
            document.addEventListener('click', function() { list.style.display = 'none'; });
            orig.style.position = 'absolute';
            orig.style.left = '-9999px';
            orig.style.width = '1px';
            orig.style.height = '1px';
            orig.style.overflow = 'hidden';
            wrapper.appendChild(display);
            wrapper.appendChild(list);
            orig.parentNode.insertBefore(wrapper, orig.nextSibling);
        });
    } catch (e) { if (window.VOORRAAD_DEBUG) console.warn('detail custom select replace failed', e); }

    const debouncedUpdate = debounce(updateDetailPrice, 150);
    container.addEventListener('change', function(e) {
        try {
            const group = e.target.closest('.detail-option-group');
            if (!group) { debouncedUpdate(); return; }
            const gid = group.getAttribute('data-group-id');
            const optDef = (currentOptions || []).find(o => String(o.group_id) === String(gid));

            // Restore only the changed control
            const ctrl = e.target && e.target.closest ? e.target.closest('input,select,textarea') : null;
            const target = ctrl || group.querySelector('input,select,textarea');
            if (!target) { debouncedUpdate(); return; }

            if (optDef && optDef.is_required == 1) {
                try {
                    target.classList.remove('input-invalid','invalid-field');
                    target.classList.add('input-required');
                    // mirror to display if select
                    if (target.tagName && target.tagName.toLowerCase() === 'select') {
                        const wrapper = target.nextElementSibling && target.nextElementSibling.classList && target.nextElementSibling.classList.contains('custom-select-wrapper') ? target.nextElementSibling : null;
                        if (wrapper) {
                            const disp = wrapper.querySelector('.custom-select-display');
                            if (disp) {
                                disp.classList.remove('input-invalid','invalid-field');
                                disp.classList.add('input-required');
                                disp.style.border = '1px solid #10b981';
                                disp.style.boxShadow = '0 0 0 3px rgba(16,185,129,0.06)';
                                disp.style.color = '';
                            }
                        }
                    }
                } catch(e){}
            } else {
                try {
                    target.classList.remove('input-required','input-invalid','invalid-field');
                    if (target.tagName && target.tagName.toLowerCase() === 'select') {
                        const wrapper = target.nextElementSibling && target.nextElementSibling.classList && target.nextElementSibling.classList.contains('custom-select-wrapper') ? target.nextElementSibling : null;
                        if (wrapper) {
                            const disp = wrapper.querySelector('.custom-select-display');
                            if (disp) {
                                disp.style.border = '';
                                disp.style.boxShadow = '';
                                disp.style.color = '';
                                disp.classList.remove('input-required','input-invalid','invalid-field');
                            }
                        }
                    }
                } catch(e){}
            }
        } catch(e){}
        debouncedUpdate();
    });
    // Avoid listening to all input events inside the options container (typing in textarea/text
    // would otherwise trigger price/stock checks on every keystroke). Instead, only observe
    // the quantity input for 'input' events and let 'change' handle selects/radios/checkboxes.
    const qtyInput = document.getElementById('detail-qty');
    if (qtyInput) {
        qtyInput.addEventListener('input', debouncedUpdate);
    }
}

// Update prijs op detailpagina
function updateDetailPrice() {
    let price = Number(currentBasePrice) || 0;
    document.querySelectorAll('#detail-options-container select').forEach(sel => {
        let delta = parseFloat(sel.selectedOptions[0]?.dataset.price || 0);
        price += delta;
    });
    document.querySelectorAll('#detail-options-container input[type="radio"]:checked').forEach(radio => {
        let delta = parseFloat(radio.dataset.price || 0);
        price += delta;
    });
    document.querySelectorAll('#detail-options-container input[type="checkbox"]:checked').forEach(cb => {
        let delta = parseFloat(cb.dataset.price || 0);
        price += delta;
    });
    const priceEl = document.getElementById('detail-final-price');
    if (priceEl) {
        if (Number(price) === 0) {
            priceEl.textContent = window.translations.op_maat || 'op maat';
            priceEl.classList.add('detail-price--opmaat');
        } else {
            priceEl.textContent = window.formatPriceNoSymbol(price);
            priceEl.classList.remove('detail-price--opmaat');
        }
    }
    const selectedOptions = getSelectedDetailOptions();
    checkDetailStock(selectedOptions);
    return price;
}

// Haal geselecteerde opties op van detailpagina
function getSelectedDetailOptions() {
    const container = document.getElementById('detail-options-container');
    if (!container) return [];
    const selectedOptions = [];
    // Select
    container.querySelectorAll('select').forEach(sel => {
        if (sel.value) {
            const opt = sel.options[sel.selectedIndex];
            selectedOptions.push({
                group_id: sel.dataset.groupId,
                group_name: sel.closest('.detail-option-group').querySelector('label').textContent.replace(/\s*\*.*$/, '').trim(),
                group_name_nl: opt.closest('.detail-option-group').dataset.groupNameNl || '', // voeg deze regel toe
                type: 'select',
                option_id: sel.value,
                label: opt.dataset.label || opt.textContent,
                price_delta: Number(opt.dataset.price) || 0,
                affects_stock: opt.dataset.affectsStock || 0
            });
        }
    });
    // Radio
    container.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
        selectedOptions.push({
            group_id: radio.dataset.groupId,
            group_name: radio.closest('.detail-option-group').querySelector('label').textContent.replace(/\s*\*.*$/, '').trim(),
            group_name_nl: radio.closest('.detail-option-group').dataset.groupNameNl || '', // voeg deze regel toe
            type: 'radio',
            option_id: radio.value,
            label: radio.dataset.label || radio.parentElement.textContent.trim(),
            price_delta: Number(radio.dataset.price) || 0,
            affects_stock: radio.dataset.affectsStock || 0
        });
    });
    // Checkbox
    container.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
        selectedOptions.push({
            group_id: cb.dataset.groupId,
            group_name: cb.closest('.detail-option-group').querySelector('label').textContent.replace(/\s*\*.*$/, '').trim(),
            group_name_nl: cb.closest('.detail-option-group').dataset.groupNameNl || '', // voeg deze regel toe
            type: 'checkbox',
            option_id: cb.value,
            label: cb.dataset.label || cb.parentElement.textContent.trim(),
            price_delta: Number(cb.dataset.price) || 0,
            affects_stock: cb.dataset.affectsStock || 0
        });
    });
    // Text/textarea
    container.querySelectorAll('input[type="text"], textarea').forEach(input => {
        if (input.value) {
            selectedOptions.push({
                group_id: input.dataset.groupId,
                group_name: input.closest('.detail-option-group').querySelector('label').textContent.replace(/\s*\*.*$/, '').trim(),
                type: input.type,
                value: input.value,
                label: input.value,
                price_delta: 0,
                affects_stock: 0
            });
        }
    });
    return selectedOptions;
}

// Check voorraad voor detailpagina
function checkDetailStock(selectedOptions) {
    if (!window.voorraadbeheerActief) {
        return;
    }

    // geselecteerde opties die stock beïnvloeden
    const stockOptions = selectedOptions.filter(opt => Number(opt.affects_stock) === 1);

    // bepaal hoeveel groepen in totaal stock beïnvloeden op deze productpagina
    const expectedStockGroups = (currentOptions || []).filter(g =>
        Array.isArray(g.options) && g.options.some(o => Number(o.affects_stock) === 1)
    ).length;

    // Als er stock‑groepen zijn, wacht tot ze allemaal gekozen zijn.
    if (expectedStockGroups > 0 && stockOptions.length < expectedStockGroups) {
        const stockInfo = document.getElementById('detail-stock-info');
        if (stockInfo) {
            stockInfo.style.display = 'none';
            stockInfo.innerHTML = '';
        }
        const addBtn = document.getElementById('detail-add-btn');
        if (addBtn) addBtn.disabled = false; // of true afhankelijk gewenste UX
        return;
    }

    // Wanneer er géén stock‑groepen zijn, gebruik direct de product-level voorraad
    if (expectedStockGroups === 0) {
        try {
            const qtyInput = document.getElementById('detail-qty');
            const stockInfo = document.getElementById('detail-stock-info');
            const addBtn = document.getElementById('detail-add-btn');
            const productStock = Number(currentProduct?.art_aantal ?? currentProduct?.art_aantal ?? 0) || 0;
            const verkrijgbaarFlag = 1; // wanneer voorraad op products niveau staat gaan we ervan uit dat het verkrijgbaar is

            // bereken al in winkelwagen aanwezige hoeveelheid van dit product (zonder opties)
            const alreadyInCart = getCartQty(productId, []);
            const maxQty = Math.max(productStock - alreadyInCart, 0);

            // Pas qty attribuut aan volgens voorraadbeheer
            if (typeof window.voorraadbeheerActief !== 'undefined' && !window.voorraadbeheerActief) {
                try { qtyInput.removeAttribute('max'); } catch(e) { qtyInput.max = ''; }
            } else {
                if (maxQty === 0 && Number(currentProduct?.art_levertijd || 0) > 0) {
                    try { qtyInput.removeAttribute('max'); } catch(e) { qtyInput.max = ''; }
                } else {
                    qtyInput.max = maxQty;
                }
            }

            // Zorg dat qty waarde geldig is
            let cur = parseInt(qtyInput.value) || 1;
            try {
                if (qtyInput.hasAttribute && qtyInput.hasAttribute('max') && qtyInput.max !== '') {
                    const m = parseInt(qtyInput.max) || 0;
                    if (cur > m) cur = m;
                }
            } catch(e){}
            if (cur < 1) cur = 1;
            qtyInput.value = cur;

            // Render stock/leverinfo met gedeelde helper
            if (typeof window.renderStockOrLevertijd === 'function') {
                window.renderStockOrLevertijd({
                    targetEl: stockInfo,
                    maxQty: maxQty,
                    verkrijgbaarFlag: verkrijgbaarFlag,
                    productId: productId,
                    productData: currentProduct || {},
                    addButtonId: 'detail-add-btn'
                });
                if (stockInfo) {
                    if ((stockInfo.innerHTML || '').trim() !== '') {
                        stockInfo.style.display = 'block';
                    } else {
                        stockInfo.style.display = 'none';
                    }
                }
                try {
                    stockInfo.setAttribute('data-stock-source', 'product');
                } catch(e){}
            }
        } catch(e) {
            console.error('Fout bij direct gebruiken van product.art_aantal', e);
        }
        return;
    }

    // bouw optiestring en ga verder met exacte één check
    const optiesString = stockOptions
        .map(opt => (opt.group_name_nl || opt.group_name) + ':' + (opt.option_id || opt.value))
        .sort()
        .join('|');

    const qtyInput = document.getElementById('detail-qty');
    const stockInfo = document.getElementById('detail-stock-info');
    const addBtn = document.getElementById('detail-add-btn');

    // Gebruik dezelfde gedeelde helper als op productpagina
    checkStock(productId, optiesString, function(result) {
        // result kan object zijn {voorraad, verkrijgbaar}
        const voorraad = parseInt(result.voorraad || 0, 10) || 0;
        const verkrijgbaarFlag = Number(result.verkrijgbaar ?? 1) === 1;

        const alreadyInCart = getCartQty(productId, stockOptions);
        const maxQty = Math.max(voorraad - alreadyInCart, 0);

        // Zorg dat stockInfo element leeg staat
        if (stockInfo) stockInfo.innerHTML = '';

        // Hergebruik renderStockOrLevertijd voor consistente UX
        if (typeof window.renderStockOrLevertijd === 'function') {
            window.renderStockOrLevertijd({
                targetEl: stockInfo,
                maxQty: maxQty,
                verkrijgbaarFlag: verkrijgbaarFlag,
                productId: productId,
                productData: currentProduct || {},
                addButtonId: 'detail-add-btn'
            });
            // Toon of verberg het stockInfo element afhankelijk van inhoud
            try {
                if (stockInfo) {
                    if ((stockInfo.innerHTML || '').trim() !== '') {
                        stockInfo.style.display = 'block';
                    } else {
                        stockInfo.style.display = 'none';
                    }
                }
            } catch (e) { /* ignore */ }
            // indien voorraadbeheer uit staat, renderStockOrLevertijd zorgt dat addBtn enabled is
            // Pas qty input max waarde aan (of verwijder) consistent met productpagina logic
            if (typeof window.voorraadbeheerActief !== 'undefined' && !window.voorraadbeheerActief) {
                try { qtyInput.removeAttribute('max'); } catch(e) { qtyInput.max = ''; }
            } else {
                if (maxQty === 0 && Number(currentProduct?.art_levertijd || 0) > 0) {
                    try { qtyInput.removeAttribute('max'); } catch(e) { qtyInput.max = ''; }
                } else {
                    qtyInput.max = maxQty;
                }
            }
            // ensure qty value valid
            let cur = parseInt(qtyInput.value) || 1;
            try {
                if (qtyInput.hasAttribute && qtyInput.hasAttribute('max') && qtyInput.max !== '') {
                    const m = parseInt(qtyInput.max) || 0;
                    if (cur > m) cur = m;
                }
            } catch(e){}
            if (cur < 1) cur = 1;
            qtyInput.value = cur;
            try {
                if (stockInfo) stockInfo.setAttribute('data-stock-source', 'options');
            } catch(e){}
        }
    });
}

// Setup form handler voor detailpagina
function setupDetailForm() {
    const form = document.getElementById('detail-options-form');
    if (!form) return;

    form.onsubmit = function(e) {
        e.preventDefault();
        
        const qty = parseInt(document.getElementById('detail-qty').value) || 1;
        const selectedOptions = getSelectedDetailOptions();
        const errorMsg = document.getElementById('detail-error-msg');
        
        // Reset error
        if (errorMsg) {
            errorMsg.style.display = 'none';
            errorMsg.textContent = '';
        }
        
        // Validatie van verplichte opties
        // consider overrides: if a required group is hidden by an override we treat it as satisfied
        const requiredGroups = currentOptions.filter(og => og.is_required == 1);
        const missingRequired = requiredGroups.filter(rg => {
            try {
                const wrapper = document.querySelector(`.detail-option-group[data-group-id="${rg.group_id}"]`);
                const hiddenByOverride = wrapper && (wrapper.dataset._overrideHidden === '1' || wrapper.getAttribute('data-override-hidden') === '1');
                if (hiddenByOverride) return false;
            } catch(e) {}
            return !selectedOptions.some(so => String(so.group_id) === String(rg.group_id));
        });
        
        if (missingRequired.length > 0) {
                // Markeer ontbrekende groepen met rode border + subtle shadow (zoals checkout)
                try {
                    missingRequired.forEach(rg => {
                        const group = document.querySelector(`.detail-option-group[data-group-id="${rg.group_id}"]`);
                        if (group) {
                            group.querySelectorAll('input,select,textarea').forEach(el => {
                                try {
                                    el.style.border = '1px solid #e74c3c';
                                    el.style.boxShadow = '0 0 0 3px rgba(231,76,60,0.08)';
                                    // remove green marker and add invalid class so CSS rules with !important apply
                                    try { el.classList && el.classList.remove('input-required'); } catch(e){}
                                    try { el.classList && el.classList.add('input-invalid'); } catch(e){}
                                    el.classList && el.classList.add('invalid-field');
                                    // if this is a replaced <select>, mirror the style on the visible display
                                    try {
                                        if (el.tagName && el.tagName.toLowerCase() === 'select') {
                                            const wrapper = el.nextElementSibling && el.nextElementSibling.classList && el.nextElementSibling.classList.contains('custom-select-wrapper') ? el.nextElementSibling : null;
                                            if (wrapper) {
                                                const disp = wrapper.querySelector('.custom-select-display');
                                                    if (disp) {
                                                        disp.style.border = '1px solid #e74c3c';
                                                        disp.style.boxShadow = '0 0 0 3px rgba(231,76,60,0.08)';
                                                        try { disp.classList && disp.classList.remove('input-required'); } catch(e){}
                                                        try { disp.classList && disp.classList.add('input-invalid'); } catch(e){}
                                                        disp.classList && disp.classList.add('invalid-field');
                                                    }
                                            }
                                        }
                                    } catch(e){}
                                } catch(e){}
                            });
                        }
                    });
                    // focus first invalid element
                    const firstInvalid = document.querySelector('.detail-option-group input[style*="#e74c3c"], .detail-option-group select[style*="#e74c3c"], .detail-option-group textarea[style*="#e74c3c"]');
                    if (firstInvalid) {
                        try { firstInvalid.focus(); firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch(e){}
                    }
                } catch(e){}
                // geen persistente message — consistent met modal/checkout
            return;
        }
        
        // Voorraadcontrole
        const stockOptions = selectedOptions.filter(opt => opt.affects_stock == 1);
        if (stockOptions.length > 0) {
            const optiesString = stockOptions
                .map(opt => (opt.group_name_nl || opt.group_name) + ':' + (opt.option_id || opt.value))
                .sort()
                .join('|');
            
            checkStock(productId, optiesString, function(voorraad) {
                const cartQty = getCartQty(productId, stockOptions);
                const beschikbaar = voorraad - cartQty;
                
                if (qty > beschikbaar) {
                    if (errorMsg) {
                        errorMsg.textContent = `Niet genoeg voorraad. Maximaal ${beschikbaar} beschikbaar.`;
                        errorMsg.style.display = 'block';
                    }
                    return;
                }
                
                // Voeg toe aan cart
                addToDetailCart(qty, selectedOptions);
            });
        } else {
            // Geen voorraadcontrole nodig
            addToDetailCart(qty, selectedOptions);
        }
    };

}

// Voeg product toe aan cart vanuit detailpagina
function addToDetailCart(qty, selectedOptions) {
    let price = updateDetailPrice();
    // Bouw samengestelde naam. Voeg art_kenmerk (indien aanwezig in currentProduct) direct na de productnaam toe.
    let fullName = productName;
    try {
        const k = (currentProduct && currentProduct.kenmerk) ? String(currentProduct.kenmerk).trim() : '';
        if (k) fullName += ' - ' + k;
    } catch (e) { /* ignore */ }
    if (selectedOptions.length > 0) {
        let optStr = selectedOptions.map(opt => {
            let label = (opt.label || '').trim();
            return label;
        }).join(' - ');
        // Gebruik ' > ' tussen basisnaam (naam [+ kenmerk]) en de opties
        fullName += ' > ' + optStr;
    }
        let cartItem = {
        product_id: productId,
        name: fullName,
        category: productCategory,
        options: selectedOptions,
        price: price,
        btw: window.productBTW || window.product_BTW || (typeof productBTW !== 'undefined' ? productBTW : 21),
        qty: qty
    };
        // trigger shared flying-dot animation if available
        try {
            if (typeof window.animateCartFly === 'function' && fromButton) {
                window.animateCartFly(fromButton);
            }
        } catch (e) { /* ignore */ }
    // Indien de stockInfo element bevat een berekende expected date -> voeg toe aan cartItem
    try {
        const stockInfo = document.getElementById('detail-stock-info');
        const expected = stockInfo ? stockInfo.getAttribute('data-expected-date') : null;
        if (expected) {
            const parts = expected.split('-');
            if (parts.length === 3) {
                cartItem.name = cartItem.name + ' > vanaf ' + parts[2].padStart(2,'0') + '/' + parts[1].padStart(2,'0') + '/' + parts[0];
                cartItem.expected_date = expected;
                cartItem.backorder = 1;
            }
        }
    } catch(e) { /* silent */ }
    let cart = JSON.parse(localStorage.getItem('cart') || '[]');
    let found = cart.find(item =>
        item.product_id === cartItem.product_id &&
        item.category === cartItem.category &&
        item.name === cartItem.name
    );
    if (found) {
        found.qty += cartItem.qty;
    } else {
        cart.push(cartItem);
    }
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartBadge && updateCartBadge();
    // Feedback
    const addBtn = document.getElementById('detail-add-btn');
    if (addBtn) {
        const originalText = addBtn.textContent;
        addBtn.textContent = 'Toegevoegd!';
        addBtn.style.background = '#28a745';
        setTimeout(() => {
            addBtn.textContent = originalText;
            addBtn.style.background = '';
        }, 2000);
    }
}

// debounce helper
function debounce(fn, wait) {
    let t = null;
    return function(...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), wait);
    };
}

// Setup image handlers
function setupImageHandlers() {
    document.querySelectorAll('.detail-thumbs img').forEach(thumb => {
        thumb.addEventListener('click', function() {
            const idx = this.dataset.idx;
            const mainImg = document.getElementById('main-image');
            if (mainImg) {
                mainImg.src = this.src;
                
                // Update active state
                document.querySelectorAll('.detail-thumbs img').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });
}
