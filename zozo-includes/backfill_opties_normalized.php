<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php");
set_time_limit(0);

$res = $mysqli->query("SELECT id, opties FROM voorraad");
$stmt = $mysqli->prepare("UPDATE voorraad SET opties_normalized = ? WHERE id = ?");

while ($row = $res->fetch_assoc()) {
    $parts = array_filter(array_map('trim', explode('|', $row['opties'])));
    sort($parts, SORT_STRING);
    $norm = implode('|', $parts);
    $stmt->bind_param('si', $norm, $row['id']);
    $stmt->execute();
}
echo "backfill done\n";
