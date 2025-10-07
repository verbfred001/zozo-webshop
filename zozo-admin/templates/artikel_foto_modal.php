<!-- Modal: Foto beheer -->
<!-- Modal: Foto beheer -->
<div id="foto-modal" class="modal-overlay hidden">
    <div class="modal-center">
        <div class="modal-box" style="max-width: 700px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Foto's beheren</h2>
                    <button type="button" onclick="closeModal('foto-modal'); window.location.reload();" class="modal-close">&times;</button>
                </div>

                <!-- Upload sectie -->
                <div class="form-section-box form-section-box--mb-small" style="border: 1px solid #bfdbfe; background: #eff6ff;">
                    <h3 class="form-section-title" style="font-size:1rem; margin-bottom:0.5em;">Nieuwe foto's uploaden</h3>
                    <form method="POST" enctype="multipart/form-data" action="">
                        <input type="hidden" name="action" value="upload_fotos">
                        <input type="hidden" name="article_id" value="<?= $art_id ?>">

                        <div class="form-section-box" style="border: 2px dashed #d1d5db; background: #fff; text-align: center; cursor: pointer; padding: 1rem;"
                            onclick="document.getElementById('foto-input').click()">
                            <div style="font-size:2rem; color:#9ca3af;">📸</div>
                            <div style="font-weight:500;">Klik om foto's te selecteren</div>
                            <div style="font-size:0.9em; color:#64748b;" id="max-files-text">Max <?= 6 - count($images ?? []) ?> foto's</div>
                            <div class="btn btn--sub" style="display:inline-block; margin-top:0.5em; padding:0.3em 1em;">📁 Kiezen</div>
                        </div>

                        <input type="file" name="fotos[]" multiple accept="image/*"
                            id="foto-input" style="display:none;">

                        <div id="selected-files" class="form-section-box--mb-small" style="display:none; font-size:0.95em; background:#eff6ff;"></div>

                        <button type="submit" class="btn btn--add" style="width:100%; margin-top:1em;">
                            📤 Uploaden
                        </button>
                    </form>
                </div>

                <!-- Bestaande foto's -->
                <?php if (!empty($images)): ?>
                    <div class="form-section-box" style="background: #f9fafb;">
                        <h3 class="form-section-title" style="font-size:1rem; margin-bottom:0.5em;">Bestaande foto's <span style="font-size:0.9em; color:#64748b;">(sleep om volgorde te wijzigen)</span></h3>
                        <div id="sortable-fotos" style="display: grid; gap: 0.5rem; grid-template-columns: repeat(3, 1fr); position: relative;">
                            <style>
                                @media (min-width: 640px) {
                                    #sortable-fotos {
                                        grid-template-columns: repeat(4, 1fr) !important;
                                    }
                                }

                                @media (min-width: 768px) {
                                    #sortable-fotos {
                                        grid-template-columns: repeat(5, 1fr) !important;
                                    }
                                }

                                @media (min-width: 1024px) {
                                    #sortable-fotos {
                                        grid-template-columns: repeat(6, 1fr) !important;
                                    }
                                }
                            </style>
                            <?php foreach ($images as $index => $image): ?>
                                <div class="form-section-box sortable-item" style="background:#fff; border:1px solid #e5e7eb; padding:0.5em; box-shadow:0 2px 8px rgba(0,0,0,0.06); cursor:move; position:relative;"
                                    data-image-id="<?= $image['id'] ?>"
                                    data-original-number="<?= $index + 1 ?>"
                                    draggable="true">
                                    <img src="/upload/<?= htmlspecialchars($image['image_name']) ?>"
                                        style="width:100%; height:60px; object-fit:cover; border-radius:6px; margin-bottom:0.3em;">
                                    <div class="flex-between-row" style="font-size:0.95em;">
                                        <span class="foto-label">Foto <?= $index + 1 ?></span>
                                        <button onclick="deleteImage(<?= $image['id'] ?>)"
                                            class="btn btn--gray" style="padding:0.2em 0.6em; font-size:1em;">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <!-- Hoofdfoto badge -->
                            <div style="position: absolute; top: 4px; left: 4px; background: #10b981; color: white; font-size: 0.75rem; padding: 1px 4px; border-radius: 0.25rem; z-index: 10; pointer-events: none;" class="hoofdfoto-badge">
                                Hoofdfoto
                            </div>
                        </div>
                        <div style="margin-top:0.7em; font-size:0.95em; color:#64748b; text-align:center;">
                            💡 Sleep foto's om volgorde te wijzigen
                        </div>
                    </div>
                <?php endif; ?>

                <div class="modal-actions" style="margin-top:2em;">
                    <button type="button" onclick="closeModal('foto-modal'); window.location.reload();" class="btn btn--gray">
                        Sluiten
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    // Foto sortable functionaliteit (zoals je andere sortables)
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('sortable-fotos');
        if (!container) return;

        let draggedElement = null;
        let originalOrder = [];

        // Maak alle items sortable
        const items = container.querySelectorAll('.sortable-item');
        items.forEach(item => {
            item.addEventListener('dragstart', handleDragStart);
            item.addEventListener('dragover', handleDragOver);
            item.addEventListener('drop', handleDrop);
            item.addEventListener('dragend', handleDragEnd);
        });

        function handleDragStart(e) {
            draggedElement = this;
            originalOrder = Array.from(container.children);
            this.style.opacity = '0.5';
        }

        function handleDragOver(e) {
            e.preventDefault();
        }

        function handleDrop(e) {
            e.preventDefault();

            if (draggedElement !== this) {
                const allItems = Array.from(container.children);
                const draggedIndex = allItems.indexOf(draggedElement);
                const targetIndex = allItems.indexOf(this);

                if (draggedIndex < targetIndex) {
                    container.insertBefore(draggedElement, this.nextSibling);
                } else {
                    container.insertBefore(draggedElement, this);
                }

                // Update positie nummers
                updatePositionNumbers();

                // Verstuur naar server (gewone form submit)
                submitNewOrder();
            }
        }

        function handleDragEnd(e) {
            this.style.opacity = '';
            draggedElement = null;
        }

        function updatePositionNumbers() {
            const items = container.querySelectorAll('.sortable-item');
            items.forEach((item, index) => {
                // Update foto label to show current position
                const fotoLabel = item.querySelector('.foto-label');
                if (fotoLabel) {
                    fotoLabel.textContent = `Foto ${index + 1}`;
                }
            });
        }

        function submitNewOrder() {
            const items = container.querySelectorAll('.sortable-item');
            const imageIds = Array.from(items).map(item => item.dataset.imageId);

            // Update alleen de positie nummers visueel
            updatePositionNumbers();

            // Verstuur naar server via hidden form in de achtergrond
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            form.target = 'hidden-iframe'; // Verstuur naar verborgen iframe

            // Voeg alle velden toe...
            const actionField = document.createElement('input');
            actionField.type = 'hidden';
            actionField.name = 'action';
            actionField.value = 'update_photo_order';
            form.appendChild(actionField);

            const articleField = document.createElement('input');
            articleField.type = 'hidden';
            articleField.name = 'article_id';
            articleField.value = '<?= $art_id ?>'; // Ook hier aanpassen
            form.appendChild(articleField);

            imageIds.forEach((imageId, index) => {
                const field = document.createElement('input');
                field.type = 'hidden';
                field.name = `image_ids[${index}]`;
                field.value = imageId;
                form.appendChild(field);
            });

            document.body.appendChild(form);

            // Maak verborgen iframe als die niet bestaat
            if (!document.getElementById('hidden-iframe')) {
                const iframe = document.createElement('iframe');
                iframe.id = 'hidden-iframe';
                iframe.name = 'hidden-iframe';
                iframe.style.display = 'none';
                document.body.appendChild(iframe);
            }

            form.submit();

            // Verwijder form na submit
            setTimeout(() => document.body.removeChild(form), 100);
        }
    });

    function deleteImage(imageId) {
        if (confirm('Weet je zeker dat je deze foto wilt verwijderen?')) {
            // AJAX request in plaats van form submit
            const formData = new FormData();
            formData.append('action', 'delete_foto');
            formData.append('image_id', imageId);
            formData.append('article_id', '<?= $art_id ?>');

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Verwijder foto uit DOM ongeacht response status
                    const photoElement = document.querySelector(`[data-image-id="${imageId}"]`);
                    if (photoElement) {
                        photoElement.remove();

                        // Update positie nummers handmatig
                        const container = document.getElementById('sortable-fotos');
                        if (container) {
                            const items = container.querySelectorAll('.sortable-item');
                            items.forEach((item, index) => {
                                const fotoLabel = item.querySelector('.foto-label');
                                if (fotoLabel) {
                                    fotoLabel.textContent = `Foto ${index + 1}`;
                                }
                            });

                            // Update max files tekst
                            const currentCount = items.length;
                            const maxFiles = 6 - currentCount;
                            const maxFilesText = document.getElementById('max-files-text');
                            if (maxFilesText) {
                                maxFilesText.textContent = `Max ${maxFiles} foto's`;
                            }

                            // Update window.currentImageCount
                            window.currentImageCount = currentCount;
                        }

                        console.log('Foto verwijderd!');
                    } else {
                        console.log('Foto element niet gevonden');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Ook hier proberen te verwijderen
                    const photoElement = document.querySelector(`[data-image-id="${imageId}"]`);
                    if (photoElement) {
                        photoElement.remove();
                        console.log('Foto verwijderd ondanks error!');
                    } else {
                        alert('Er is een fout opgetreden bij het verwijderen van de foto.');
                    }
                });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('foto-input');
        fileInput.addEventListener('change', function() {
            console.log('File input changed!'); // Debug

            // Direct de code hier uitvoeren in plaats van functie aanroepen
            const input = this;
            const selectedDiv = document.getElementById('selected-files');
            const maxFiles = 6 - (window.currentImageCount || <?= count($images ?? []) ?>);

            console.log('Files selected:', input.files.length);
            console.log('Selected div:', selectedDiv);
            console.log('Max files:', maxFiles);

            if (input.files.length > maxFiles) {
                alert(`Je kunt maximaal ${maxFiles} foto's uploaden.`);
                input.value = '';
                selectedDiv.innerHTML = '';
                selectedDiv.style.display = 'none';
                return;
            }

            if (input.files.length > 0) {
                console.log('Showing files...');
                selectedDiv.innerHTML = `<strong>${input.files.length} foto${input.files.length > 1 ? 's' : ''} geselecteerd:</strong><br>` +
                    Array.from(input.files).map(file => `• ${file.name} (${(file.size / 1024 / 1024).toFixed(1)}MB)`).join('<br>');
                selectedDiv.style.display = 'block';
                console.log('Files div content:', selectedDiv.innerHTML);
            } else {
                selectedDiv.innerHTML = '';
                selectedDiv.style.display = 'none';
            }
        });
    });
</script>