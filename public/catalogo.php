<?php
require_once __DIR__ . '/../config/auth.php';

$pageTitle  = '&Stocker - Catalogo';
$activePage = 'catalog';
$pageCss    = ['catalog.css'];

$search      = trim((string)($_GET['q'] ?? ''));
$macroFilter = (string)($_GET['macro'] ?? '');
$availability = (string)($_GET['stock'] ?? 'all');
$sort        = (string)($_GET['sort'] ?? 'featured');

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(p.nome_prodotto LIKE :q OR p.marca_prodotto LIKE :q OR p.descrizione LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}
if ($macroFilter !== '' && $macroFilter !== 'Tutte') {
    $where[] = 'c.nome_macro_categoria = :macro';
    $params[':macro'] = $macroFilter;
}
if ($availability === 'available') {
    $where[] = 'p.qta_magazzino > p.qta_minima_alert';
} elseif ($availability === 'low') {
    $where[] = 'p.qta_magazzino > 0 AND p.qta_magazzino <= p.qta_minima_alert';
}

$orderBy = 'p.id_prodotto ASC';
if ($sort === 'priceAsc')  $orderBy = 'p.prezzo_vendita ASC';
if ($sort === 'priceDesc') $orderBy = 'p.prezzo_vendita DESC';
if ($sort === 'stockAsc')  $orderBy = 'p.qta_magazzino ASC';

$sql = 'SELECT p.*, c.nome_macro_categoria, c.nome_micro_categoria, c.nome_nano_categoria
        FROM prodotti p
        JOIN categorie c ON c.id_categoria = p.id_categoria
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $orderBy;

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$macros = db()->query('SELECT DISTINCT nome_macro_categoria FROM categorie ORDER BY nome_macro_categoria')
              ->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/../src/partials/header.php';
?>

<main class="page-content">

    <div class="page-title-block">
        <p class="eyebrow">Vetrina e-commerce</p>
        <h1>Catalogo prodotti</h1>
        <div class="divider"></div>
        <p>Catalogo dinamico filtrato per categorie gerarchiche. Gli utenti registrati possono acquistare; al checkout il trigger MySQL aggiorna lo stock e i prezzi vengono storicizzati.</p>
    </div>

    <form method="get" class="catalog-filters">
        <div class="field">
            <label>Cerca</label>
            <input class="input" type="text" name="q" placeholder="Nome, marca, descrizione" value="<?= e($search) ?>">
        </div>
        <div class="field">
            <label>Macro categoria</label>
            <select class="input" name="macro">
                <option value="Tutte">Tutte</option>
                <?php foreach ($macros as $m): ?>
                    <option value="<?= e($m) ?>" <?= $macroFilter === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Disponibilita'</label>
            <select class="input" name="stock">
                <option value="all"       <?= $availability === 'all' ? 'selected' : '' ?>>Tutti</option>
                <option value="available" <?= $availability === 'available' ? 'selected' : '' ?>>Disponibili</option>
                <option value="low"       <?= $availability === 'low' ? 'selected' : '' ?>>Sottoscorta</option>
            </select>
        </div>
        <div class="field">
            <label>Ordina</label>
            <select class="input" name="sort">
                <option value="featured"  <?= $sort === 'featured' ? 'selected' : '' ?>>In evidenza</option>
                <option value="priceAsc"  <?= $sort === 'priceAsc' ? 'selected' : '' ?>>Prezzo crescente</option>
                <option value="priceDesc" <?= $sort === 'priceDesc' ? 'selected' : '' ?>>Prezzo decrescente</option>
                <option value="stockAsc"  <?= $sort === 'stockAsc' ? 'selected' : '' ?>>Stock crescente</option>
            </select>
        </div>
        <div class="field" style="grid-column: 1 / -1; display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">Applica filtri</button>
            <a class="btn" href="catalogo.php">Reset</a>
        </div>
    </form>

    <?php if ($products): ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <?php include __DIR__ . '/../src/partials/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">Nessun prodotto trovato con questi filtri.</div>
    <?php endif; ?>

</main>

<?php require __DIR__ . '/../src/partials/footer.php'; ?>
