<?php
require_once __DIR__ . '/../config/auth.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT p.*, c.nome_macro_categoria, c.nome_micro_categoria, c.nome_nano_categoria,
                              f.ragione_sociale AS fornitore
                       FROM prodotti p
                       JOIN categorie c  ON c.id_categoria = p.id_categoria
                       JOIN fornitori f  ON f.id_fornitore = p.id_fornitore
                       WHERE p.id_prodotto = :id');
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    flash('Prodotto non trovato.', 'warning');
    header('Location: catalogo.php');
    exit;
}

$pageTitle  = '&Stocker - ' . $product['nome_prodotto'];
$activePage = 'catalog';
$pageCss    = ['catalog.css'];

$user = currentUser();
$soldOut = (int)$product['qta_magazzino'] <= 0;

require __DIR__ . '/../src/partials/header.php';
?>

<main class="page-content">
    <div class="page-title-block">
        <p class="eyebrow"><?= e(categoryLabel($product)) ?></p>
    </div>

    <div class="product-detail">
        <div class="gallery">
            <img src="<?= e($product['link_immagine']) ?>" alt="<?= e($product['nome_prodotto']) ?>">
        </div>
        <div class="info">
            <p class="eyebrow" style="font-family:'Oswald',sans-serif; font-size:11px; letter-spacing:5px; color:var(--accent); text-transform:uppercase;">Prodotto #<?= (int)$product['id_prodotto'] ?></p>
            <h1><?= e($product['nome_prodotto']) ?></h1>
            <p class="meta"><?= e($product['marca_prodotto']) ?> / Fornitore: <?= e($product['fornitore']) ?></p>
            <p class="desc"><?= nl2br(e((string)$product['descrizione'])) ?></p>

            <dl class="specs">
                <div><dt>Prezzo</dt><dd><?= eur((float)$product['prezzo_vendita']) ?></dd></div>
                <div><dt>Stock</dt><dd><?= (int)$product['qta_magazzino'] ?> pezzi</dd></div>
                <div><dt>Soglia alert</dt><dd><?= (int)$product['qta_minima_alert'] ?> pezzi</dd></div>
                <div><dt>Condizione</dt><dd><?= e($product['condizione_prodotto']) ?></dd></div>
                <div><dt>Formato</dt><dd><?= e($product['formato_acquisto']) ?></dd></div>
                <div><dt>Paese</dt><dd><?= e((string)$product['paese_fabbricazione']) ?></dd></div>
            </dl>

            <?php if ($soldOut): ?>
                <div class="add-row"><button class="btn" disabled>Esaurito</button></div>
            <?php elseif (!$user): ?>
                <div class="add-row"><a href="login.php" class="btn btn-primary">Accedi per ordinare</a></div>
            <?php elseif ($user['ruolo'] === 'admin'): ?>
                <div class="add-row"><a href="admin.php?section=products" class="btn btn-primary">Apri gestione</a></div>
            <?php else: ?>
                <form class="add-row" method="post" action="carrello.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id_prodotto" value="<?= (int)$product['id_prodotto'] ?>">
                    <div class="field qty">
                        <label>Quantita'</label>
                        <input class="input" type="number" name="quantita" min="1" max="<?= (int)$product['qta_magazzino'] ?>" value="1">
                    </div>
                    <button type="submit" class="btn btn-primary">Aggiungi al carrello</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../src/partials/footer.php'; ?>
