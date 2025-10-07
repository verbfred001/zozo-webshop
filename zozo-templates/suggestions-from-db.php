<div class="suggestions">
    <a href="/suggesties" class="suggesties-link" style="text-decoration:none; color:inherit;">
        <h2>Suggesties</h2>
    </a>
    <?php
    // zozo-templates/suggestions-from-db.php

    include_once $_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/DB_connectie.php";
    global $mysqli;

    $sql = "
    SELECT p.art_naam, MIN(i.image_name) AS image_name
    FROM products p
    LEFT JOIN images i ON i.product_id = p.art_id
    WHERE p.art_catID = 272 AND (p.art_weergeven = 1 OR p.art_weergeven = '1' OR LOWER(p.art_weergeven) = 'ja')
    GROUP BY p.art_id
    ORDER BY p.art_unix DESC
    LIMIT 5
    ";
    $result = $mysqli->query($sql);

    if ($result && $result->num_rows > 0) {
        echo '<div class="suggestion-list">';
        while ($row = $result->fetch_assoc()) {
            echo '<a href="/nl/cat/suggesties" class="suggestion" style="display:flex;align-items:center;gap:0.75rem;text-decoration:none;color:inherit;">';
            if (!empty($row['image_name'])) {
                echo '<img src="/upload/' . htmlspecialchars($row['image_name']) . '" alt="' . htmlspecialchars($row['art_naam']) . '" class="suggestion-thumb">';
            }
            echo '<span class="suggestion-title">' . htmlspecialchars($row['art_naam']) . '</span>';
            echo '</a>';
        }
        echo '</div>';
    } else {
        echo '<p>Geen suggesties gevonden.</p>';
    }
    ?>
</div>