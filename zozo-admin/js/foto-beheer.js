function previewImages(input) {
    const preview = document.getElementById('upload-preview');
    const uploadBtn = document.getElementById('upload-btn');
    const maxFiles = 6 - (window.currentImageCount || 0);
    
    preview.innerHTML = '';
    
    if (input.files.length > maxFiles) {
        alert(`Je kunt maximaal ${maxFiles} foto's uploaden.`);
        input.value = '';
        preview.classList.add('hidden');
        uploadBtn.disabled = true;
        return;
    }
    
    if (input.files.length > 0) {
        preview.classList.remove('hidden');
        uploadBtn.disabled = false;
        
        // Toon aantal geselecteerde bestanden
        const dropZone = document.querySelector('[onclick*="foto-input"]');
        const statusDiv = dropZone.querySelector('.text-lg');
        statusDiv.textContent = `${input.files.length} foto${input.files.length > 1 ? 's' : ''} geselecteerd`;
        
        Array.from(input.files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'foto-preview-item'; // Voeg deze klasse toe aan je main.css
                div.innerHTML = `
                    <img src="${e.target.result}" style="width:100%; height:60px; object-fit:cover; border-radius:6px;">
                    <div style="font-size:0.95em; text-align:center; margin-top:0.2em;" title="${file.name}">${file.name}</div>
                    <div style="font-size:0.85em; text-align:center; color:#64748b;">${(file.size / 1024 / 1024).toFixed(1)}MB</div>
                `;
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    } else {
        preview.classList.add('hidden');
        uploadBtn.disabled = true;
        
        // Reset drop zone text
        const dropZone = document.querySelector('[onclick*="foto-input"]');
        if (statusDiv) {
            const statusDiv = dropZone.querySelector('.text-lg');
            statusDiv.textContent = 'Klik hier om foto\'s te selecteren';
        }
    }
}

// Drag & Drop functionaliteit
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.querySelector('[onclick*="foto-input"]');
    const fileInput = document.getElementById('foto-input');
    
    if (dropZone && fileInput) {
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        // Highlight drop zone when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        // Handle dropped files
        dropZone.addEventListener('drop', handleDrop, false);
    }

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight(e) {
        dropZone.classList.add('dropzone-highlight');
    }

    function unhighlight(e) {
        dropZone.classList.remove('dropzone-highlight');
    }

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        fileInput.files = files;
        previewImages(fileInput);
    }
});

async function uploadFotos() {
    const form = document.getElementById('foto-upload-form');
    const formData = new FormData(form);
    
    try {
        const response = await fetch('ajax/upload_fotos.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload(); // Refresh om nieuwe foto's te tonen
        } else {
            alert('Fout bij uploaden: ' + result.message);
        }
    } catch (error) {
        alert('Fout bij uploaden: ' + error.message);
    }
}

async function makePrimary(imageId) {
    try {
        const response = await fetch('ajax/set_primary_foto.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({image_id: imageId, article_id: window.artikel.art_id})
        });
        
        const result = await response.json();
        if (result.success) {
            location.reload();
        }
    } catch (error) {
        alert('Fout: ' + error.message);
    }
}

async function deleteFoto(imageId) {
    if (!confirm('Weet je zeker dat je deze foto wilt verwijderen?')) return;
    
    try {
        const response = await fetch('ajax/delete_foto.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({image_id: imageId})
        });
        
        const result = await response.json();
        if (result.success) {
            location.reload();
        }
    } catch (error) {
        alert('Fout: ' + error.message);
    }
}