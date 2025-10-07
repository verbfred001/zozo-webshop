<!-- filepath: zozo-admin/templates/categorie_tree.php -->
<ul class="cat-list" id="cat-list">
    <?php
    function printCatTree($cats, $level = 0)
    {
        foreach ($cats as $cat) {
            echo '<li class="cat-block ' . ($level == 0 ? 'cat-block--main' : 'cat-block--sub') . '" data-id="' . $cat['cat_id'] . '">';
            echo '<div class="cat-block__header">';
            echo '<div class="cat-block__info">';
            echo '<span class="cat-block__drag">⋮⋮</span>';
            if ($level > 0) {
                $indent = str_repeat('└─ ', $level - 1);
                echo '<span class="cat-block__indent">' . $indent . '</span>';
                echo '<span class="cat-block__subtitle">' . htmlspecialchars($cat['cat_naam']) . '</span>';
            } else {
                echo '<span class="cat-block__title">' . htmlspecialchars($cat['cat_naam']) . '</span>';
            }
            if ($cat['verborgen'] === 'ja') {
                echo ' <span class="cat-block__hidden">verborgen</span>';
            }
            echo '</div>';
            echo '<div class="cat-block__actions">';
            $subBtnClass = 'btn--sub';
            if ($level == 1) $subBtnClass .= ' btn--sub-80';
            if ($level == 2) $subBtnClass .= ' btn--sub-60';
            if ($level >= 3) $subBtnClass .= ' btn--sub-40';

            echo '<button onclick="showAddForm(' . $cat['cat_id'] . ')" class="btn ' . $subBtnClass . '">+ Sub</button>';
            echo '<button onclick="showEditForm(' . $cat['cat_id'] . ')" class="btn btn--edit" title="Bewerk">';
            echo '<svg class="icon icon--edit" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-2.828 0L9 13zm0 0V17h4"></path>';
            echo '</svg>';
            echo '</button>';
            echo '<button onclick="deleteCategory(' . $cat['cat_id'] . ')" class="btn btn--delete" title="Verwijderen">';
            echo '<svg class="icon icon--delete" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>';
            echo '</svg>';
            echo '</button>';
            echo '</div>';
            echo '</div>';

            // Subcategorieën als <ul> met <li>
            if (!empty($cat['subs'])) {
                echo '<ul class="subcat-list">';
                printCatTree($cat['subs'], $level + 1);
                echo '</ul>';
            }

            echo '</li>';
        }
    }
    printCatTree($categories, 0);
    ?>
</ul>