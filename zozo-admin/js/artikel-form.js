// Vertaal functie
async function translateText(text, targetLang) {
    if (!text.trim()) return '';
    try {
        const apiUrl = `https://api.mymemory.translated.net/get?q=${encodeURIComponent(text)}&langpair=nl|${targetLang}`;
        const response = await fetch(apiUrl);
        const result = await response.json();
        return result.responseData.translatedText || '';
    } catch (error) {
        console.error('Vertaling mislukt:', error);
        return '';
    }
}

// Vertaal field functie
async function translateField(sourceFieldName, targetFieldName, targetLang) {
    // Robust field lookup: prefer visible/non-hidden fields
    const findField = (nameOrId) => {
        const idSel = `#${nameOrId}`;
        const nameSel = `[name="${nameOrId}"]`;
        const nonHiddenNameSel = `${nameSel}:not([type="hidden"])`;

        let el = document.querySelector(idSel);
        if (el) return el;
        el = document.querySelector(nonHiddenNameSel);
        if (el) return el;
        return document.querySelector(nameSel);
    };

    const sourceField = findField(sourceFieldName);
    const targetField = findField(targetFieldName) || document.getElementById(targetFieldName);
    const sourceText = sourceField ? sourceField.value : '';
    
    if (!sourceText.trim()) {
        alert('Vul eerst de Nederlandse tekst in');
        return;
    }
    
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '⏳ Bezig...';
    
    try {
        const translation = await translateText(sourceText, targetLang);
        if (translation) {
            // Minimal normalization: lowercase first char so "Soupe" -> "soupe"
            function lcFirst(str) {
                if (!str || typeof str !== 'string') return str;
                // Use locale-aware lowercasing for first char
                const first = str.charAt(0).toLocaleLowerCase();
                return first + str.slice(1);
            }
            const final = lcFirst((translation || '').trim());
            if (targetField) {
                targetField.value = final;
                // ensure any UI bindings see the change
                targetField.dispatchEvent(new Event('input', { bubbles: true }));
                targetField.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    } catch (error) {
        alert('Vertaling mislukt');
    } finally {
        btn.disabled = false;
        btn.textContent = '🌍 Vertaal';
    }
}

// Category selector (zoals in artikelen.php)
function buildCategoryTree(categories) {
    const tree = {};
    categories.forEach(cat => {
        if (!tree[cat.cat_top_sub]) tree[cat.cat_top_sub] = [];
        tree[cat.cat_top_sub].push(cat);
    });
    return tree;
}

const categories = window.categories;
const tree = buildCategoryTree(categories);

function renderSelect(level, parentId, selectedId) {
    const cats = tree[parentId] || [];
    if (!cats.length) return null;

    const select = document.createElement('select');
    select.name = 'cat_level_' + level;
    select.dataset.level = level;
    select.className = 'px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm min-w-32';
    select.innerHTML = '<option value="">-- Kies --</option>' +
        cats.map(cat =>
            `<option value="${cat.cat_id}"${cat.cat_id == selectedId ? ' selected' : ''}>${cat.cat_naam}</option>`
        ).join('');
    return select;
}

function updateSelects(selectedIds = []) {
    const container = document.getElementById('category-selects');
    container.innerHTML = '';
    let parentId = 0;
    let level = 0;
    let lastSelectedId = '';

    while (true) {
        const selectedId = selectedIds[level] || '';
        const select = renderSelect(level, parentId, selectedId);
        if (!select) break;

        container.appendChild(select);
        select.addEventListener('change', function() {
            const ids = Array.from(container.querySelectorAll('select')).slice(0, level + 1).map(s => s.value);
            updateSelects(ids);
            document.getElementById('selected-cat-id').value = ids.filter(Boolean).pop() || '';
        });

        if (!select.value) break;
        parentId = select.value;
        lastSelectedId = select.value;
        level++;
    }

    document.getElementById('selected-cat-id').value = lastSelectedId || '';
}

// Initialize category selector on page load
document.addEventListener('DOMContentLoaded', function() {
    let selectedId = document.getElementById('selected-cat-id').value;
    let path = [];
    if (selectedId) {
        let lookup = {};
        categories.forEach(cat => lookup[cat.cat_id] = cat);
        while (selectedId && lookup[selectedId]) {
            path.unshift(selectedId);
            selectedId = lookup[selectedId].cat_top_sub;
        }
    }
    updateSelects(path);
});