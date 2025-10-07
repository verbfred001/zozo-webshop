// Vertaling functie
async function translateText(text, targetLang) {
    if (!text.trim()) return '';
    console.log(`Vertaling starten: "${text}" naar ${targetLang}`);
    try {
        const apiUrl = `https://api.mymemory.translated.net/get?q=${encodeURIComponent(text)}&langpair=nl|${targetLang}`;
        const response = await fetch(apiUrl, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        return result.responseData.translatedText || '';
    } catch (error) {
        console.error('Vertaling mislukt:', error);
        return getSimpleTranslation(text, targetLang);
    }
}

function getSimpleTranslation(text, targetLang) {
    const translations = {
        'fr': { 'beleg': 'garniture', 'saus': 'sauce', 'grootten': 'tailles', 'extra': 'extra', 'groot': 'grand', 'klein': 'petit', 'medium': 'moyen', 'normaal': 'normal', 'optie': 'option', 'keuze': 'choix', 'grootte': 'taille', 'kleur': 'couleur', 'materiaal': 'matériau' },
        'en': { 'beleg': 'topping', 'saus': 'sauce', 'grootten': 'sizes', 'extra': 'extra', 'groot': 'large', 'klein': 'small', 'medium': 'medium', 'normaal': 'regular', 'optie': 'option', 'keuze': 'choice', 'grootte': 'size', 'kleur': 'color', 'materiaal': 'material' }
    };
    const words = text.toLowerCase().split(' ');
    const translatedWords = words.map(word => {
        return translations[targetLang] && translations[targetLang][word] ? translations[targetLang][word] : word;
    });
    return translatedWords.join(' ');
}

// Modal functies
function openModal(id) { 
    document.getElementById(id).classList.remove('hidden'); 
}

function closeModal(id) { 
    document.getElementById(id).classList.add('hidden'); 
}

// Show functies
function showAddGroupForm() {
    document.getElementById('newoptiongroup_name_nl').value = '';
    if (document.getElementById('newoptiongroup_name_fr')) document.getElementById('newoptiongroup_name_fr').value = '';
    if (document.getElementById('newoptiongroup_name_en')) document.getElementById('newoptiongroup_name_en').value = '';
    document.getElementById('newoptiongroup_type').value = 'select';
    const newAffectsStock = document.getElementById('newoptiongroup_affects_stock');
    if (newAffectsStock) newAffectsStock.checked = false;
    openModal('newoptiongroup-modal');
}

function showAddOptionForm(groupId) {
    document.getElementById('newoption_group_id').value = groupId;
    document.getElementById('newoption_name_nl').value = '';
    if (document.getElementById('newoption_name_fr')) document.getElementById('newoption_name_fr').value = '';
    if (document.getElementById('newoption_name_en')) document.getElementById('newoption_name_en').value = '';
    document.getElementById('newoption_price_delta').value = '0';
    openModal('newoption-modal');
}

// Edit functies (dynamisch gevuld vanuit PHP data)
function showEditGroupForm(groupId) {
    // Deze wordt dynamisch gevuld door PHP in de hoofdpagina
    console.log('Edit group:', groupId);
}

function showEditOptionForm(optionId, groupId) {
    // Deze wordt dynamisch gevuld door PHP in de hoofdpagina
    console.log('Edit option:', optionId, 'in group:', groupId);
}

// Delete functies
function deleteGroup(groupId) {
    if (confirm('Weet je zeker dat je deze optiegroep en ALLE bijbehorende opties wilt verwijderen?\n\nDit kan niet ongedaan worden gemaakt!')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/opties';
        
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_group';
        deleteInput.value = '1';
        
        const groupIdInput = document.createElement('input');
        groupIdInput.type = 'hidden';
        groupIdInput.name = 'group_id';
        groupIdInput.value = groupId;
        
        form.appendChild(deleteInput);
        form.appendChild(groupIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteOption(optionId) {
    if (confirm('Weet je zeker dat je deze optie wilt verwijderen?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/opties';
        
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_option';
        deleteInput.value = '1';
        
        const optionIdInput = document.createElement('input');
        optionIdInput.type = 'hidden';
        optionIdInput.name = 'option_id';
        optionIdInput.value = optionId;
        
        form.appendChild(deleteInput);
        form.appendChild(optionIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Sortable functies
function saveGroupOrder() {
    let order = [];
    document.querySelectorAll('#optiegroepen-lijst .optiegroep').forEach((el, i) => {
        order.push({ id: el.dataset.id, sort_order: i });
    });
    
    fetch('/zozo-admin/includes/optiegroepen_sorteren.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order: order })
    });
}

function saveOptionOrder(groupId) {
    let order = [];
    document.querySelectorAll('#opties-lijst-' + groupId + ' li[data-id]').forEach((el, i) => {
        order.push({ id: el.dataset.id, sort_order: i });
    });
    
    fetch('/zozo-admin/includes/opties_sorteren.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ group_id: groupId, order: order })
    });
}

// Event listeners voor vertaal buttons - NIEUWE OPTIEGROEP
document.addEventListener('DOMContentLoaded', function() {
    // Vertaal buttons - NIEUWE OPTIEGROEP
    

  

    // Sortable initialization - GEFIXED
    if (document.getElementById('optiegroepen-lijst')) {
        new Sortable(document.getElementById('optiegroepen-lijst'), {
            animation: 150,
            handle: '.optiegroep-drag',
            filter: '.text-center',
            onEnd: function(evt) {
                saveGroupOrder();
            }
        });
    }

    // Voor optiegroepen
    if (document.getElementById('optiegroepen-lijst')) {
        new Sortable(document.getElementById('optiegroepen-lijst'), {
            animation: 150,
            handle: '.optiegroep-drag',
            filter: '.text-center',
            onEnd: function(evt) {
                saveGroupOrder();
            }
        });
    }

    // Voor opties
    document.querySelectorAll('[id^="opties-lijst-"]').forEach(function(optiesList) {
        const realOptions = optiesList.querySelectorAll('li[data-id]');
        if (realOptions.length > 0) {
            new Sortable(optiesList, {
                animation: 150,
                handle: '.optie-drag',
                filter: '.italic, .bg-gray-50',
                onEnd: function(evt) {
                    const groupId = optiesList.id.replace('opties-lijst-', '');
                    saveOptionOrder(groupId);
                }
            });
        }
    });

    // Click outside modal to close
    document.querySelectorAll('[id$="-modal"]').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    });
});

// Functie om sortable opnieuw te initialiseren na het toevoegen van een optie - GEFIXED
function reinitializeSortables() {
    document.querySelectorAll('[id^="opties-lijst-"]').forEach(function(optiesList) {
        const groupId = optiesList.id.replace('opties-lijst-', '');
        
        // Verwijder bestaande sortable als die er is
        if (optiesList.sortable) {
            optiesList.sortable.destroy();
        }
        
        // Maak nieuwe sortable als er daadwerkelijke opties zijn (geen text/textarea info)
        const realOptions = optiesList.querySelectorAll('li[data-id]');
        if (realOptions.length > 0) {
            optiesList.sortable = new Sortable(optiesList, {
                animation: 150,
                handle: '.optie-drag',
                filter: '.italic, .bg-gray-50',  // Filter uit de info teksten
                onEnd: function(evt) {
                    saveOptionOrder(groupId);
                }
            });
        }
    });
}

document.body.addEventListener('click', async function(e) {
    // Nieuwe optiegroep FR
    if (e.target && e.target.id === 'newoptiongroup-translate-fr-btn') {
        const nlText = document.getElementById('newoptiongroup_name_nl').value;
        const frField = document.getElementById('newoptiongroup_name_fr');
        if (!nlText.trim()) { alert('Vul eerst de Nederlandse naam in'); return; }
        e.target.disabled = true; e.target.textContent = '⏳ Bezig...';
        try {
            const frTranslation = await translateText(nlText, 'fr');
            if (frTranslation) frField.value = frTranslation;
        } catch (error) { alert('Vertaling mislukt'); }
        finally { e.target.disabled = false; e.target.textContent = '🌍 Vertaal'; }
    }
    // Nieuwe optie FR
    if (e.target && e.target.id === 'newoption-translate-fr-btn') {
        const nlText = document.getElementById('newoption_name_nl').value;
        const frField = document.getElementById('newoption_name_fr');
        if (!nlText.trim()) { alert('Vul eerst de Nederlandse optie naam in'); return; }
        e.target.disabled = true; e.target.textContent = '⏳ Bezig...';
        try {
            const frTranslation = await translateText(nlText, 'fr');
            if (frTranslation) frField.value = frTranslation;
        } catch (error) { alert('Vertaling mislukt'); }
        finally { e.target.disabled = false; e.target.textContent = '🌍 Vertaal'; }
    }
    // Nieuwe optiegroep EN
    if (e.target && e.target.id === 'newoptiongroup-translate-en-btn') {
        const nlText = document.getElementById('newoptiongroup_name_nl').value;
        const enField = document.getElementById('newoptiongroup_name_en');
        if (!nlText.trim()) { alert('Vul eerst de Nederlandse naam in'); return; }
        e.target.disabled = true; e.target.textContent = '⏳ Bezig...';
        try {
            const enTranslation = await translateText(nlText, 'en');
            if (enTranslation) enField.value = enTranslation;
        } catch (error) { alert('Vertaling mislukt'); }
        finally { e.target.disabled = false; e.target.textContent = '🌍 Vertaal'; }
    }
    // Nieuwe optie EN
    if (e.target && e.target.id === 'newoption-translate-en-btn') {
        const nlText = document.getElementById('newoption_name_nl').value;
        const enField = document.getElementById('newoption_name_en');
        if (!nlText.trim()) { alert('Vul eerst de Nederlandse optie naam in'); return; }
        e.target.disabled = true; e.target.textContent = '⏳ Bezig...';
        try {
            const enTranslation = await translateText(nlText, 'en');
            if (enTranslation) enField.value = enTranslation;
        } catch (error) { alert('Vertaling mislukt'); }
        finally { e.target.disabled = false; e.target.textContent = '🌍 Vertaal'; }
    }
    // Edit optiegroep FR
    if (e.target && e.target.id === 'editoptiongroup-translate-fr-btn') {
        const nlText = document.getElementById('editoptiongroup_name_nl').value;
        const frField = document.getElementById('editoptiongroup_name_fr');
        if (!nlText.trim()) { alert('Vul eerst de Nederlandse naam in'); return; }
        e.target.disabled = true; e.target.textContent = '⏳ Bezig...';
        try {
            const frTranslation = await translateText(nlText, 'fr');
            if (frTranslation) frField.value = frTranslation;
        } catch (error) { alert('Vertaling mislukt'); }
        finally { e.target.disabled = false; e.target.textContent = '🌍 Vertaal'; }
    }
    // Edit optie FR
    if (e.target && e.target.id === 'editoption-translate-fr-btn') {
        const nlText = document.getElementById('editoption_name_nl').value;
        const frField = document.getElementById('editoption_name_fr');
        if (!nlText.trim()) { alert('Vul eerst de Nederlandse optie naam in'); return; }
        e.target.disabled = true; e.target.textContent = '⏳ Bezig...';
        try {
            const frTranslation = await translateText(nlText, 'fr');
            if (frTranslation) frField.value = frTranslation;
        } catch (error) { alert('Vertaling mislukt'); }
        finally { e.target.disabled = false; e.target.textContent = '🌍 Vertaal'; }
    }
    // Edit optiegroep EN
    if (e.target && e.target.id === 'editoptiongroup-translate-en-btn') {
        const nlText = document.getElementById('editoptiongroup_name_nl').value;
        const enField = document.getElementById('editoptiongroup_name_en');
        if (!nlText.trim()) { alert('Vul eerst de Nederlandse naam in'); return; }
        e.target.disabled = true; e.target.textContent = '⏳ Bezig...';
        try {
            const enTranslation = await translateText(nlText, 'en');
            if (enTranslation) enField.value = enTranslation;
        } catch (error) { alert('Vertaling mislukt'); }
        finally { e.target.disabled = false; e.target.textContent = '🌍 Vertaal'; }
    }
    // Edit optie EN
    if (e.target && e.target.id === 'editoption-translate-en-btn') {
        const nlText = document.getElementById('editoption_name_nl').value;
        const enField = document.getElementById('editoption_name_en');
        if (!nlText.trim()) { alert('Vul eerst de Nederlandse optie naam in'); return; }
        e.target.disabled = true; e.target.textContent = '⏳ Bezig...';
        try {
            const enTranslation = await translateText(nlText, 'en');
            if (enTranslation) enField.value = enTranslation;
        } catch (error) { alert('Vertaling mislukt'); }
        finally { e.target.disabled = false; e.target.textContent = '🌍 Vertaal'; }
    }
});