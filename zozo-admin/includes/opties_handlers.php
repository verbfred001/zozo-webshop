<?php
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new option group - GEFIXED kolomnamen
    if (isset($_POST['add_group'])) {
        $group_name_nl = trim($_POST['group_name_nl'] ?? '');
        $group_name_fr = trim($_POST['group_name_fr'] ?? '');
        $group_name_en = trim($_POST['group_name_en'] ?? '');
        $info = trim($_POST['info'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $affects_stock = isset($_POST['affects_stock']) ? 1 : 0;

        $sql = "INSERT INTO option_groups (group_name, group_name_fr, group_name_en, type, affects_stock, info, sort_order) VALUES (?, ?, ?, ?, ?, ?, 999)";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            // types: group_name(s), group_name_fr(s), group_name_en(s), type(s), affects_stock(i), info(s)
            $stmt->bind_param("ssssis", $group_name_nl, $group_name_fr, $group_name_en, $type, $affects_stock, $info);
            $stmt->execute();
            $group_id = $mysqli->insert_id;
        } else {
            error_log('opties_handlers.php prepare failed: ' . $mysqli->error);
            // fallback redirect or show error as needed
            header('Location: /admin/opties?error=prepare_failed');
            exit;
        }

        header('Location: /admin/opties#groep-' . $group_id);
        exit;
    }

    // Update option group - TYPE WORDT NIET GEUPDATE
    if (isset($_POST['update_group'])) {
        $group_id = $_POST['group_id'];
        $group_name_nl = $_POST['group_name_nl'];
        $group_name_fr = $_POST['group_name_fr'] ?? null;
        $group_name_en = $_POST['group_name_en'] ?? null;
        // TYPE WEGGELATEN - mag niet gewijzigd worden
        $affects_stock = isset($_POST['affects_stock']) ? 1 : 0;
        $info = $_POST['info'] ?? null;

        // SQL zonder type field, maar met info
        $sql = "UPDATE option_groups SET group_name = ?, group_name_fr = ?, group_name_en = ?, affects_stock = ?, info = ? WHERE group_id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sssisi", $group_name_nl, $group_name_fr, $group_name_en, $affects_stock, $info, $group_id);
        $stmt->execute();

        header('Location: /admin/opties#groep-' . $group_id);
        exit;
    }

    // Add new option - GEFIXED kolomnamen
    if (isset($_POST['add_option'])) {
        $group_id = $_POST['group_id'];
        $option_name_nl = $_POST['option_name_nl'];
        $option_name_fr = $_POST['option_name_fr'] ?? null;
        $option_name_en = $_POST['option_name_en'] ?? null;
        $price_delta = $_POST['price_delta'] ?? 0;

        $sql = "INSERT INTO options (group_id, option_name, option_name_fr, option_name_en, price_delta, sort_order) VALUES (?, ?, ?, ?, ?, 999)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("isssd", $group_id, $option_name_nl, $option_name_fr, $option_name_en, $price_delta);
        $stmt->execute();
        $option_id = $mysqli->insert_id;

        header('Location: /admin/opties#optie-' . $option_id);
        exit;
    }

    // Update option
    if (isset($_POST['update_option'])) {
        $option_id = $_POST['option_id'];
        $option_name_nl = $_POST['option_name_nl'];
        $option_name_fr = $_POST['option_name_fr'] ?? null;
        $option_name_en = $_POST['option_name_en'] ?? null;
        $price_delta = $_POST['price_delta'] ?? 0;

        $sql = "UPDATE options SET option_name = ?, option_name_fr = ?, option_name_en = ?, price_delta = ? WHERE option_id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sssdi", $option_name_nl, $option_name_fr, $option_name_en, $price_delta, $option_id);
        $stmt->execute();
        $option_id = $_POST['option_id']; // <-- ID van de bewerkte optie

        header('Location: /admin/opties#optie-' . $option_id);
        exit;
    }

    // Delete option group
    if (isset($_POST['delete_group'])) {
        $group_id = $_POST['group_id'];

        // First delete all options in this group
        $sql = "DELETE FROM options WHERE group_id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $group_id);
        $stmt->execute();

        // Then delete the group
        $sql = "DELETE FROM option_groups WHERE group_id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $group_id);
        $stmt->execute();

        header('Location: /admin/opties#groep-' . $group_id);
        exit;
    }

    // Delete option
    if (isset($_POST['delete_option'])) {
        $option_id = $_POST['option_id'];

        $sql = "DELETE FROM options WHERE option_id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $option_id);
        $stmt->execute();

        header('Location: opties');
        exit;
    }
}
