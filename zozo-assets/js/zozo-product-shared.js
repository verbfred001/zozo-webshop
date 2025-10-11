// Opties-string voor voorraadcontrole
function getVoorraadOptiesString(selectedOptions) {
    return (selectedOptions || [])
        .filter(opt => opt && opt.affects_stock == 1)
        .map(opt => opt.group_name + ':' + (opt.option_id || opt.value))
        .join('|');
}

// Format price without euro symbol.
// - if cents are zero -> '7,-'
// - otherwise -> '6,85' (comma decimal separator, thousands with dot)
function formatPriceNoSymbol(amount) {
    const n = Number(amount) || 0;
    const rounded = Math.round(n * 100) / 100;
    const euros = Math.floor(Math.abs(rounded));
    const cents = Math.round((Math.abs(rounded) - euros) * 100);
    if (cents === 0) {
        // If exactly zero, prefer the localized 'op maat' string when available
        if (Math.abs(rounded) === 0 && typeof window !== 'undefined' && window.translations && window.translations.op_maat) {
            return window.translations.op_maat;
        }
        return (rounded < 0 ? '-' : '') + String(euros) + ',-';
    }
    // build localized string with 2 decimals, comma as decimal separator and dot as thousands sep
    const parts = euros.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const centStr = (cents < 10 ? '0' + cents : String(cents));
    return (rounded < 0 ? '-' : '') + parts + ',' + centStr;
}

// expose helper
window.formatPriceNoSymbol = formatPriceNoSymbol;

let voorraadbeheerActief = true; // Zet deze globaal, en update hem na het laden van de productdata
// Developer debug flag: zet true om console-log en DOM-attribuut te zien welke bron voor voorraad gebruikt wordt
window.VOORRAAD_DEBUG = false;

/**
 * Throttled stock checker
 * - stuurt same-origin credentials en X-Requested-With header
 * - queue met max concurente requests om 403 door overbelasting te voorkomen
 * - behoudt bestaande callback-signature: checkStock(productId, optiesString, callback)
 */

const _stockQueue = {
    running: 0,
    queue: []
};
const _MAX_CONCURRENT_STOCK_REQUESTS = 3; // verlaagd (oorspronkelijk 6)
const _STOCK_REQUEST_DELAY_MS = 150; // verhoogd pauze (oorspronkelijk 40)

function _runNextStock() {
    if (_stockQueue.queue.length === 0) return;
    if (_stockQueue.running >= _MAX_CONCURRENT_STOCK_REQUESTS) return;
    const task = _stockQueue.queue.shift();
    _stockQueue.running++;
    task().finally(() => {
        _stockQueue.running--;
        // kleine delay om bursts te vermijden
        setTimeout(_runNextStock, _STOCK_REQUEST_DELAY_MS);
    });
}

function _enqueueStockTask(taskFn) {
    return new Promise((resolve, reject) => {
        _stockQueue.queue.push(() => taskFn().then(resolve).catch(reject));
        _runNextStock();
    });
}

/**
 * Promise-based low-level request with retry/backoff for transient 403/5xx.
 * checkStockPromise(productId, optiesString, opts?)
 * opts = { retries: 3, backoffBaseMs: 200 }
 */
function checkStockPromise(productId, optiesString, opts = {}) {
    const url = '/zozo-includes/check_stock.php?product_id=' + encodeURIComponent(productId) +
        (optiesString ? '&opties=' + encodeURIComponent(optiesString) : '');

    const maxRetries = Number.isInteger(opts.retries) ? opts.retries : 3;
    const backoffBaseMs = Number.isFinite(opts.backoffBaseMs) ? opts.backoffBaseMs : 200;

    function attempt(tryNo) {
        return _enqueueStockTask(() => {
            return fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                cache: 'no-store'
            }).then(resp => {
                if (!resp.ok) {
                    if ((resp.status === 403 || (resp.status >= 500 && resp.status < 600)) && tryNo < maxRetries) {
                        const delay = backoffBaseMs * Math.pow(2, tryNo - 1);
                        return new Promise((res) => setTimeout(res, delay)).then(() => attempt(tryNo + 1));
                    }
                    return Promise.resolve({ voorraad: 0, beschikbaar: 1 });
                }
                return resp.json().catch(() => ({ voorraad: 0, beschikbaar: 1 }));
            }).then(data => {
                // normalize to object {voorraad, verkrijgbaar}
                let voorraad = 0;
                let verkrijgbaar = 1;
                if (typeof data === 'number') {
                    voorraad = parseInt(data, 10) || 0;
                } else if (data && typeof data.voorraad !== 'undefined') {
                    voorraad = parseInt(data.voorraad || 0, 10) || 0;
                }
                // prefer 'verkrijgbaar' (new), fallback to 'beschikbaar' (legacy)
                if (data) {
                    if (typeof data.verkrijgbaar !== 'undefined') {
                        verkrijgbaar = Number(data.verkrijgbaar) ? 1 : 0;
                    } else if (typeof data.beschikbaar !== 'undefined') {
                        verkrijgbaar = Number(data.beschikbaar) ? 1 : 0;
                    }
                }
                // return both for backward compatibility but use 'verkrijgbaar' everywhere in callers
                return { voorraad, verkrijgbaar, beschikbaar: verkrijgbaar };
            }).catch(err => {
                if (tryNo < maxRetries) {
                    const delay = backoffBaseMs * Math.pow(2, tryNo - 1);
                    return new Promise((res) => setTimeout(res, delay)).then(() => attempt(tryNo + 1));
                }
                return { voorraad: 0, verkrijgbaar: 1, beschikbaar: 1 };
            });
        });
    }

    return attempt(1);
}

/* Backwards-compatible wrapper */
function checkStock(productId, optiesString, callback) {
    if (typeof callback !== 'function') callback = function(){};
    checkStockPromise(productId, optiesString)
        .then(result => {
            try { callback(result, optiesString); } catch (e) { /* swallow */ }
        })
        .catch(() => {
            try { callback({ voorraad: 0, beschikbaar: 1 }, optiesString); } catch (e) {}
        });
}

function filterStockOptions(options) {
    return (options || [])
        .filter(o => o && o.affects_stock == 1)
        .map(o => o.group_name + ':' + (o.option_id || o.value))
        .sort()
        .join('|');
}

/* --- COMBINATOR HELPERS: veilig cartesiaans product en optiestring builder --- */
/**
 * Bereken cartesiaans product van meerdere option-groepen.
 * Input: [[opt1, opt2], [optA, optB], [optX, optY]] => array van combinaties (elk is array)
 */
function cartesianProduct(arrays) {
    if (!Array.isArray(arrays) || arrays.length === 0) return [];
    return arrays.reduce((acc, curr) => {
        const out = [];
        acc.forEach(a => {
            curr.forEach(c => out.push(a.concat([c])));
        });
        return out;
    }, [[]]);
}

/**
 * Bouw consistente optiestring voor opslag match: "groep:optie|groep:optie|..."
 * Verwacht elk element in combinatie als object met minimaal: group_name en option_id (of value).
 * Sorteert de onderdelen zodat volgorde geen mismatch veroorzaakt.
 */
function buildOptionsStringFromCombination(combination) {
    if (!Array.isArray(combination)) return '';
    const parts = combination.map(o => {
        const key = o.group_name ?? o.group ?? ('g' + (o.group_id ?? ''));
        const val = o.option_id ?? o.value ?? o.id ?? '';
        return String(key) + ':' + String(val);
    }).sort();
    return parts.join('|');
}

// snelle in-memory cache om herhaalde lookups te vermijden (key = productId + '|' + optiesString)
const _stockCache = new Map();

/**
 * Genereer alle combinaties van groepen (alleen opties die affects_stock==1) en roep checkStock aan per unieke combinatie.
 * - dedupeert identieke optiestrings zodat er maar 1 DB-call per unieke combinatie gebeurt
 * - gebruikt checkStockBatch wanneer beschikbaar (één POST voor alle unieke strings)
 * - vult een in-memory cache om herhaalde calls binnen dezelfde sessie te vermijden
 *
 * groups: array van arrays (per groep: option-objects)
 * productId: product ID voor checkStock
 * onResult: callback(optiesString, voorraad, combination)
 */
function generateAndCheckCombinations(groups, productId, onResult) {
    if (!Array.isArray(groups) || groups.length === 0) return;
    // filter en verwijder lege groepen
    const usable = groups
        .map(g => (Array.isArray(g) ? g.filter(opt => opt && Number(opt.affects_stock) == 1) : []))
        .filter(g => g.length > 0);
    if (usable.length === 0) return;

    const combos = cartesianProduct(usable);
    if (!combos || combos.length === 0) return;

    // map van optiesString -> lijst van combo indices (om later terug te mappen naar combinations)
    const uniqueMap = new Map();
    combos.forEach((combo, idx) => {
        const optiesString = buildOptionsStringFromCombination(combo);
        if (!uniqueMap.has(optiesString)) uniqueMap.set(optiesString, []);
        uniqueMap.get(optiesString).push(idx);
    });

    const uniqueStrings = Array.from(uniqueMap.keys());
    if (uniqueStrings.length === 0) return;

    // helper om resultaten terug te mappen naar alle bijbehorende combos
    const handleResult = (optS, voorraad) => {
        const indices = uniqueMap.get(optS) || [];
        indices.forEach(i => {
            const combo = combos[i];
            try {
                if (typeof onResult === 'function') onResult(optS, voorraad, combo);
            } catch (e) { /* swallow */ }
        });
    };

    // bepaal welke unieke strings nog opgevraagd moeten worden (cache check)
    const toRequest = [];
    uniqueStrings.forEach(optS => {
        const cacheKey = productId + '|' + optS;
        if (_stockCache.has(cacheKey)) {
            handleResult(optS, _stockCache.get(cacheKey));
        } else {
            toRequest.push(optS);
        }
    });

    if (toRequest.length === 0) return;

    // indien batch-endpoint beschikbaar: één call
    if (typeof window.checkStockBatch === 'function') {
        checkStockBatch(productId, toRequest).then(map => {
            toRequest.forEach(optS => {
                const v = parseInt(map[optS] || 0, 10) || 0;
                _stockCache.set(productId + '|' + optS, v);
                handleResult(optS, v);
            });
        }).catch(() => {
            // fallback naar individuele calls (gebruikt de bestaande throttled queue)
            toRequest.forEach(optS => {
                checkStockPromise(productId, optS).then(v => {
                    const val = parseInt(v || 0, 10) || 0;
                    _stockCache.set(productId + '|' + optS, val);
                    handleResult(optS, val);
                });
            });
        });
    } else {
        // geen batch endpoint: vraag per unieke string (throttling is al in checkStockPromise)
        toRequest.forEach(optS => {
            checkStockPromise(productId, optS).then(v => {
                const val = parseInt(v || 0, 10) || 0;
                _stockCache.set(productId + '|' + optS, val);
                handleResult(optS, val);
            });
        });
    }
}

// Maak helpers beschikbaar voor andere scripts
window.cartesianProduct = cartesianProduct;
window.buildOptionsStringFromCombination = buildOptionsStringFromCombination;
window.generateAndCheckCombinations = generateAndCheckCombinations;

/* --- CART COUNT / BADGE (consolidated & robust) --- */
function _computeCartCount(cartData) {
    if (!cartData) return 0;
    // Array of items [{qty:..}] typical
    if (Array.isArray(cartData)) {
        return cartData.reduce((sum, it) => {
            if (!it) return sum;
            const q = parseInt(it.qty ?? it.quantity ?? it.count ?? it.qty_num ?? 0, 10);
            return sum + (isNaN(q) ? 0 : q);
        }, 0);
    }
    // Object with items array: { items: [...] }
    if (cartData.items && Array.isArray(cartData.items)) {
        return _computeCartCount(cartData.items);
    }
    // Object map: { productId: qty, ... }
    if (typeof cartData === 'object') {
        return Object.values(cartData).reduce((sum, v) => {
            if (typeof v === 'number') return sum + v;
            if (typeof v === 'object') {
                const q = parseInt(v.qty ?? v.quantity ?? v.count ?? 0, 10);
                return sum + (isNaN(q) ? 0 : q);
            }
            const parsed = parseInt(v, 10);
            return sum + (isNaN(parsed) ? 0 : parsed);
        }, 0);
    }
    return 0;
}

function getCartQty(productId, voorraadOpties) {
    let cart = [];
    try {
        cart = JSON.parse(localStorage.getItem('cart') || '[]');
    } catch (e) {
        cart = [];
    }
    let qty = 0;
    (cart || []).forEach(item => {
        if (!item) return;
        if (String(item.product_id) !== String(productId)) return;

        const itemOptions = Array.isArray(item.options) ? item.options : [];
        // Haal alleen de voorraadbepalende opties uit het cart-item
        let itemVoorraadOpties = itemOptions
            .filter(o => o && o.affects_stock == 1)
            .map(o => o.group_name + ':' + (o.option_id || o.value))
            .sort()
            .join('|');

        // Haal alleen de voorraadbepalende opties uit de huidige selectie
        let selectedVoorraadOpties = (voorraadOpties || [])
            .map(o => o.group_name + ':' + (o.option_id || o.value))
            .sort()
            .join('|');

        if (itemVoorraadOpties === selectedVoorraadOpties) {
            const q = parseInt(item.qty ?? item.quantity ?? 0, 10) || 0;
            qty += q;
        }
    });
    return qty;
}

(function(){
    function findBadge() {
        return document.querySelector('.cart-badge') || document.getElementById('cart-badge');
    }

    function pulseBadge(newCount, animate = true) {
        const badge = findBadge();
        if (!badge) return;

        const prev = parseInt(badge.getAttribute('data-count') || badge.getAttribute('data-prev') || '0', 10) || 0;
        const raw = (typeof newCount === 'number') ? Math.max(0, Math.floor(newCount))
            : parseInt(badge.getAttribute('data-count') || badge.textContent || '0', 10) || 0;

        const display = raw >= 10 ? '9+' : String(raw);

        badge.textContent = display;
        badge.setAttribute('data-count', String(raw));
        badge.setAttribute('data-prev', String(prev));

        // pulse only when explicitly requested AND count increased
        const shouldPulse = Boolean(animate) && (raw > prev);
        if (shouldPulse) {
            badge.classList.add('cart-badge--added');
            window.setTimeout(() => badge.classList.remove('cart-badge--added'), 1400);
        } else {
            badge.classList.remove('cart-badge--added');
        }

        if (raw <= 0) {
            badge.style.display = 'none';
        } else {
            badge.style.display = 'inline-flex';
        }
    }

    window.updateCartBadge = function(count, animate = true) {
        pulseBadge(count, animate);
    };

    function _initCartBadge() {
        try {
            const badge = findBadge();
            const serverCountAttr = badge ? badge.getAttribute('data-count') : null;
            const serverCount = serverCountAttr ? parseInt(serverCountAttr, 10) : NaN;

            let lsCount = 0;
            try {
                const raw = JSON.parse(localStorage.getItem('cart') || 'null');
                lsCount = _computeCartCount(raw);
            } catch (e) {
                lsCount = 0;
            }

            const initialCount = Number.isFinite(serverCount) && serverCount >= 0 ? serverCount : lsCount;

            if (badge) {
                badge.setAttribute('data-count', String(initialCount));
                badge.setAttribute('data-prev', String(initialCount));
                if (initialCount > 0) {
                    badge.style.display = 'inline-flex';
                    badge.textContent = (initialCount >= 10 ? '9+' : String(initialCount));
                } else {
                    badge.style.display = 'none';
                    badge.textContent = '';
                }
            }
        } catch (e) {
            // fail silently
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _initCartBadge);
    } else {
        _initCartBadge();
    }
})();

// Expose helpers: lees count uit localStorage en refresh badge direct
window.getCartCountFromStorage = function() {
    try {
        const raw = JSON.parse(localStorage.getItem('cart') || 'null');
        return _computeCartCount(raw);
    } catch (e) {
        return 0;
    }
};

window.refreshCartBadgeFromStorage = function(animate = false) {
    const cnt = window.getCartCountFromStorage();
    if (typeof window.updateCartBadge === 'function') {
        window.updateCartBadge(cnt, Boolean(animate));
    }
};

    // Shared helper: animate a small green dot from a source element to the header cart
    window.animateCartFly = function(fromElem) {
        try {
            var cartIcon = document.querySelector('.cart-btn');
            if (!cartIcon || !fromElem) return;
            var startRect = fromElem.getBoundingClientRect();
            var endRect = cartIcon.getBoundingClientRect();

            var ball = document.createElement('div');
            ball.className = 'zozo-fly-dot';
            // initial placement centered on the source element
            ball.style.left = (startRect.left + startRect.width / 2 - 9) + 'px';
            ball.style.top = (startRect.top + startRect.height / 2 - 9) + 'px';
            document.body.appendChild(ball);

            // force layout
            ball.offsetWidth;

            // move toward the center of the cart icon
            ball.style.left = (endRect.left + endRect.width / 2 - 9) + 'px';
            ball.style.top = (endRect.top + endRect.height / 2 - 9) + 'px';
            ball.style.opacity = '0.95';
            ball.style.transform = 'scale(0.6)';

            // after animation, remove and pulse badge
            setTimeout(function() {
                try { ball.remove(); } catch(e) {}
                var badge = document.getElementById('cart-badge');
                if (badge) {
                    badge.classList.add('cart-badge--added');
                    setTimeout(function() { badge.classList.remove('cart-badge--added'); }, 1200);
                }
            }, 1200);
        } catch (e) { /* fail silently */ }
    };

// Simpele gedragsregels:
// - Als voorraad === 0 en art_levertijd > 0: toon altijd vandaag + art_levertijd en allow add-to-cart.
// - Als voorraad === 0 en art_levertijd == 0: toon 'Voorraad: niet beschikbaar' en disable add-to-cart.

// Hook localStorage.setItem zodat wijzigingen aan cart direct de badge updaten
if (!window.__zozo_localStorage_hooked) {
    (function(){
        const CART_KEYS = ['cart','zozo_cart','basket','shopping_cart','cart_items','cartData','cartData_v1'];
        const originalSet = Storage.prototype.setItem;
        Storage.prototype.setItem = function(key, value) {
            // voer originele write eerst uit
            originalSet.apply(this, arguments);
            try {
                if (CART_KEYS.indexOf(String(key)) !== -1) {
                    // lichte delay zodat caller klaar is met schrijven
                    setTimeout(() => window.dispatchEvent(new CustomEvent('zozo:cart-updated')), 10);
                }
            } catch (e) { /* fail silently */ }
        };
        window.__zozo_localStorage_hooked = true;
    })();

    // Event listener: refresh badge (met animatie wanneer mogelijk)
    window.addEventListener('zozo:cart-updated', function () {
        if (typeof window.refreshCartBadgeFromStorage === 'function') {
            // true => pulse only if count increased
            window.refreshCartBadgeFromStorage(true);
        }
    }, { passive: true });
}

/**
 * Render stock info or levertijd into targetEl.
 * params: {
 *   targetEl: DOMElement,
 *   maxQty: number,
 *   verkrijgbaarFlag: boolean,
 *   productId: number|string,
 *   productData: object (ajax product response, optional),
 *   addButtonId: string (optional, default 'modal-add-btn')
 * }
 */
function renderStockOrLevertijd({ targetEl, maxQty, verkrijgbaarFlag, productId, productData = {}, addButtonId = 'modal-add-btn' }) {
    if (!targetEl) return;
    // Respecteer globale voorraadbeheer setting: wanneer uit, toon geen voorraadmeldingen
    if (typeof window.voorraadbeheerActief !== 'undefined' && !window.voorraadbeheerActief) {
        try { const ab = document.getElementById(addButtonId); if (ab) ab.disabled = false; } catch(e){}
        targetEl.innerHTML = '';
        return;
    }
    function dagenTekst(n){ return n + ' dag' + (n === 1 ? '' : 'en'); }

    // bepaal levertijd (ajax productData heeft voorrang, fallback naar global meta)
    var lv = Number(productData?.art_levertijd ?? productData?.levertijd ?? 0) || 0;
    if ((!lv || lv === 0) && window.__zozoProductMeta && window.__zozoProductMeta[productId]) {
        lv = Number(window.__zozoProductMeta[productId].levertijd || 0) || 0;
    }
    var addBtn = document.getElementById(addButtonId);

    // Wanneer geen voorraad maar artikel wél verkrijgbaar is
    if (Number(maxQty) === 0 && Number(verkrijgbaarFlag) === 1) {
        if (Number(lv) > 0) {
            // bereken geschatte datum: vandaag + lv dagen
            try {
                const today = new Date();
                const eta = new Date(today);
                eta.setDate(eta.getDate() + Number(lv));
                const dd = String(eta.getDate()).padStart(2, '0');
                const mm = String(eta.getMonth() + 1).padStart(2, '0');
                const yyyy = eta.getFullYear();
                // duidelijkere wording en korter label
                const br = targetEl.id === 'detail-stock-info' ? '' : '<br>';
                targetEl.innerHTML = `${br}<small style="color:#ff9800;">Beschikbaar vanaf ${dd}/${mm}/${yyyy} <span style="color:#888;">(koop op bestelling)</span></small>`;
                // Stel data-attribute op modal of op het target element zodat frontend bij toevoegen de datum kan meezenden
                try {
                    if (targetEl.closest) {
                        const modal = targetEl.closest('#product-options-modal');
                        if (modal) modal.setAttribute('data-expected-date', `${yyyy}-${mm}-${dd}`);
                    }
                    // altijd ook op het target element zetten (voor detailpagina gebruik)
                    targetEl.setAttribute('data-expected-date', `${yyyy}-${mm}-${dd}`);
                } catch(e){}
            } catch (e) {
                const br = targetEl.id === 'detail-stock-info' ? '' : '<br>';
                targetEl.innerHTML = `${br}<small style=\"color:#ff9800;\">Momenteel niet voorradig (geschatte levertijd ${lv} ${lv==1? 'dag':'dagen'})</small>`;
            }
            if (addBtn) addBtn.disabled = false; // allow adding to cart even when stock=0 (backorder)
            return;
        } else {
            // levertijd == 0 -> niet beschikbaar en niet bestelbaar
            const br = targetEl.id === 'detail-stock-info' ? '' : '<br>';
            targetEl.innerHTML = `${br}<small style="color:#ff9800;">Voorraad: niet beschikbaar</small>`;
            if (addBtn) addBtn.disabled = true;
            try {
                if (targetEl.closest) {
                    const modal = targetEl.closest('#product-options-modal');
                    if (modal) modal.removeAttribute('data-expected-date');
                }
                targetEl.removeAttribute('data-expected-date');
            } catch(e){}
            return;
        }
    }

    // fallback / normale stock weergave
    if (!verkrijgbaarFlag) {
        const br = targetEl.id === 'detail-stock-info' ? '' : '<br>';
        targetEl.innerHTML = `${br}<small style="color:#ff9800;">${window.translations?.niet_verkrijgbaar || 'Niet verkrijgbaar'}</small>`;
        if (addBtn) addBtn.disabled = true;
        return;
    }

    if (Number(maxQty) > 3) {
        const br = targetEl.id === 'detail-stock-info' ? '' : '<br>';
        targetEl.innerHTML = `${br}<small style="color:#088f61;">${window.translations?.op_stock || 'Op stock'}</small>`;
        if (addBtn) addBtn.disabled = false;
    } else {
        const tpl = window.translations?.voorraad_slechts || 'Voorraad: slechts %s beschikbaar';
        const br = targetEl.id === 'detail-stock-info' ? '' : '<br>';
        targetEl.innerHTML = `${br}<small style="color:#ff9800;">${tpl.replace('%s', maxQty)}</small>`;
        if (addBtn) addBtn.disabled = (Number(maxQty) === 0);
    }
}
window.renderStockOrLevertijd = renderStockOrLevertijd;