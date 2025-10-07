<?php
function tel_voorraad_varianten($mysqli, $art_id)
{
    $aantal = 0; // Initialiseren voor de zekerheid
    $totaal = 0; // Initialiseren voor de zekerheid
    // Tel het aantal varianten voor dit artikel
    $stmt = $mysqli->prepare("SELECT COUNT(*) as aantal FROM voorraad WHERE art_id = ?");
    $stmt->bind_param("i", $art_id);
    $stmt->execute();
    $stmt->bind_result($aantal);
    $stmt->fetch();
    $stmt->close();

    if ($aantal > 0) {
        // Sommeer de voorraad van alle varianten
        $stmt = $mysqli->prepare("SELECT SUM(stock) as totaal FROM voorraad WHERE art_id = ?");
        $stmt->bind_param("i", $art_id);
        $stmt->execute();
        $stmt->bind_result($totaal);
        $stmt->fetch();
        $stmt->close();
        return (int)$totaal;
    } else {
        // Geen varianten: neem voorraad uit products
        $stmt = $mysqli->prepare("SELECT art_aantal FROM products WHERE art_id = ?");
        $stmt->bind_param("i", $art_id);
        $stmt->execute();
        $stmt->bind_result($aantal);
        $stmt->fetch();
        $stmt->close();
        return (int)$aantal;
    }
}
