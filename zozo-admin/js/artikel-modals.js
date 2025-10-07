// Modal functies
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

// Translate functie - GEBRUIK DEZE
async function getSimpleTranslation(text, targetLang) {
    const response = await fetch('/zozo-admin/includes/translate.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            text: text,
            target_lang: targetLang
        })
    });
    
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const responseText = await response.text();
    console.log('Response:', responseText); // DEBUG
    
    if (!responseText) {
        throw new Error('Lege response van server');
    }
    
    const result = JSON.parse(responseText);
    
    if (result.success) {
        return result.translation;
    } else {
        throw new Error(result.error || 'Vertaling mislukt');
    }
}

async function translateModalField(sourceFieldName, targetFieldName, targetLang, button) {
    try {
        const form = button.closest('form');
        // Helper to find the visible field (prefer non-hidden inputs inside the same form)
        const findField = (nameOrId, withinForm) => {
            const idSel = `#${nameOrId}`;
            const nameSel = `[name="${nameOrId}"]`;
            const nonHiddenNameSel = `${nameSel}:not([type="hidden"])`;
            const root = withinForm || document;

            // Try by id first
            let el = root.querySelector(idSel);
            if (el) return el;

            // Prefer a named non-hidden field
            el = root.querySelector(nonHiddenNameSel);
            if (el) return el;

            // Fallback to any named field
            return root.querySelector(nameSel);
        };

        const sourceField = findField(sourceFieldName, form);
        const targetField = findField(targetFieldName, form);

        if (!sourceField || !targetField) {
            console.error('Velden niet gevonden:', sourceFieldName, targetFieldName);
            alert('Velden niet gevonden');
            return;
        }

        const sourceText = (sourceField.value || '').trim();

        if (!sourceText) {
            alert('Voer eerst tekst in het bronveld in');
            return;
        }

        const originalText = button.innerHTML;
        button.innerHTML = '⏳';
        button.disabled = true;

        try {
            const translation = await getSimpleTranslation(sourceText, targetLang);
            // Minimal normalization: make sure the first character is lowercase
            // so translated article names like "Soupe" become "soupe".
            function lcFirst(str) {
                if (!str || typeof str !== 'string') return str;
                return str.charAt(0).toLowerCase() + str.slice(1);
            }
            targetField.value = lcFirst((translation || '').trim());
        } catch (error) {
            console.error('Vertaling mislukt:', error);
            alert('Vertaling mislukt: ' + error.message);
        } finally {
            button.innerHTML = originalText;
            button.disabled = false;
        }

    } catch (error) {
        console.error('Error in translateModalField:', error);
        alert('Er ging iets mis met de vertaling');
        if (button) {
            button.innerHTML = '🌍';
            button.disabled = false;
        }
    }
}

// Close modal on outside click
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[id$="-modal"]').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    });
});