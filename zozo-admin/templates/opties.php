// Vertaling functie (gekopieerd van categorieen.php)
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
'fr': { 'optie': 'option', 'keuze': 'choix', 'grootte': 'taille' },
'en': { 'optie': 'option', 'keuze': 'choice', 'grootte': 'size' }
};
const words = text.toLowerCase().split(' ');
const translatedWords = words.map(word => {
return translations[targetLang] && translations[targetLang][word] ? translations[targetLang][word] : word;
});
return translatedWords.join(' ');
}

// Modal functies
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

// Show functies
function showAddGroupForm() {
document.getElementById('newoptiongroup_name_nl').value = '';
// Reset andere velden...
openModal('newoptiongroup-modal');
}

function showAddOptionForm(groupId) {
document.getElementById('newoption_group_id').value = groupId;
document.getElementById('newoption_name_nl').value = '';
// Reset andere velden...
openModal('newoption-modal');
}

// Edit functies (met PHP data)
// DELETE functies
// Sortable functies
// Vertaal button event listeners

// Sortable initialization
new Sortable(document.getElementById('optiegroepen-lijst'), {
animation: 150, handle: '.cursor-move', filter: '.text-center',
onEnd: function(evt) { saveGroupOrder(); }
});

// Click outside modal to close
document.querySelectorAll('[id$="-modal"]').forEach(modal => {
modal.addEventListener('click', function(e) {
if (e.target === this) this.classList.add('hidden');
});
});