<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");

// nieuw: respecteer instelling 'voorraadbeheer'
$voorraadbeheer = 1;
if ($mysqli) {
    $r = $mysqli->query("SELECT voorraadbeheer FROM instellingen LIMIT 1");
    if ($r) {
        $row = $r->fetch_assoc();
        if (isset($row['voorraadbeheer'])) $voorraadbeheer = intval($row['voorraadbeheer']);
    }
}

if ($voorraadbeheer === 0) {
    // Optie A: geef globale productvoorraad terug (als aanwezig)
    $product_id = intval($_GET['product_id'] ?? 0);
    $globalStock = 0;
    if ($product_id > 0) {
        $s = $mysqli->prepare("SELECT art_aantal FROM products WHERE art_id = ? LIMIT 1");
        if ($s) {
            $s->bind_param('i', $product_id);
            $s->execute();
            $res = $s->get_result();
            if ($rrow = $res->fetch_assoc()) $globalStock = intval($rrow['art_aantal']);
            $s->close();
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['voorraad' => $globalStock, 'verkrijgbaar' => 1]);
    exit;
}

$product_id = intval($_GET['product_id'] ?? 0);
$opties_raw = $_GET['opties'] ?? '';

error_log("check_stock called: product_id=" . ($_GET['product_id'] ?? 'NULL') . " opties=" . ($_GET['opties'] ?? ''));

$voorraad = 0;
$verkrijgbaar = 1;

if ($product_id > 0) {
    // Normaliseer inkomende optiestring defensief:
    $parts = array_filter(array_map('trim', explode('|', urldecode($opties_raw))));
    sort($parts, SORT_STRING);
    $opties_normalized = implode('|', $parts);

    if ($opties_normalized !== '') {
        // Exact lookup op kolom 'opties'
        $stmt = $mysqli->prepare("SELECT stock, verkrijgbaar FROM voorraad WHERE art_id = ? AND opties = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $product_id, $opties_normalized);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc() ?? null;

            $voorraad = $row ? intval($row['stock']) : 0;
            // prefer 'verkrijgbaar' if present, default 1
            $verkrijgbaar = $row ? (isset($row['verkrijgbaar']) ? intval($row['verkrijgbaar']) : 1) : 1;

            $stmt->close();

            echo json_encode(['voorraad' => $voorraad, 'verkrijgbaar' => $verkrijgbaar]);
            exit;
        } else {
            error_log('check_stock prepare failed: ' . $mysqli->error);
        }
    } else {
        // Geen opties opgegeven -> fallback naar globale voorraad in products
        $stmt = $mysqli->prepare("SELECT art_aantal FROM products WHERE art_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $voorraad = intval($row['art_aantal']);
            }
            $stmt->close();
        }
    }
}

// Default response (covers fallbacks)
echo json_encode(['voorraad' => $voorraad, 'verkrijgbaar' => $verkrijgbaar]);
