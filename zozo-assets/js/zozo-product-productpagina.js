//console.log('checkStock geladen uit:', document.currentScript.src);


// Prijsberekening op basis van geselecteerde opties
function updateModalPrice(basePrice = 0, translations = window.translations) {
    let price = basePrice;
    document.querySelectorAll('#modal-options-fields select').forEach(sel => {
        let delta = parseFloat(sel.selectedOptions[0]?.dataset.delta || 0);
        price += delta;
    });
    document.querySelectorAll('#modal-options-fields input[type="radio"]:checked').forEach(radio => {
        let delta = parseFloat(radio.dataset.delta || 0);
        price += delta;
    });
    document.querySelectorAll('#modal-options-fields input[type="checkbox"]:checked').forEach(cb => {
        let delta = parseFloat(cb.dataset.delta || 0);
        price += delta;
    });
    if (document.getElementById('modal-final-price')) {
        const priceEl = document.getElementById('modal-final-price');
        priceEl.textContent = (translations?.eenheidsprijs || 'Eenheidsprijs:') + ' ' + window.formatPriceNoSymbol(price);
        try { priceEl.classList.remove('modal-price--opmaat'); } catch(e){}
    }
    return price;
}

function clearProductModalState() {
    const modal = document.getElementById('product-options-modal');
    if (!modal) return;
    // clear textual containers
    const stockEl = modal.querySelector('#modal-stock-info') || modal.querySelector('.modal-stock-info');
    if (stockEl) stockEl.innerHTML = '';
    // reset form(s)
    modal.querySelectorAll('form').forEach(f => { try { f.reset(); } catch(e){} });
    // clear inputs/selects (defensive)
    modal.querySelectorAll('input,textarea,select').forEach(el => {
        try {
            if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
            else if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else el.value = '';
        } catch(e){}
    });
    // remove data attributes used for temp state
    modal.removeAttribute('data-art-id');
    modal.removeAttribute('data-art-levertijd');
    // clear any previous required/invalid classes left from previous interactions
    modal.querySelectorAll('.modal-option-group').forEach(group => {
        try {
            group.classList.remove('modal-option-required', 'modal-option-invalid');
            // also remove classes from direct input/select/textarea children
            group.querySelectorAll('input,select,textarea').forEach(el => el.classList.remove('input-required', 'input-invalid'));
            // for label wrappers, remove inline padding/border that might have been set earlier
            group.querySelectorAll('label').forEach(lbl => {
                lbl.classList.remove('modal-option-required', 'modal-option-invalid');
                // remove any inline padding/borderRadius left from earlier code
                try { lbl.style.padding = ''; lbl.style.borderRadius = ''; } catch(e){}
            });
        } catch(e){}
    });
    // ensure add button disabled until stock evaluated
    const addBtn = modal.querySelector('#modal-add-btn') || modal.querySelector('.modal-add-button');
    if (addBtn) {
        if (typeof window.voorraadbeheerActief !== 'undefined' && !window.voorraadbeheerActief) {
            addBtn.disabled = false;
        } else {
            addBtn.disabled = true;
        }
    }
}

// Animatie van knop naar winkelwagen
function animateToCart(fromBtn) {
    let cartIcon = document.querySelector('.cart-btn');
        // prefer shared helper if present
        if (typeof window.animateCartFly === 'function') {
            try { window.animateCartFly(fromBtn); return; } catch(e) {}
        }
        // fallback local animation (keeps previous behavior)
        if (!cartIcon || !fromBtn) return;

    let startRect = fromBtn.getBoundingClientRect();
    let endRect = cartIcon.getBoundingClientRect();

    let ball = document.createElement('div');
    ball.style.position = 'fixed';
    ball.style.left = (startRect.left + startRect.width / 2 - 10) + 'px';
    ball.style.top = (startRect.top + startRect.height / 2 - 10) + 'px';
    ball.style.width = '18px';
    ball.style.height = '18px';
    ball.style.background = '#22c55e'; // green
    ball.style.borderRadius = '50%';
    ball.style.zIndex = 15000;
    // slower, smooth easing
    ball.style.transition = 'all 1.2s cubic-bezier(.2,.8,.2,1)';
    ball.style.pointerEvents = 'none';
    document.body.appendChild(ball);

    // force layout
    ball.offsetWidth;

    // move to the center of the cart icon
    ball.style.left = (endRect.left + endRect.width / 2 - 9) + 'px';
    ball.style.top = (endRect.top + endRect.height / 2 - 9) + 'px';
    ball.style.opacity = '0.95';
    ball.style.transform = 'scale(0.6)';

    // After animation ends, remove the ball and pulse the badge green using the existing class
    setTimeout(() => {
        try { ball.remove(); } catch(e){}
        let badge = document.getElementById('cart-badge');
        if (badge) {
            // use existing class which sets green background and pulse in main.css
            badge.classList.add('cart-badge--added');
            // remove the green pulse after a longer time so it's visible
            setTimeout(() => badge.classList.remove('cart-badge--added'), 1200);
        }
    }, 1200);
}

updateCartBadge();

//console.log('checkStock geladen uit:', document.currentScript.src);

// Globale variabelen
let currentProduct = null;
let currentOptions = [];
let currentBasePrice = 0;
let currentBtw = 21;
let modalProductData = null;

// voorkom impliciete global
if (typeof window.voorraadbeheerActief === 'undefined') window.voorraadbeheerActief = false;

// Voeg deze helper toe bovenaan je bestand (na de globale variabelen):
function filterRequiredStockOptions(options) {
    return options
        .filter(o => o.affects_stock == 1 && o.is_required == 1)
        .map(o => String(o.option_id || o.value))
        .sort(); // sort voor consistente vergelijking
}

// nieuw: helper om consistent DB-key te bouwen (gebruik voorkeur voor niet-gelocaliseerde naam)
function buildOptiesStringForDb(selectedOptions) {
    return selectedOptions
        .filter(o => Number(o.affects_stock) === 1)
        .map(o => {
            // gebruik eerst de niet-gelokaliseerde NL naam (stabiel), fallback op group_name of id
            const groupKey = o.group_name_nl || o.group_name || ('g' + (o.group_id || ''));
            const val = o.option_id || o.value || '';
            return String(groupKey) + ':' + String(val);
        })
        .sort()
        .join('|');
}

// Modal openen en vullen
function openProductModal(productId, categoryName) {
    // clear any previous state first
    clearProductModalState();
    let lang = window.currentLang || 'nl';
    fetch('/zozo-includes/get_product_options.php?id=' + productId + '&lang=' + lang)
        .then(r => r.json())
        .then(data => {
            modalProductData = data;
            currentProduct = data.product;
            currentProduct.category = categoryName;

            currentOptions = data.options;
            currentBasePrice = parseFloat(data.product.price_incl);
            currentBtw = parseInt(data.product.btw);
            // Zorg dat we de globale setting updaten zodat andere scripts (shared helpers) deze lezen
            window.voorraadbeheerActief = data.voorraadbeheer == 1;

            document.getElementById('modal-product-title').textContent = data.product.name;
            document.getElementById('modal-qty').value = 1;

            // Opties tonen
            let html = '';
            data.options.forEach(opt => {
                // include data-group-id so we can reliably find the group element later
                html += `<div class="modal-option-group" data-group-id="${opt.group_id}">`;
                html += `<label>${opt.group_name}${(opt.is_required == 1 && opt.type !== 'checkbox') ? ' <span style="color:#1f2937;">*</span>' : ''}</label>`;

                    if (opt.type === 'radio') {
                    opt.options.forEach((o, idx) => {
                        let delta = o.price_delta ? parseFloat(o.price_delta) : 0;
                        let deltaText = delta > 0 ? ` (+€${delta.toFixed(2)})` : '';
                        const checkedAttr = idx === 0 ? ' checked' : '';
                        // include data-label with the raw option_name (no price) for programmatic use
                        html += `
<label style="display:flex;align-items:center;font-weight:400;margin-bottom:5px;">
<input type="radio" name="option_${opt.group_id}" id="option_${opt.group_id}_${idx}" value="${o.option_id}" data-delta="${delta}" data-label="${(o.option_name||'').replace(/"/g,'&quot;')}" style="margin-right:8px;"${checkedAttr}>
${o.option_name}${deltaText}
</label>
`;
                    });
                } else if (opt.type === 'select') {
                    html += `<select name="option_${opt.group_id}">`;
                    html += `<option value="" selected>${window.translations.kies}</option>`;
                    opt.options.forEach(o => {
                        let delta = o.price_delta ? parseFloat(o.price_delta) : 0;
                        let deltaText = delta > 0 ? ` (+€${delta.toFixed(2)})` : '';
                        // add data-label with raw option_name
                        html += `<option value="${o.option_id}" data-delta="${delta}" data-label="${(o.option_name||'').replace(/"/g,'&quot;')}">${o.option_name}${deltaText}</option>`;
                    });
                    html += `</select>`;
                } else if (opt.type === 'text') {
                    html += `<input type="text" name="option_${opt.group_id}" placeholder="${window.translations.vulin}">`;
                } else if (opt.type === 'checkbox') {
                    opt.options.forEach((o, idx) => {
                        let delta = o.price_delta ? parseFloat(o.price_delta) : 0;
                        let deltaText = delta > 0 ? ` (+€${delta.toFixed(2)})` : '';
                        // add data-label to checkbox input
                        html += `
<label style="display:flex;align-items:center;font-weight:400;margin-bottom:5px;">
<input type="checkbox" name="option_${opt.group_id}[]" id="option_${opt.group_id}_${idx}" value="${o.option_id}" data-delta="${delta}" data-label="${(o.option_name||'').replace(/"/g,'&quot;')}" style="margin-right:8px;">
${o.option_name}${deltaText}
</label>
`;
                    });
                } else if (opt.type === 'textarea') {
                    html += `<textarea name="option_${opt.group_id}" rows="3" placeholder="${window.translations.vulin}" style="width:100%;"></textarea>`;
                }

                html += `</div>`;
            });
            document.getElementById('modal-options-fields').innerHTML = html;
            // initialize option-rule overrides for the modal (if rules exist)
            try {
                const modalEl = document.getElementById('product-options-modal');
                if (typeof window.initOptionRuleOverrides === 'function' && modalEl) {
                    // example rule: trigger group 58 values 180 or 209 hide groups 59,60,61
                    window.initOptionRuleOverrides({ root: modalEl, triggerGroupId: 58, triggerValues: ['180','209'], targetGroupIds: ['59','60','61'], sentinelValue: '0' });
                }
            } catch(e) { console.warn('initOptionRuleOverrides modal init failed', e); }
            // Apply default green border to required fields so users see they're required
            try {
                data.options.forEach(opt => {
                    if (opt.is_required == 1 && opt.type !== 'checkbox') {
                        const groupEl = document.querySelector(`.modal-option-group[data-group-id="${opt.group_id}"]`);
                        if (groupEl) {
                            // For radio groups we style the label container so the border is visible
                            if (opt.type === 'radio') {
                                // add class to the group wrapper so the border is visible
                                try { groupEl.classList.add('modal-option-required'); } catch(e){}
                                groupEl.querySelectorAll('label').forEach(lbl => {
                                    try { lbl.classList.add('modal-option-required'); } catch(e){}
                                });
                            } else {
                                // For select, text, textarea: add class to the input/select/textarea element
                                const inputEl = groupEl.querySelector('select, input, textarea');
                                if (inputEl) {
                                    try { inputEl.classList.add('input-required'); } catch(e){}
                                }
                            }
                        }
                    }
                });
            } catch(e) { if (window.VOORRAAD_DEBUG) console.warn('failed to apply default required styling', e); }
            // Soft-wrap helper: insert zero-width-spaces into long option texts so
            // native <select> dropdowns have break points. This won't affect the
            // visible label but allows wrapping in browsers that support it.
            try {
                // find all selects we just created
                const selects = document.querySelectorAll('#modal-options-fields select');
                selects.forEach(sel => {
                    // iterate options and insert ZWSP around common break chars/spaces
                    Array.from(sel.options).forEach(opt => {
                        // skip empty placeholder
                        if (!opt.text) return;
                        // if text is already short, skip
                        if (opt.text.length < 35) return;
                        // Insert ZWSP after slashes, commas and sequences of two spaces and before parentheses
                        let t = opt.text;
                        // normalize multiple spaces
                        t = t.replace(/\s{2,}/g, ' ');
                        // insert ZWSP after common delimiters and before '(' so long words can break
                        t = t.replace(/([\/,_\-])\s?/g, '$1\u200B');
                        t = t.replace(/\s+\(/g, '\u200B (');
                        // also insert ZWSP at mid-word camel boundaries or long sequences (every 20 chars)
                        t = t.replace(/(.{20})/g, '$1\u200B');
                        opt.text = t;
                    });
                });
            } catch (e) {
                if (window.VOORRAAD_DEBUG) console.warn('zwsp injection failed', e);
            }
            // Replace selects in the modal with a lightweight custom dropdown
            // so the visible popup can wrap long lines and have a constrained max-width.
            try {
                const selectsToReplace = document.querySelectorAll('#modal-options-fields select');
                selectsToReplace.forEach(orig => {
                    // Skip if already replaced
                    if (orig.closest('.custom-select-wrapper')) return;

                    // Build wrapper
                    const wrapper = document.createElement('div');
                    wrapper.className = 'custom-select-wrapper';

                    // Create display element
                    const display = document.createElement('div');
                    display.className = 'custom-select-display';
                    display.tabIndex = 0;
                    display.textContent = orig.selectedOptions[0]?.text || (orig.querySelector('option')?.text || 'Kies...');

                    // Create list container
                    const list = document.createElement('div');
                    list.className = 'custom-select-list';
                    list.style.display = 'none';

                    // Populate items
                    Array.from(orig.options).forEach((opt, idx) => {
                        const item = document.createElement('div');
                        item.className = 'custom-select-item';
                        if (opt.disabled) item.setAttribute('aria-disabled', 'true');
                        item.dataset.value = opt.value;
                        item.innerHTML = opt.text;
                        if (orig.selectedIndex === idx) item.classList.add('selected');
                        item.addEventListener('click', function() {
                            // set original select value and dispatch change
                            orig.value = item.dataset.value;
                            // update display
                            display.textContent = item.textContent;
                            // mark selected
                            list.querySelectorAll('.custom-select-item').forEach(i => i.classList.remove('selected'));
                            item.classList.add('selected');
                            list.style.display = 'none';
                            orig.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                        list.appendChild(item);
                    });

                    // toggle handler
                    display.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const visible = list.style.display !== 'none';
                        // hide all other lists
                        document.querySelectorAll('.custom-select-list').forEach(l => l.style.display = 'none');
                        if (visible) {
                            list.style.display = 'none';
                        } else {
                            // set list width equal to the display width (cap at 400px)
                            try {
                                const dispRect = display.getBoundingClientRect();
                                const desired = Math.min(400, Math.round(dispRect.width));
                                list.style.width = desired + 'px';
                                list.style.maxWidth = desired + 'px';
                                // align to left edge of wrapper so it doesn't jump out to the right
                                list.style.left = '0px';
                            } catch (err) {
                                // fallback to CSS-defined sizing
                                list.style.width = '';
                                list.style.maxWidth = '';
                                list.style.left = '';
                            }
                            list.style.display = 'block';
                        }
                    });
                    // keyboard accessibility: Enter/Space open/select
                    display.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            display.click();
                        }
                    });

                    // hide on outside click
                    document.addEventListener('click', function() { list.style.display = 'none'; });

                    // hide original select but keep it in the DOM for form serialization and scripts
                    orig.style.position = 'absolute';
                    orig.style.left = '-9999px';
                    orig.style.width = '1px';
                    orig.style.height = '1px';
                    orig.style.overflow = 'hidden';
                    // assemble
                    wrapper.appendChild(display);
                    wrapper.appendChild(list);
                    orig.parentNode.insertBefore(wrapper, orig.nextSibling);
                });
            } catch (e) { if (window.VOORRAAD_DEBUG) console.warn('custom select replace failed', e); }
            document.getElementById('modal-qty-label').textContent = window.translations.aantal;
            // Set button content: cart icon + localized label
            var addBtn = document.getElementById('modal-add-btn');
            if (addBtn) {
                // Use the same cart SVG as the product-card so icons match visually
                addBtn.innerHTML = `
                    <svg class="modal-add-icon" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <circle cx="9" cy="21" r="1" />
                        <circle cx="20" cy="21" r="1" />
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61l1.38-7.59H6" />
                    </svg>
                    <span style="display:inline-block;vertical-align:middle;margin-left:8px;">${window.translations.in_mijn_winkelwagen || window.translations.toevoegen}</span>
                `;
            }

            updateModalPrice(currentBasePrice);

            // Toon modal
            document.getElementById('product-options-modal').classList.remove('hidden');

            let hasRequired = data.options.some(opt => opt.is_required == 1);
            document.getElementById('modal-required-hint').innerHTML = hasRequired ?
                `<span style="font-size:0.85em;color:#1f2937;">(* = ${window.translations.verplichte_velden || 'verplichte velden'})</span>` :
                '';
            // Direct voorraadinstelling wanneer er geen stock-bepalende groepen zijn
            try {
                const expectedStockGroups = (modalProductData?.options || []).filter(g => Number(g.affects_stock) === 1 && Array.isArray(g.options) && g.options.length>0).length;
                if (expectedStockGroups === 0) {
                    const voorraadDiv = document.getElementById('modal-stock-info') || (() => {
                        const d = document.createElement('div'); d.id = 'modal-stock-info'; document.querySelector('.modal-qty-row').insertAdjacentElement('beforebegin', d); return d;
                    })();
                    const qtyInput = document.getElementById('modal-qty');
                    const productStock = Number(modalProductData?.product?.art_aantal ?? 0) || 0;
                    const alreadyInCart = getCartQty(currentProduct.id, []);
                    const maxQty = Math.max(productStock - alreadyInCart, 0);
                    const produktLevertijd = Number(modalProductData?.product?.art_levertijd || 0);

                    if (typeof window.voorraadbeheerActief !== 'undefined' && !window.voorraadbeheerActief) {
                        try { qtyInput.removeAttribute('max'); } catch (e) { qtyInput.max = ''; }
                    } else {
                        if (maxQty === 0 && produktLevertijd > 0) {
                            try { qtyInput.removeAttribute('max'); } catch (e) { qtyInput.max = ''; }
                        } else {
                            qtyInput.max = maxQty;
                        }
                    }

                    let curQty = parseInt(qtyInput.value) || 1;
                    try {
                        if (qtyInput.hasAttribute && qtyInput.hasAttribute('max') && qtyInput.max !== '') {
                            const maxAttr = parseInt(qtyInput.max) || 0;
                            if (curQty > maxAttr) curQty = maxAttr;
                        }
                    } catch (e) { /* ignore */ }
                    if (curQty < 1) curQty = 1;
                    qtyInput.value = curQty;

                    if (typeof window.renderStockOrLevertijd === 'function') {
                        window.renderStockOrLevertijd({
                            targetEl: voorraadDiv,
                            maxQty: maxQty,
                            verkrijgbaarFlag: 1,
                            productId: currentProduct.id,
                            productData: modalProductData?.product || {},
                            addButtonId: 'modal-add-btn'
                        });
                        try { voorraadDiv.setAttribute('data-stock-source', 'product'); if (window.VOORRAAD_DEBUG) console.log('[modal-open] stock source: product, voorraad:', productStock, 'maxQty:', maxQty); } catch(e){}
                    }
                }
            } catch (e) { if (window.VOORRAAD_DEBUG) console.error('modal open voorraad error', e); }
        });
}

function closeProductModal() {
    document.getElementById('product-options-modal').classList.add('hidden');
}

// Event listeners
var modalOptionsForm = document.getElementById('modal-options-form');
            if (modalOptionsForm) {
    modalOptionsForm.addEventListener('change', function(e) {
    // clear inline error styles for this group when user changes something
    try {
        const modal = document.getElementById('product-options-modal');
        if (modal) {
            // If the change target is inside a .modal-option-group, clear styles for that group only
            const group = e.target.closest('.modal-option-group');
            if (group) {
                // determine if this group is required
                const gid = group.getAttribute('data-group-id');
                const optDef = (modalProductData && modalProductData.options) ? modalProductData.options.find(o => String(o.group_id) === String(gid)) : null;
                    if (optDef && optDef.is_required == 1) {
                    // restore required green styling until submission verifies
                        // ensure the wrapper shows required border
                        try { group.classList.add('modal-option-required'); } catch(e){}
                        if (optDef.type === 'radio') {
                            // style labels for radio groups
                            group.querySelectorAll('label').forEach(lbl => {
                                try { lbl.classList.add('modal-option-required'); lbl.classList.remove('invalid-field'); } catch(e){}
                            });
                        } else {
                            group.querySelectorAll('input,select,textarea').forEach(el => {
                                try { el.classList.add('input-required'); el.classList.remove('invalid-field'); } catch(e){}
                            });
                        }
                } else {
                    // non-required: clear any classes
                    group.classList.remove('modal-option-required', 'modal-option-invalid');
                    group.querySelectorAll('input,select,textarea').forEach(el => el.classList.remove('input-required', 'input-invalid'));
                    group.querySelectorAll('label').forEach(lbl => {
                        try { lbl.classList.remove('modal-option-required', 'modal-option-invalid', 'invalid-field'); lbl.style.padding = ''; lbl.style.borderRadius = ''; } catch(e){}
                    });
                }
            }
        }
    } catch(e) {}
    if (e.target.id === 'modal-qty') {
        updateModalPrice(currentBasePrice);
        return;
    }

    updateModalPrice(currentBasePrice);

    // Bouw actuele selectedOptions op en tel required voorraad-opties
    let selectedOptions = [];
    let requiredCount = 0;
    let filledCount = 0;

    modalProductData.options.forEach(opt => {
        let group = `option_${opt.group_id}`;
        let value = '';
        let optionId = null;

        if (opt.type === 'radio') {
            let checked = document.querySelector(`input[name="${group}"]:checked`);
            if (checked) { value = checked.value; optionId = checked.value; }
        } else if (opt.type === 'select') {
            let sel = document.querySelector(`select[name="${group}"]`);
            if (sel && sel.value) { value = sel.value; optionId = sel.value; }
        } else if (opt.type === 'checkbox') {
            let cbs = document.querySelectorAll(`input[name="option_${opt.group_id}[]"]:checked`);
            if (cbs.length > 0) {
                value = Array.from(cbs).map(cb => cb.value).join(',');
                optionId = value;
            }
        } else if (opt.type === 'text') {
            let inp = document.querySelector(`input[name="${group}"]`);
            if (inp && inp.value) { value = inp.value; optionId = null; }
        } else if (opt.type === 'textarea') {
            let ta = document.querySelector(`textarea[name="option_${opt.group_id}"]`);
            if (ta && ta.value) { value = ta.value; optionId = null; }
        }

    // Count ALL required options (niet alleen affects_stock)
        if (opt.is_required == 1) requiredCount++;
        if (opt.is_required == 1 && value) filledCount++;

        if (value) {
            selectedOptions.push({
                group_id: opt.group_id,
                group_name: opt.group_name,
                group_name_nl: opt.group_name_nl,
                type: opt.type,
                value: value,
                option_id: optionId,
                affects_stock: opt.affects_stock
            });
        }
    });

    // debug: zie wat er geselecteerd is
    console.log('modal selectedOptions:', selectedOptions, 'requiredCount:', requiredCount, 'filledCount:', filledCount);

    // Bouw lijst van opties die voorraad beïnvloeden (voor later gebruik bij check / cart)
    const voorraadOpties = selectedOptions.filter(o => Number(o.affects_stock) === 1);

    // Wacht tot alle required opties zijn ingevuld
    let voorraadDiv = document.getElementById('modal-stock-info');
    let qtyInput = document.getElementById('modal-qty');
    if (!voorraadDiv) {
        voorraadDiv = document.createElement('div');
        voorraadDiv.id = 'modal-stock-info';
        document.querySelector('.modal-qty-row').insertAdjacentElement('beforebegin', voorraadDiv);
    }

    // If there are required fields, but none are filled yet, show a gentle hint and keep add disabled.
    // If some required fields are filled but not all, clear the persistent hint and enable the add button
    // so the user can press submit and get red highlights for the missing fields.
    if (requiredCount > 0 && filledCount === 0) {
        // no persistent hint text here by design; keep add button disabled until user fills required fields
        voorraadDiv.innerHTML = '';
        const addBtnEl = document.getElementById('modal-add-btn');
        if (addBtnEl) addBtnEl.disabled = true;
        qtyInput.max = 1;
        qtyInput.value = 1;
        return;
    } else if (requiredCount > 0 && filledCount > 0 && filledCount < requiredCount) {
        // partial: allow the user to try to submit and surface validation errors
        voorraadDiv.innerHTML = '';
        const addBtnEl = document.getElementById('modal-add-btn');
        if (addBtnEl) addBtnEl.disabled = false;
        // Do not proceed to stock check until all required options are filled
        return;
    }

    // Bepaal hoeveel groepen voorraad beïnvloeden op deze productmodal
    const expectedStockGroups = (modalProductData?.options || []).filter(g => Number(g.affects_stock) === 1 && Array.isArray(g.options) && g.options.length>0).length;

    // Wanneer er géén stock-groepen zijn, gebruik direct product-level voorraad uit de modal response
    if (expectedStockGroups === 0) {
        try {
            const productStock = Number(modalProductData?.product?.art_aantal ?? 0) || 0;
            const alreadyInCart = getCartQty(currentProduct.id, []);
            const maxQty = Math.max(productStock - alreadyInCart, 0);
            const produktLevertijd = Number(modalProductData?.product?.art_levertijd || 0);

            if (typeof window.voorraadbeheerActief !== 'undefined' && !window.voorraadbeheerActief) {
                try { qtyInput.removeAttribute('max'); } catch(e) { qtyInput.max = ''; }
            } else {
                if (maxQty === 0 && produktLevertijd > 0) {
                    try { qtyInput.removeAttribute('max'); } catch(e) { qtyInput.max = ''; }
                } else {
                    qtyInput.max = maxQty;
                }
            }

            let curQty = parseInt(qtyInput.value) || 1;
            try {
                if (qtyInput.hasAttribute && qtyInput.hasAttribute('max') && qtyInput.max !== '') {
                    const maxAttr = parseInt(qtyInput.max) || 0;
                    if (curQty > maxAttr) curQty = maxAttr;
                }
            } catch (e) { /* ignore */ }
            if (curQty < 1) curQty = 1;
            qtyInput.value = curQty;

            // Render voorraad/leverinfo
            if (typeof window.renderStockOrLevertijd === 'function') {
                window.renderStockOrLevertijd({
                    targetEl: voorraadDiv,
                    maxQty: maxQty,
                    verkrijgbaarFlag: 1,
                    productId: currentProduct.id,
                    productData: modalProductData?.product || {},
                    addButtonId: 'modal-add-btn'
                });
                try {
                    voorraadDiv.setAttribute('data-stock-source', 'product');
                    if (window.VOORRAAD_DEBUG) console.log('[modal] stock source: product, voorraad:', productStock, 'maxQty:', maxQty);
                } catch(e){}
            }
        } catch(e) {
            if (window.VOORRAAD_DEBUG) console.error('Fout bij bepalen product-level voorraad in modal', e);
        }
        return;
    }

    // Alle required opties zijn ingevuld, nu voorraad checken
    // ================= REPLACE START =================
    (function(){
        // Bouw één genormaliseerde optiestring van de geselecteerde opties (alleen opties die voorraad beïnvloeden)
        const voorraadOpts = buildOptiesStringForDb(selectedOptions);

        console.log('checkStock ->', currentProduct.id, voorraadOpts);

        // één enkele call naar DB voor de exacte combinatie
    checkStock(currentProduct.id, voorraadOpts, function(result) {
            // result = {voorraad, verkrijgbaar}
            const v = parseInt(result.voorraad || 0, 10) || 0;
            const verkrijgbaarFlag = Number(result.verkrijgbaar ?? 1) === 1;

            const alreadyInCart = getCartQty(currentProduct.id, voorraadOpties);
            const maxQty = Math.max(v - alreadyInCart, 0);

            // basis setup
            voorraadDiv.innerHTML = '';
            const produktLevertijd = Number(modalProductData?.product?.art_levertijd || 0);
            // Indien voorraadbeheer uit staat: geen max-attribuut (onbeperkt bestellen)
            if (typeof window.voorraadbeheerActief !== 'undefined' && !window.voorraadbeheerActief) {
                try { qtyInput.removeAttribute('max'); } catch (e) { qtyInput.max = ''; }
            } else {
                // Wanneer maxQty === 0 maar product heeft art_levertijd > 0 -> allow backorder (geen max)
                if (Number(maxQty) === 0 && produktLevertijd > 0) {
                    try { qtyInput.removeAttribute('max'); } catch(e) { qtyInput.max = ''; }
                } else {
                    qtyInput.max = maxQty;
                }
            }
            // Zorg dat de waarde geldig is: minimaal 1 en, indien max aanwezig, niet groter dan max
            let curQty = parseInt(qtyInput.value) || 1;
            try {
                if (qtyInput.hasAttribute && qtyInput.hasAttribute('max') && qtyInput.max !== '') {
                    const maxAttr = parseInt(qtyInput.max) || 0;
                    if (curQty > maxAttr) curQty = maxAttr;
                }
            } catch (e) { /* ignore */ }
            if (curQty < 1) curQty = 1;
            qtyInput.value = curQty;

            // Gebruik gedeelde helper om óf levertijd óf voorraadtekst te tonen
            if (typeof window.renderStockOrLevertijd === 'function') {
                window.renderStockOrLevertijd({
                    targetEl: voorraadDiv,
                    maxQty: maxQty,
                    verkrijgbaarFlag: verkrijgbaarFlag,
                    productId: currentProduct.id,
                    productData: modalProductData?.product || {},
                    addButtonId: 'modal-add-btn'
                });
                // tag source in DOM and optional debug log
                try {
                    voorraadDiv.setAttribute('data-stock-source', voorraadOpts && voorraadOpts !== '' ? 'options' : 'product');
                    if (window.VOORRAAD_DEBUG) console.log('[modal] stock source:', voorraadDiv.getAttribute('data-stock-source'), 'voorraad:', v, 'opties:', voorraadOpts);
                } catch(e){}
                    // Extra guard: wanneer de modal een berekende expected-date bevat,
                    // forceer dan dat de add-knop enabled is (voorkom dat andere logica hem uitschakelt).
                    try {
                        const modalEl = document.getElementById('product-options-modal');
                        if (modalEl && modalEl.getAttribute('data-expected-date')) {
                            const ab = document.getElementById('modal-add-btn');
                            if (ab) ab.disabled = false;
                        }
                    } catch (e) { /* ignore */ }
            } else {
                // fallback: oude weergave
                if (!verkrijgbaarFlag) {
                    voorraadDiv.innerHTML = `<br><small style="color:#ff9800;">${window.translations.niet_verkrijgbaar || 'Niet verkrijgbaar'}</small>`;
                    document.getElementById('modal-add-btn').disabled = true;
                    qtyInput.max = 0;
                    qtyInput.value = 0;
                    return;
                }
                document.getElementById('modal-add-btn').disabled = false;
                if (maxQty > 3) {
                    voorraadDiv.innerHTML += `<br><small style="color:#088f61;">${window.translations.op_stock || 'Op stock'}</small>`;
                } else {
                    const tpl = window.translations.voorraad_slechts || 'Voorraad: slechts %s beschikbaar';
                    voorraadDiv.innerHTML += `<br><small style="color:#ff9800;">${tpl.replace('%s', maxQty)}</small>`;
                }
            }
        });
    })();
    // ================= REPLACE END =================

    });
} else {
    // defensive: log in debug when form is missing
    if (window.VOORRAAD_DEBUG) console.warn('modal-options-form niet gevonden; event listeners niet toegevoegd');
}

// Toevoegen aan cart
if (modalOptionsForm) {
    modalOptionsForm.onsubmit = function(e) {
        e.preventDefault();
        const data = modalProductData;
        const qty = parseInt(document.getElementById('modal-qty').value) || 1;
        const selectedOptions = [];
        let missingRequired = false;
        let firstInvalidEl = null;

        for (let i = 0; i < (data.options || []).length; i++) {
            const opt = data.options[i];
            const group = `option_${opt.group_id}`;
            let filled = false;
            let value = '';
            let optionText = '';
            let optionId = null;
            let priceDelta = 0;

            if (opt.type === 'radio') {
                const checked = document.querySelector(`input[name="${group}"]:checked`);
                if (checked) {
                    filled = true;
                    value = checked.value;
                    optionId = checked.value;
                    optionText = checked.dataset.label || (checked.closest('label') ? checked.closest('label').textContent.trim() : '');
                    priceDelta = parseFloat(checked.dataset.delta || 0);
                }
            } else if (opt.type === 'select') {
                const sel = document.querySelector(`select[name="${group}"]`);
                if (sel && sel.value) {
                    filled = true;
                    value = sel.value;
                    optionId = sel.value;
                    optionText = sel.selectedOptions[0].dataset.label || sel.selectedOptions[0].textContent;
                    priceDelta = parseFloat(sel.selectedOptions[0].dataset.delta || 0);
                }
            } else if (opt.type === 'text') {
                const inp = document.querySelector(`input[name="${group}"]`);
                if (inp && inp.value) {
                    filled = true;
                    value = inp.value;
                    optionText = inp.value;
                }
            } else if (opt.type === 'checkbox') {
                const cbs = document.querySelectorAll(`input[name="option_${opt.group_id}[]"]:checked`);
                if (cbs.length) {
                    cbs.forEach(cb => {
                        selectedOptions.push({
                            group_id: opt.group_id,
                            group_name: opt.group_name,
                            group_name_nl: opt.group_name_nl,
                            type: opt.type,
                            value: cb.value,
                            option_id: cb.value,
                            label: cb.dataset.label || (cb.closest('label') ? cb.closest('label').textContent.trim() : ''),
                            price_delta: parseFloat(cb.dataset.delta || 0),
                            affects_stock: opt.affects_stock
                        });
                    });
                    filled = true;
                }
            } else if (opt.type === 'textarea') {
                const ta = document.querySelector(`textarea[name="option_${opt.group_id}"]`);
                if (ta && ta.value) {
                    filled = true;
                    value = ta.value;
                    optionText = ta.value;
                }
            }

            // Determine if this group is hidden by any overrides; if hidden, treat as filled for validation
            let hiddenByOverride = false;
            try {
                const wrapper = document.querySelector(`.modal-option-group[data-group-id="${opt.group_id}"]`);
                hiddenByOverride = wrapper && (wrapper.dataset._overrideHidden === '1' || wrapper.getAttribute('data-override-hidden') === '1');
            } catch (e) { hiddenByOverride = false; }

            if (filled && opt.type !== 'checkbox') {
                selectedOptions.push({
                    group_id: opt.group_id,
                    group_name: opt.group_name,
                    group_name_nl: opt.group_name_nl,
                    type: opt.type,
                    value: value,
                    option_id: optionId,
                    label: optionText,
                    price_delta: priceDelta,
                    affects_stock: opt.affects_stock
                });
            }

            // Per-group visual state for required groups
            if (opt.is_required == 1 && !hiddenByOverride) {
                try {
                    const groupEl = document.querySelector(`.modal-option-group[data-group-id="${opt.group_id}"]`);
                    if (filled) {
                        if (groupEl) {
                            groupEl.classList.remove('modal-option-invalid');
                            groupEl.classList.add('modal-option-required');
                            if (opt.type === 'radio') {
                                groupEl.querySelectorAll('label').forEach(lbl => {
                                    lbl.classList.remove('modal-option-invalid', 'invalid-field');
                                    lbl.classList.add('modal-option-required');
                                });
                            } else {
                                groupEl.querySelectorAll('input,select,textarea').forEach(el => {
                                    el.classList.remove('input-invalid');
                                    el.classList.add('input-required');
                                });
                            }
                        }
                    } else {
                        missingRequired = true;
                        if (groupEl) {
                            // remove the green required marker from the wrapper but do NOT add a
                            // wrapper-level invalid border (keep validation subtle by marking
                            // the inputs/labels themselves)
                            groupEl.classList.remove('modal-option-required');
                            if (opt.type === 'radio') {
                                groupEl.querySelectorAll('label').forEach(lbl => {
                                    lbl.classList.remove('modal-option-required');
                                    // mark the label itself as invalid (keeps the highlight localized)
                                    lbl.classList.add('modal-option-invalid', 'invalid-field');
                                });
                            } else {
                                const targets = groupEl.querySelectorAll('input,select,textarea');
                                if (targets.length) {
                                    targets.forEach(t => {
                                        t.classList.remove('input-required');
                                        t.classList.add('input-invalid');
                                    });
                                } else {
                                    groupEl.querySelectorAll('label').forEach(l => { l.classList.add('invalid-field'); });
                                }
                            }
                        }
                    }
                } catch (e) { /* ignore UI update errors */ }
            }
        }

        if (missingRequired) {
            // Keep feedback subtle: we've marked the individual inputs/labels as invalid.
            // Do not focus/scroll to the first invalid element and do not show a persistent error.
            document.getElementById('modal-error-msg').innerHTML = '';
            return;
        } else {
            document.getElementById('modal-error-msg').innerHTML = '';
        }

        // Prijs berekenen
        let price = updateModalPrice(currentBasePrice);

        // Bouw samengestelde naam. Voeg art_kenmerk direct na de productnaam toe indien aanwezig.
        let fullName = currentProduct.name;
        if (modalProductData && modalProductData.product && modalProductData.product.kenmerk) {
            const k = (modalProductData.product.kenmerk || '').trim();
            if (k !== '') fullName += ' - ' + k;
        }
        if (selectedOptions.length > 0) {
            let optStr = selectedOptions.map(opt => {
                let label = (opt.label || '').trim();
                return label;
            }).join(' - ');
            fullName += ' > ' + optStr;
        }

        // Cart item object
        let cartItem = {
            product_id: currentProduct.id,
            name: fullName,
            category: currentProduct.category,
            options: selectedOptions,
            price: price,
            btw: currentProduct.BTWtarief || currentProduct.art_BTWtarief || currentProduct.btw || 21,
            qty: qty
        };

        try {
            const modalEl = document.getElementById('product-options-modal');
            const expected = modalEl ? modalEl.getAttribute('data-expected-date') : null;
            if (expected) {
                const parts = expected.split('-');
                if (parts.length === 3) {
                    const disp = parts[2].padStart(2, '0') + '/' + parts[1].padStart(2, '0') + '/' + parts[0];
                    cartItem.name = cartItem.name + ' > vanaf ' + disp;
                    cartItem.expected_date = expected;
                    cartItem.backorder = 1;
                }
            }
        } catch (e) { /* silent */ }

        let cart = JSON.parse(localStorage.getItem('cart') || '[]');
        let found = cart.find(item => item.product_id === cartItem.product_id && item.category === cartItem.category && item.name === cartItem.name);
        if (found) found.qty += cartItem.qty; else cart.push(cartItem);
        localStorage.setItem('cart', JSON.stringify(cart));

        closeProductModal();
        if (typeof updateCartBadge === 'function') updateCartBadge();

        let btns = document.querySelectorAll('.product-cart-btn');
        let fromBtn = null;
        btns.forEach(btn => { if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(currentProduct.id)) fromBtn = btn; });
        animateToCart(fromBtn);
    };
} else {
    if (window.VOORRAAD_DEBUG) console.warn('modal-options-form niet gevonden; onsubmit-handler niet toegevoegd');
}
