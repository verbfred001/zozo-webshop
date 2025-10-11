// Verbeterde JavaScript voor sortable
        document.addEventListener('DOMContentLoaded', function() {
            // Drag & drop sorteren voor hoofdcategorieën
            new Sortable(document.getElementById('cat-list'), {
                animation: 150,
                handle: '.cat-block__drag',
                onEnd: saveOrder
            });

            // Drag & drop sorteren voor alle subcategorie-lijsten
            function initSubcatSortables() {
                document.querySelectorAll('.subcat-list').forEach(function(subcatList) {
                    new Sortable(subcatList, {
                        animation: 150,
                        handle: '.cat-block__drag',
                        onEnd: saveOrder
                    });
                });
            }

            // Initialiseer subcategorie sortables
            initSubcatSortables();

            // Functie om de volgorde op te slaan
            function saveOrder() {
                let order = [];

                function processList(list, parentId = 0) {
                    list.querySelectorAll(':scope > .cat-block').forEach((block, i) => {
                        order.push({
                            id: block.dataset.id,
                            volgorde: i + 1,
                            parent: parentId
                        });
                        // Zoek een directe subcat-list (indien aanwezig)
                        let subcatList = block.querySelector(':scope > .subcat-list');
                        if (subcatList) {
                            processList(subcatList, block.dataset.id);
                        }
                    });
                }

                processList(document.getElementById('cat-list'));

                fetch('/zozo-admin/includes/categorieen_opslaan_volgorde.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        order: order
                    })
                });
            }

            // Maak saveOrder globaal beschikbaar
            window.saveOrder = saveOrder;
        });

        // Toon modal voor toevoegen hoofdcategorie of subcategorie
        function showAddForm(parentId = 0) {
            document.getElementById('cat_id').value = '';
            document.getElementById('cat_top_sub').value = parentId;
            document.getElementById('cat_naam').value = '';

            if (window.activeLanguages && window.activeLanguages.includes('fr')) {
                if (document.getElementById('cat_naam_fr')) document.getElementById('cat_naam_fr').value = '';
                if (document.getElementById('cat_afkorting_fr')) document.getElementById('cat_afkorting_fr').value = '';
            }
            if (window.activeLanguages && window.activeLanguages.includes('en')) {
                if (document.getElementById('cat_naam_en')) document.getElementById('cat_naam_en').value = '';
                if (document.getElementById('cat_afkorting_en')) document.getElementById('cat_afkorting_en').value = '';
            }

            document.getElementById('cat_afkorting').value = '';
            document.getElementById('verborgen').value = 'nee';
            document.getElementById('cat_volgorde').value = '999';
            document.getElementById('modal-title').textContent = parentId == 0 ? "Hoofdcategorie toevoegen" : "Subcategorie toevoegen";
            document.getElementById('cat-form-modal').classList.remove('hidden');
        }

        function showEditForm(catId) {
            fetch('/zozo-admin/includes/categorieen_get.php?id=' + catId)
                .then(resp => resp.json())
                .then(data => {
                    document.getElementById('cat_id').value = data.cat_id ?? '';
                    document.getElementById('cat_top_sub').value = data.cat_top_sub ?? '';
                    document.getElementById('cat_naam').value = data.cat_naam ?? '';

                    // Alleen actieve talen invullen
                    if (window.activeLanguages && window.activeLanguages.includes('fr')) {
                        if (document.getElementById('cat_naam_fr')) document.getElementById('cat_naam_fr').value = data.cat_naam_fr ?? '';
                    }
                    if (window.activeLanguages && window.activeLanguages.includes('en')) {
                        if (document.getElementById('cat_naam_en')) document.getElementById('cat_naam_en').value = data.cat_naam_en ?? '';
                    }

                    // Hidden afkorting velden invullen
                    document.getElementById('cat_afkorting').value = data.cat_afkorting ?? '';
                    if (window.activeLanguages && window.activeLanguages.includes('fr')) {
                        if (document.getElementById('cat_afkorting_fr')) document.getElementById('cat_afkorting_fr').value = data.cat_afkorting_fr ?? '';
                    }
                    if (window.activeLanguages && window.activeLanguages.includes('en')) {
                        if (document.getElementById('cat_afkorting_en')) document.getElementById('cat_afkorting_en').value = data.cat_afkorting_en ?? '';
                    }

                    document.getElementById('verborgen').value = data.verborgen ?? 'nee';
                    document.getElementById('cat_volgorde').value = data.cat_volgorde ?? '999';
                    document.getElementById('modal-title').textContent = data.cat_top_sub == 0 ? "Hoofdcategorie bewerken" : "Subcategorie bewerken";
                    document.getElementById('cat-form-modal').classList.remove('hidden');

                    // Genereer slugs op basis van de geladen namen
                    generateAllSlugs();
                })
                .catch(error => {
                    alert("Kan categorie niet ophalen!");
                    console.error(error);
                });
        }

        // Auto-translate functie met MyMemory API (geen CORS problemen)
        async function translateText(text, targetLang) {
            if (!text.trim()) return '';

            try {
                // MyMemory API - geen CORS problemen
                const apiUrl = `https://api.mymemory.translated.net/get?q=${encodeURIComponent(text)}&langpair=nl|${targetLang}`;
                const response = await fetch(apiUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                // MyMemory heeft een andere response structuur
                return result.responseData.translatedText || '';
            } catch (error) {
                console.error('Vertaling mislukt:', error);

                // Fallback naar simpele woordenboek vertalingen
                return getSimpleTranslation(text, targetLang);
            }
        }

        // Fallback woordenboek voor als API niet werkt
        function getSimpleTranslation(text, targetLang) {
            const translations = {
                'fr': {
                    'heren': 'hommes',
                    'dames': 'femmes',
                    'kinderen': 'enfants',
                    'schoenen': 'chaussures',
                    'broeken': 'pantalons',
                    'hemden': 'chemises',
                    'shirts': 'chemises',
                    'accessoires': 'accessoires',
                    'tassen': 'sacs',
                    'riemen': 'ceintures',
                    'hoeden': 'chapeaux',
                    'sieraden': 'bijoux',
                    'jassen': 'manteaux',
                    'truien': 'pulls',
                    'rokken': 'jupes',
                    'jurken': 'robes',
                    'ondergoed': 'sous-vêtements',
                    'sokken': 'chaussettes',
                    'brillen': 'lunettes',
                    'horloges': 'montres'
                },
                'en': {
                    'heren': 'men',
                    'dames': 'women',
                    'kinderen': 'children',
                    'schoenen': 'shoes',
                    'broeken': 'pants',
                    'hemden': 'shirts',
                    'shirts': 'shirts',
                    'accessoires': 'accessories',
                    'tassen': 'bags',
                    'riemen': 'belts',
                    'hoeden': 'hats',
                    'sieraden': 'jewelry',
                    'jassen': 'jackets',
                    'truien': 'sweaters',
                    'rokken': 'skirts',
                    'jurken': 'dresses',
                    'ondergoed': 'underwear',
                    'sokken': 'socks',
                    'brillen': 'glasses',
                    'horloges': 'watches'
                }
            };

            const words = text.toLowerCase().split(' ');
            const translatedWords = words.map(word => {
                return translations[targetLang] && translations[targetLang][word] ? translations[targetLang][word] : word;
            });

            const result = translatedWords.join(' ');
            return result;
        }

        // Slug generatie voor Nederlands (blijft hetzelfde)
        document.getElementById('cat_naam').addEventListener('input', function() {
            let text = this.value;
            let slug = generateSlug(text);
            document.getElementById('cat_afkorting').value = slug;
        });

        // Vertaling via buttons - veel betrouwbaarder
        if (window.activeLanguages && window.activeLanguages.includes('fr')) {
            if (document.getElementById('translate-fr-btn')) {
                document.getElementById('translate-fr-btn').addEventListener('click', async function() {
                    const nlText = document.getElementById('cat_naam').value;
                    const frField = document.getElementById('cat_naam_fr');

                    if (!nlText.trim()) {
                        alert('Vul eerst de Nederlandse naam in');
                        return;
                    }

                    // Button feedback
                    this.disabled = true;
                    this.textContent = '⏳ Bezig...';

                    try {
                        const frTranslation = await translateText(nlText, 'fr');
                        if (frTranslation) {
                            frField.value = frTranslation;
                            document.getElementById('cat_afkorting_fr').value = generateSlug(frTranslation);
                        }
                    } catch (error) {
                        console.error('Franse vertaling mislukt:', error);
                        alert('Vertaling mislukt');
                    } finally {
                        this.disabled = false;
                        this.textContent = '🌍 Vertaal';
                    }
                });
            }

            // Franse slug generatie bij handmatig typen
            if (document.getElementById('cat_naam_fr')) {
                document.getElementById('cat_naam_fr').addEventListener('input', function() {
                    let slug = generateSlug(this.value);
                    if (document.getElementById('cat_afkorting_fr')) {
                        document.getElementById('cat_afkorting_fr').value = slug;
                    }
                });
            }
        }

        if (window.activeLanguages && window.activeLanguages.includes('en')) {
            // Engelse vertaal-knop
            if (document.getElementById('translate-en-btn')) {
                document.getElementById('translate-en-btn').addEventListener('click', async function() {
                    const nlText = document.getElementById('cat_naam').value;
                    const enField = document.getElementById('cat_naam_en');

                    if (!nlText.trim()) {
                        alert('Vul eerst de Nederlandse naam in');
                        return;
                    }

                    // Button feedback
                    this.disabled = true;
                    this.textContent = '⏳ Bezig...';

                    try {
                        const enTranslation = await translateText(nlText, 'en');
                        if (enTranslation) {
                            enField.value = enTranslation;
                            document.getElementById('cat_afkorting_en').value = generateSlug(enTranslation);
                        }
                    } catch (error) {
                        console.error('Engelse vertaling mislukt:', error);
                        alert('Vertaling mislukt');
                    } finally {
                        this.disabled = false;
                        this.textContent = '🌍 Vertaal';
                    }
                });
            }

            // Engelse slug generatie bij handmatig typen
            if (document.getElementById('cat_naam_en')) {
                document.getElementById('cat_naam_en').addEventListener('input', function() {
                    let slug = generateSlug(this.value);
                    if (document.getElementById('cat_afkorting_en')) {
                        document.getElementById('cat_afkorting_en').value = slug;
                    }
                });
            }
        }

        // Herbruikbare functie voor slug generatie (ongewijzigd)
        function generateSlug(text) {
            return text
                .normalize("NFD") // splits accenttekens van letters
                .replace(/[\u0300-\u036f]/g, "") // verwijder accenttekens
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '') // alleen letters, cijfers, spaties, streepjes
                .replace(/\s+/g, '-') // spaties naar streepjes
                .replace(/-+/g, '-') // dubbele streepjes naar enkel
                .replace(/^-+|-+$/g, ''); // verwijder streepjes aan begin/eind
        }

        // Modal sluiten
        function closeForm() {
            document.getElementById('cat-form-modal').classList.add('hidden');
        }

        // Formulier submit (met fetch)
        document.getElementById('cat-edit-form').addEventListener('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            fetch('/zozo-admin/includes/categorieen_opslaan.php', {
                    method: 'POST',
                    body: formData
                }).then(resp => resp.json())
                .then(data => location.reload());
        });

        function deleteCategory(catId) {
            if (confirm("Weet je zeker dat je deze categorie wilt verwijderen?")) {
                fetch('/zozo-admin/includes/categorieen_verwijder.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'id=' + encodeURIComponent(catId)
                    })
                    .then(resp => resp.json())
                    .then(data => location.reload());
            }
        }

        // Click outside modal to close
        document.getElementById('cat-form-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeForm();
            }
        });

        document.body.addEventListener('click', async function(e) {
            if (e.target && e.target.classList.contains('translate-btn')) {
                e.preventDefault();
                const srcId = e.target.getAttribute('data-src');
                const destId = e.target.getAttribute('data-dest');
                const lang = e.target.getAttribute('data-lang');
                const src = document.getElementById(srcId);
                const dest = document.getElementById(destId);
                if (!src.value.trim()) {
                    alert('Vul eerst de Nederlandse naam in');
                    return;
                }
                e.target.disabled = true;
                e.target.textContent = '⏳ Bezig...';
                try {
                    const translation = await translateText(src.value, lang);
                    if (translation) dest.value = translation;
                } catch (error) {
                    alert('Vertaling mislukt');
                } finally {
                    e.target.disabled = false;
                    e.target.textContent = 'vertaal';
                }
            }
        });

        // Bijvoorbeeld in categorieen.js, na het vullen van de velden:
        function openEditModal(categoryData) {
            // ... vul alle velden met bestaande data ...

            // Genereer slugs op basis van de geladen namen
            generateAllSlugs();
        }