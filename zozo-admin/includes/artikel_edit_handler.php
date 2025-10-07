<?php
// Include centrale functies
require_once(__DIR__ . '/functions.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // zorg dat art_id beschikbaar is
    $art_id = intval($_POST['art_id'] ?? $_GET['id'] ?? 0);
    // (optioneel) debuglog tijdelijk:
    // @file_put_contents(__DIR__.'/debug_artikel_edit.log', date('c')." POST:".json_encode($_POST)." computed art_id: ".$art_id."\n", FILE_APPEND);

    $fieldsToUpdate = [];
    $values = [];
    $types = '';

    // Naam update - MET AUTOMATISCHE SLUG GENERATIE VOOR ALLE TALEN
    if (isset($_POST['art_naam'])) {
        $fieldsToUpdate[] = 'art_naam = ?';
        $values[] = $_POST['art_naam'];
        $types .= 's';

        // AUTO-GENEREER AFKORTING uit Nederlandse naam (zoals categorieën)
        if (!empty($_POST['art_naam'])) {
            $autoSlug = generateSlug($_POST['art_naam']);
            $fieldsToUpdate[] = 'art_afkorting = ?';
            $values[] = $autoSlug;
            $types .= 's';
        }

        // Franse naam en slug
        if (isset($_POST['art_naam_fr'])) {
            $fieldsToUpdate[] = 'art_naam_fr = ?';
            $values[] = $_POST['art_naam_fr'];
            $types .= 's';

            // AUTO-GENEREER Franse afkorting
            if (!empty($_POST['art_naam_fr'])) {
                $autoSlugFr = generateSlug($_POST['art_naam_fr']);
                $fieldsToUpdate[] = 'art_afkorting_fr = ?';
                $values[] = $autoSlugFr;
                $types .= 's';
            }
        }

        // Engelse naam en slug
        if (isset($_POST['art_naam_en'])) {
            $fieldsToUpdate[] = 'art_naam_en = ?';
            $values[] = $_POST['art_naam_en'];
            $types .= 's';

            // AUTO-GENEREER Engelse afkorting
            if (!empty($_POST['art_naam_en'])) {
                $autoSlugEn = generateSlug($_POST['art_naam_en']);
                $fieldsToUpdate[] = 'art_afkorting_en = ?';
                $values[] = $autoSlugEn;
                $types .= 's';
            }
        }
    }

    // Kenmerk update
    if (isset($_POST['art_kenmerk'])) {
        $fieldsToUpdate[] = 'art_kenmerk = ?';
        $values[] = $_POST['art_kenmerk'];
        $types .= 's';

        if (isset($_POST['art_kenmerk_fr'])) {
            $fieldsToUpdate[] = 'art_kenmerk_fr = ?';
            $values[] = $_POST['art_kenmerk_fr'];
            $types .= 's';
        }
        if (isset($_POST['art_kenmerk_en'])) {
            $fieldsToUpdate[] = 'art_kenmerk_en = ?';
            $values[] = $_POST['art_kenmerk_en'];
            $types .= 's';
        }
    }

    // Bepaal juiste waarde voor art_kostprijs (excl. btw) met 10 decimalen
    if (isset($_POST['kostprijs_excl']) && $_POST['kostprijs_excl'] !== '') {
        $kostprijs_excl = (float)str_replace(',', '.', $_POST['kostprijs_excl']);
    } elseif (isset($_POST['kostprijs_incl']) && $_POST['kostprijs_incl'] !== '' && isset($_POST['btw_tarief'])) {
        $btw = (float)$_POST['btw_tarief'];
        $kostprijs_excl = (float)str_replace(',', '.', $_POST['kostprijs_incl']) / (1 + $btw / 100);
    } else {
        $kostprijs_excl = null;
    }

    if ($kostprijs_excl !== null) {
        $fieldsToUpdate[] = 'art_kostprijs = ?';
        // Format met 10 decimalen
        $values[] = number_format($kostprijs_excl, 10, '.', '');
        $types .= 'd';
    }

    if (isset($_POST['oudeprijs_excl']) && $_POST['oudeprijs_excl'] !== '') {
        $oudeprijs_excl = (float)str_replace(',', '.', $_POST['oudeprijs_excl']);
    } elseif (isset($_POST['oudeprijs_incl']) && $_POST['oudeprijs_incl'] !== '' && isset($_POST['btw_tarief'])) {
        $btw = (float)$_POST['btw_tarief'];
        $oudeprijs_excl = (float)str_replace(',', '.', $_POST['oudeprijs_incl']) / (1 + $btw / 100);
    } else {
        $oudeprijs_excl = null;
    }

    if ($oudeprijs_excl !== null) {
        $fieldsToUpdate[] = 'art_oudeprijs = ?';
        $values[] = number_format($oudeprijs_excl, 10, '.', '');
        $types .= 'd';
    }

    if (isset($_POST['btw_tarief'])) {
        $fieldsToUpdate[] = 'art_BTWtarief = ?';
        $values[] = intval($_POST['btw_tarief']);
        $types .= 'i';
    }

    // VOORRAAD UPDATE - art_aantal en art_levertijd kunnen onafhankelijk worden bijgewerkt
    if (isset($_POST['art_aantal'])) {
        $fieldsToUpdate[] = 'art_aantal = ?';
        $values[] = intval($_POST['art_aantal']);
        $types .= 'i';
    }

    if (isset($_POST['art_levertijd'])) {
        $fieldsToUpdate[] = 'art_levertijd = ?';
        $values[] = intval($_POST['art_levertijd']);
        $types .= 'i';
    }

    // Backwards compatibility: if legacy clients send art_online ('ja'/'nee'), map it to art_weergeven
    if (isset($_POST['art_online']) && !isset($_POST['art_weergeven'])) {
        $valW = ($_POST['art_online'] === 'ja') ? 1 : 0;
        $fieldsToUpdate[] = 'art_weergeven = ?';
        $values[] = $valW;
        $types .= 'i';
    }

    /* Normalize checkbox/flag fields so we never try to insert strings like 'nee'
       into integer columns. We store 1 for checked/yes, 0 for unchecked/no. */
    $shouldHandleFlags = isset($_POST['art_indekijker']) || isset($_POST['art_suggestie']) || isset($_POST['art_weergeven']);
    if ($shouldHandleFlags) {
        // art_indekijker (checkbox sends value when checked)
        $fieldsToUpdate[] = 'art_indekijker = ?';
        $valInde = 0;
        if (isset($_POST['art_indekijker'])) {
            $raw = $_POST['art_indekijker'];
            if ($raw === '1' || $raw === 1 || strtolower((string)$raw) === 'ja') {
                $valInde = 1;
            }
        }
        $values[] = $valInde;
        $types .= 'i';

        // art_suggestie (checkbox used to mark suggestion)
        $fieldsToUpdate[] = 'art_suggestie = ?';
        $valSugg = 0;
        if (isset($_POST['art_suggestie'])) {
            $raw = $_POST['art_suggestie'];
            if ($raw === '1' || $raw === 1 || strtolower((string)$raw) === 'ja') {
                $valSugg = 1;
            }
        }
        $values[] = $valSugg;
        $types .= 'i';

        // art_weergeven (hidden input / checkbox-like control)
        if (isset($_POST['art_weergeven'])) {
            $fieldsToUpdate[] = 'art_weergeven = ?';
            $raw = $_POST['art_weergeven'];
            $valW = 0;
            if ($raw === '1' || $raw === 1 || strtolower((string)$raw) === 'ja') {
                $valW = 1;
            }
            $values[] = $valW;
            $types .= 'i';
        }
    }

    // CATEGORIE UPDATE - TOEGEVOEGD
    if (isset($_POST['art_catID'])) {
        $fieldsToUpdate[] = 'art_catID = ?';
        $values[] = intval($_POST['art_catID']);
        $types .= 'i';
    }

    // OMSCHRIJVINGEN
    if (isset($_POST['art_omschrijving'])) {
        $fieldsToUpdate[] = 'art_omschrijving = ?';
        $values[] = $_POST['art_omschrijving'];
        $types .= 's';
    }
    if (isset($_POST['art_omschrijving_fr'])) {
        $fieldsToUpdate[] = 'art_omschrijving_fr = ?';
        $values[] = $_POST['art_omschrijving_fr'];
        $types .= 's';
    }
    if (isset($_POST['art_omschrijving_en'])) {
        $fieldsToUpdate[] = 'art_omschrijving_en = ?';
        $values[] = $_POST['art_omschrijving_en'];
        $types .= 's';
    }

    // Voer update uit
    if (!empty($fieldsToUpdate)) {
        $sql = "UPDATE products SET " . implode(', ', $fieldsToUpdate) . " WHERE art_id = ?";
        $values[] = $art_id;
        $types .= 'i';

        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$values);

            if ($stmt->execute()) {
                $success = "Wijzigingen succesvol opgeslagen!";
                // Redirect om dubbele POST te voorkomen
                // Bepaal anchor op basis van het bewerkte veld
                $anchor = '';
                if (isset($_POST['kostprijs_excl']) || isset($_POST['oudeprijs_excl']) || isset($_POST['btw_tarief'])) {
                    $anchor = 'anchor-prijzen';
                } elseif (isset($_POST['art_aantal'])) {
                    $anchor = 'anchor-voorraad';
                } elseif (isset($_POST['art_weergeven']) || isset($_POST['art_online'])) {
                    $anchor = 'anchor-status';
                } elseif (isset($_POST['art_catID'])) {
                    $anchor = 'anchor-categorie';
                } elseif (isset($_POST['art_omschrijving']) || isset($_POST['art_omschrijving_fr']) || isset($_POST['art_omschrijving_en'])) {
                    $anchor = 'anchor-omschrijving';
                }

                // redirect altijd terug naar de juiste detail pagina
                header('Location: /admin/detail?id=' . $art_id . '&zoek=' . urlencode($_POST['zoek'] ?? $_GET['zoek'] ?? '') . '&cat=' . urlencode($_POST['cat'] ?? $_GET['cat'] ?? '') . '&success=1');
                exit;
            } else {
                $error = "Fout bij het opslaan: " . $stmt->error;
            }
        } else {
            $error = "Database fout: " . $mysqli->error;
        }
    }
}

// Success message tonen
if (isset($_GET['success'])) {
    $success = "Wijzigingen succesvol opgeslagen!";
}

// Foto upload handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'upload_fotos') {
        $articleId = intval($_POST['article_id']);
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/';

        // Check of upload directory bestaat
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Check hoeveel foto's er al zijn
        $countQuery = $mysqli->query("SELECT COUNT(*) as count FROM product_images WHERE product_id = $articleId");
        $existingCount = $countQuery->fetch_assoc()['count'];

        $uploadedCount = 0;
        $errors = [];

        if (isset($_FILES['fotos']) && is_array($_FILES['fotos']['tmp_name'])) {
            foreach ($_FILES['fotos']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['fotos']['error'][$key] === UPLOAD_ERR_OK) {
                    $originalName = $_FILES['fotos']['name'][$key];
                    $fileSize = $_FILES['fotos']['size'][$key];

                    // Validaties
                    if ($fileSize > 5 * 1024 * 1024) { // 5MB
                        $errors[] = "Bestand $originalName is te groot (max 5MB)";
                        continue;
                    }

                    $imageInfo = getimagesize($tmpName);
                    if (!$imageInfo) {
                        $errors[] = "Bestand $originalName is geen geldige afbeelding";
                        continue;
                    }

                    // Genereer unieke bestandsnaam
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                    if (!in_array($extension, $allowedExtensions)) {
                        $errors[] = "Bestand $originalName heeft een niet-toegestaan bestandstype";
                        continue;
                    }

                    $newFileName = 'art_' . $articleId . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
                    $targetPath = $uploadDir . $newFileName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        // Bepaal volgorde
                        $newOrder = $existingCount + $uploadedCount + 1;

                        // Eerste foto ooit = hoofdfoto
                        $isPrimary = ($existingCount === 0 && $uploadedCount === 0) ? 1 : 0;

                        // Opslaan in database
                        $stmt = $mysqli->prepare("INSERT INTO product_images (product_id, image_name, image_order) VALUES (?, ?, ?)");
                        $stmt->bind_param("isi", $articleId, $newFileName, $newOrder);

                        if ($stmt->execute()) {
                            $uploadedCount++;
                        } else {
                            unlink($targetPath);
                            $errors[] = "Database fout bij $originalName";
                        }
                    } else {
                        $errors[] = "Upload fout bij $originalName";
                    }
                }
            }
        }

        // Redirect
        $articleId = intval($_POST['article_id']);
        header("Location: /admin/detail?id=" . $articleId . "&zoek=" . urlencode($_POST['zoek'] ?? $_GET['zoek'] ?? '') . "&cat=" . urlencode($_POST['cat'] ?? $_GET['cat'] ?? '') . "#anchor-foto");
        exit;
    }

    // Hoofdfoto instellen
    if ($_POST['action'] === 'set_primary') {
        $imageId = intval($_POST['image_id']);
        $articleId = intval($_POST['article_id']);

        // Reset alle foto's van dit artikel
        $mysqli->query("UPDATE product_images SET is_primary = 0 WHERE product_id = $articleId");

        // Stel nieuwe hoofdfoto in
        $mysqli->query("UPDATE product_images SET is_primary = 1 WHERE id = $imageId");

        header("Location: /admin/detail?id=" . $articleId . "&zoek=" . urlencode($_POST['zoek'] ?? $_GET['zoek'] ?? '') . "&cat=" . urlencode($_POST['cat'] ?? $_GET['cat'] ?? '') . "#anchor-foto");
        exit;
    }

    // Foto verwijderen
    if ($_POST['action'] === 'delete_foto') {
        $imageId = intval($_POST['image_id']);

        // Haal bestandsnaam op
        $result = $mysqli->query("SELECT image_name FROM product_images WHERE id = $imageId");
        $image = $result->fetch_assoc();

        if ($image) {
            // Verwijder bestand
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . $image['image_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Verwijder uit database
            $mysqli->query("DELETE FROM product_images WHERE id = $imageId");

            // Herorder remaining photos to close gaps
            $mysqli->query("SET @row_number = 0");
            $mysqli->query("UPDATE product_images SET image_order = (@row_number:=@row_number+1) WHERE product_id = {$image['product_id']} ORDER BY image_order");
        }

        header("Location: /admin/detail?id=" . $articleId . "&zoek=" . urlencode($_POST['zoek'] ?? $_GET['zoek'] ?? '') . "&cat=" . urlencode($_POST['cat'] ?? $_GET['cat'] ?? '') . "#anchor-foto");
        exit;
    }

    // Update foto volgorde
    if ($_POST['action'] === 'update_photo_order') {
        $articleId = intval($_POST['article_id']);
        $imageIds = $_POST['image_ids'] ?? [];

        foreach ($imageIds as $order => $imageId) {
            $newOrder = $order + 1; // Start bij 1

            $stmt = $mysqli->prepare("UPDATE product_images SET image_order = ? WHERE id = ? AND product_id = ?");
            $stmt->bind_param("iii", $newOrder, $imageId, $articleId);
            $stmt->execute();
        }

        header("Location: /admin/detail?id=" . $articleId . "&zoek=" . urlencode($_POST['zoek'] ?? $_GET['zoek'] ?? '') . "&cat=" . urlencode($_POST['cat'] ?? $_GET['cat'] ?? '') . "#anchor-foto");

        exit;
    }
}

// GET actions voor foto beheer
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'delete_foto' && isset($_GET['image_id'])) {
        $imageId = intval($_GET['image_id']);

        // Haal bestandsnaam en check of het hoofdfoto was
        $result = $mysqli->query("SELECT image_name, is_primary, product_id FROM product_images WHERE id = $imageId");
        $image = $result->fetch_assoc();

        if ($image) {
            $wasMainPhoto = $image['is_primary'];
            $productId = $image['product_id'];

            // Verwijder bestand
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . $image['image_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Verwijder uit database
            $mysqli->query("DELETE FROM product_images WHERE id = $imageId");

            // Als hoofdfoto verwijderd, maak eerste overgebleven foto hoofdfoto
            if ($wasMainPhoto) {
                $mysqli->query("UPDATE product_images SET is_primary = 0 WHERE product_id = $productId");
                $mysqli->query("UPDATE product_images SET is_primary = 1 WHERE product_id = $productId ORDER BY image_order LIMIT 1");
            }
        }


        header("Location: /admin/detail?id=" . $productId . "&zoek=" . urlencode($_GET['zoek'] ?? '') . "&cat=" . urlencode($_GET['cat'] ?? '') . "#anchor-foto");
        exit;
    }
}
