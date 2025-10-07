<?php
// filepath: /zozo-pages/product-card.php
// Verwacht: $product = ['naam' => ..., 'prijs' => ..., 'image' => ...];

if (!isset($product)) return;

$image = !empty($product['image']) ? htmlspecialchars($product['image']) : '/zozo-assets/img/no-image.webp';
$url = '/prod/' . rawurlencode($product['afkorting']);
?>
<div class="product-card">
  <a href="<?= $url ?>">
    <img src="<?= $image ?>" alt="<?= htmlspecialchars($product['naam']) ?>" class="product-image">
  </a>
  <div class="product-info">
    <a href="<?= htmlspecialchars($product['url']) ?>" class="product-title"><?= htmlspecialchars($product['naam']) ?></a>
    <div class="product-bottom">
      <?php if (floatval(str_replace(',', '.', $product['prijs'])) == 0): ?>
        <span class="product-price" style="white-space: pre-line;">Bestel<br>op maat</span>
      <?php else: ?>
        <span class="product-price">&euro; <?= htmlspecialchars($product['prijs']) ?></span>
      <?php endif; ?>
      <button class="add-to-cart-btn" title="Voeg toe aan winkelwagen"><i class="fas fa-shopping-cart"></i></button>
    </div>
  </div>
</div>