<?php
require_once __DIR__ . '/../config/auth.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ---------------------------------------------------------------
// Azioni POST: add / update / remove / checkout
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $user   = currentUser();

    if (in_array($action, ['add', 'update', 'remove'], true)) {
        $id  = (int)($_POST['id_prodotto'] ?? 0);
        $qty = max(1, (int)($_POST['quantita'] ?? 1));

        if ($action === 'remove') {
            unset($_SESSION['cart'][$id]);
            flash('Prodotto rimosso dal carrello.', 'success');
        } else {
            $stmt = db()->prepare('SELECT qta_magazzino, nome_prodotto FROM prodotti WHERE id_prodotto = :id');
            $stmt->execute([':id' => $id]);
            $product = $stmt->fetch();

            if (!$product) {
                flash('Prodotto non trovato.', 'error');
            } elseif (!$user) {
                flash('Accedi per acquistare.', 'warning');
                header('Location: login.php');
                exit;
            } elseif ($user['ruolo'] !== 'user') {
                flash('Gli amministratori gestiscono lo stock dal pannello admin.', 'warning');
            } elseif ((int)$product['qta_magazzino'] <= 0) {
                flash('Prodotto esaurito.', 'error');
            } else {
                $current = (int)($_SESSION['cart'][$id] ?? 0);
                if ($action === 'add')    $target = $current + $qty;
                elseif ($action === 'update') $target = $qty;
                else $target = $current;
                $target = min($target, (int)$product['qta_magazzino']);
                if ($target <= 0) {
                    unset($_SESSION['cart'][$id]);
                } else {
                    $_SESSION['cart'][$id] = $target;
                }
                flash('Carrello aggiornato.', 'success');
            }
        }

        header('Location: carrello.php');
        exit;
    }

    // -----------------------------------------------------------
    // CHECKOUT: crea ordine + dettagli. Il trigger MySQL aggiorna
    // automaticamente qta_magazzino e blocca lo stock insufficiente.
    // -----------------------------------------------------------
    if ($action === 'checkout') {
        requireUser();
        $customer = currentCustomer();
        if (!$customer || !$_SESSION['cart']) {
            flash('Carrello vuoto o profilo cliente non trovato.', 'warning');
            header('Location: carrello.php');
            exit;
        }

        $metodo = (string)($_POST['metodo_pagamento'] ?? 'carta');
        if (!in_array($metodo, ['carta', 'bonifico', 'contrassegno'], true)) {
            $metodo = 'carta';
        }

        $ids = array_keys($_SESSION['cart']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $prodStmt = db()->prepare(
            "SELECT id_prodotto, nome_prodotto, prezzo_vendita, qta_magazzino, formato_acquisto
             FROM prodotti WHERE id_prodotto IN ($placeholders)"
        );
        $prodStmt->execute($ids);
        $rows = $prodStmt->fetchAll();
        $byId = [];
        foreach ($rows as $r) $byId[(int)$r['id_prodotto']] = $r;

        // Verifica disponibilita'
        foreach ($_SESSION['cart'] as $pid => $qty) {
            $p = $byId[(int)$pid] ?? null;
            if (!$p) { flash('Un prodotto non e\' piu\' disponibile.', 'error'); header('Location: carrello.php'); exit; }
            if ((int)$qty > (int)$p['qta_magazzino']) {
                flash('Stock insufficiente per ' . $p['nome_prodotto'] . '.', 'error');
                header('Location: carrello.php');
                exit;
            }
        }

        $total = 0.0;
        foreach ($_SESSION['cart'] as $pid => $qty) {
            $total += (int)$qty * (float)$byId[(int)$pid]['prezzo_vendita'];
        }

        $pdo = db();
        try {
            $pdo->beginTransaction();

            $insOrder = $pdo->prepare(
                "INSERT INTO ordini (id_cliente, metodo_pagamento, stato_ordine, totale_ordine)
                 VALUES (:cliente, :metodo, 'confermato', :totale)"
            );
            $insOrder->execute([
                ':cliente' => (int)$customer['id_cliente'],
                ':metodo'  => $metodo,
                ':totale'  => round($total, 2),
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $insDetail = $pdo->prepare(
                'INSERT INTO dettaglio_ordini (id_ordine, id_prodotto, quantita_ordinata, prezzo_unitario_applicato, formato_acquisto)
                 VALUES (:o, :p, :q, :prezzo, :fmt)'
            );

            foreach ($_SESSION['cart'] as $pid => $qty) {
                $p = $byId[(int)$pid];
                $insDetail->execute([
                    ':o'      => $orderId,
                    ':p'      => (int)$pid,
                    ':q'      => (int)$qty,
                    ':prezzo' => (float)$p['prezzo_vendita'],
                    ':fmt'    => $p['formato_acquisto'],
                ]);
                // Il trigger AFTER INSERT decrementa automaticamente qta_magazzino.
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            flash('Ordine #' . $orderId . ' confermato. Stock aggiornato dal trigger MySQL.', 'success');
            header('Location: account.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Errore checkout: ' . $e->getMessage(), 'error');
            header('Location: carrello.php');
            exit;
        }
    }
}

// ---------------------------------------------------------------
// Rendering carrello
// ---------------------------------------------------------------
$pageTitle  = '&Stocker - Carrello';
$activePage = 'cart';
$pageCss    = ['catalog.css'];

$cart  = $_SESSION['cart'];
$lines = [];
$total = 0.0;

if ($cart) {
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT * FROM prodotti WHERE id_prodotto IN ($placeholders)"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $p) {
        $qty = (int)$cart[(int)$p['id_prodotto']];
        $line = ['product' => $p, 'qty' => $qty, 'subtotal' => $qty * (float)$p['prezzo_vendita']];
        $lines[] = $line;
        $total  += $line['subtotal'];
    }
}

$user     = currentUser();
$customer = currentCustomer();
$canCheckout = $user && $user['ruolo'] === 'user' && $customer && $lines;

require __DIR__ . '/../src/partials/header.php';
?>

<main class="page-content">
    <div class="page-title-block">
        <p class="eyebrow">Checkout</p>
        <h1>Carrello e ordine</h1>
        <div class="divider"></div>
        <p>Riepilogo articoli e finalizzazione ordine. Il decremento dello stock e' affidato al trigger MySQL <code>tr_aggiorna_magazzino_checkout</code>.</p>
    </div>

    <div class="cart-layout">
        <section>
            <?php if (!$lines): ?>
                <div class="empty-state">Il carrello e' vuoto. <a class="btn" href="catalogo.php" style="margin-left:14px;">Apri catalogo</a></div>
            <?php else: ?>
                <?php foreach ($lines as $line): $p = $line['product']; ?>
                    <div class="cart-line">
                        <img src="<?= e($p['link_immagine']) ?>" alt="<?= e($p['nome_prodotto']) ?>">
                        <div>
                            <p class="name"><?= e($p['nome_prodotto']) ?></p>
                            <p class="brand"><?= e($p['marca_prodotto']) ?></p>
                            <p class="brand" style="margin-top:8px;">Disponibili: <?= (int)$p['qta_magazzino'] ?></p>
                        </div>
                        <div class="actions">
                            <p class="price"><?= eur($line['subtotal']) ?></p>
                            <form method="post" class="inline-form" style="gap:6px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id_prodotto" value="<?= (int)$p['id_prodotto'] ?>">
                                <input class="input qty" type="number" name="quantita" min="1" max="<?= (int)$p['qta_magazzino'] ?>" value="<?= (int)$line['qty'] ?>">
                                <button class="btn" type="submit">OK</button>
                            </form>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="id_prodotto" value="<?= (int)$p['id_prodotto'] ?>">
                                <button class="btn btn-danger" type="submit">Rimuovi</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <aside class="cart-summary">
            <h2 style="font-family:'Oswald',sans-serif; font-size:22px; letter-spacing:3px; margin:0 0 14px; text-transform:uppercase;">Riepilogo</h2>
            <div class="row"><span>Righe</span><span><?= count($lines) ?></span></div>
            <div class="row"><span>Totale articoli</span><span><?= array_sum(array_column($lines, 'qty')) ?></span></div>
            <div class="row total"><span>Totale</span><span><?= eur($total) ?></span></div>

            <?php if ($canCheckout): ?>
                <form method="post" style="margin-top:18px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="checkout">
                    <div class="field">
                        <label>Metodo pagamento</label>
                        <select class="input" name="metodo_pagamento">
                            <option value="carta">Carta</option>
                            <option value="bonifico">Bonifico</option>
                            <option value="contrassegno">Contrassegno</option>
                        </select>
                    </div>
                    <p style="font-size:12px; color:var(--gray-light); margin-bottom:14px;">
                        Spedizione a <?= e($customer['nome'] . ' ' . $customer['cognome']) ?>,
                        <?= e($customer['indirizzo_spedizione']) ?>, <?= e($customer['citta']) ?>.
                    </p>
                    <button class="btn btn-primary" type="submit">Conferma ordine</button>
                </form>
            <?php elseif (!$user): ?>
                <a class="btn btn-primary" href="login.php" style="margin-top:18px;">Accedi per ordinare</a>
            <?php elseif ($user['ruolo'] === 'admin'): ?>
                <p style="color:var(--gray-light); font-size:12px; margin-top:18px;">Sei autenticato come admin: usa il pannello per gestire ordini.</p>
            <?php endif; ?>
        </aside>
    </div>
</main>

<?php require __DIR__ . '/../src/partials/footer.php'; ?>
