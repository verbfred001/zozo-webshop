<?php
session_start();
header("Content-Type: application/json");

try {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data || !isset($data["sequence"]) || !is_array($data["sequence"])) {
        echo json_encode(["success" => false, "error" => "Invalid data"]);
        exit;
    }

    $sequence = $data["sequence"];
    foreach ($sequence as $orderId) {
        if (!is_numeric($orderId)) {
            echo json_encode(["success" => false, "error" => "Invalid order ID: " . $orderId]);
            exit;
        }
    }

    $_SESSION["order_sequence"] = $sequence;

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
