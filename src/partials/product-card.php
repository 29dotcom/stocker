<?php
// Componente riutilizzabile per la scheda prodotto nel catalogo.
declare(strict_types=1);

/** @var array $product */
$user = currentUser();
$lowStock = (int)$product['qta_magazzino'] <= (int)$product['qta_minima_alert'];
$soldOut  = (int)$product['qta_magazzino'] <= 0;
$badgeClass = $soldOut ? 'is-out' : ($lowStock ? 'is-low' : 'is-ok');
$badgeLabel = $soldOut ? 'Esaurito' : ($lowStock ? 'Sottoscorta' : 'Disponibile');
?>
<article class="product-card">
    <a class="product-media" href="prodotto.php?id=<?= (int)$product['id_prodotto'] ?>">
        <img src="<?= e($product['link_immagine']) ?>" alt="<?= e($product['nome_prodotto']) ?>" loading="lazy">
        <span class="product-format"><?= e($product['formato_acquisto']) ?></span>
    </a>
    <div class="product-body">
        <p class="product-category">
            <?= e($product['nome_macro_categoria']) ?> / <?= e($product['nome_micro_categoria']) ?> / <?= e($product['nome_nano_categoria']) ?>
        </p>
        <h2 class="product-name">
            <a href="prodotto.php?id=<?= (int)$product['id_prodotto'] ?>"><?= e($product['nome_prodotto']) ?></a>
        </h2>
        <p class="product-brand"><?= e($product['marca_prodotto']) ?></p>
        <p class="product-desc"><?= e(mb_strimwidth((string)$product['descrizione'], 0, 160, '...')) ?></p>

        <div class="product-footer">
            <div>
                <p class="product-price"><?= eur((float)$product['prezzo_vendita']) ?></p>
                <p class="product-stock <?= e($badgeClass) ?>">
                    <?= e($badgeLabel) ?> &middot; <?= (int)$product['qta_magazzino'] ?> pz
                </p>
            </div>
            <?php if ($soldOut): ?>
                <button class="btn btn-ghost" disabled>Esaurito</button>
            <?php elseif (!$user): ?>
                <a class="btn btn-ghost" href="login.php">Accedi</a>
            <?php elseif ($user['ruolo'] === 'admin'): ?>
                <a class="btn btn-ghost" href="admin.php?section=products">Gestisci</a>
            <?php else: ?>
                <form method="post" action="carrello.php" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id_prodotto" value="<?= (int)$product['id_prodotto'] ?>">
                    <input type="hidden" name="quantita" value="1">
                    <button class="btn btn-ghost" type="submit">Aggiungi</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>
