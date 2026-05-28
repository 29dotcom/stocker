<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user     = currentUser();
$customer = currentCustomer();

$pageTitle  = '&Stocker - Account';
$activePage = 'account';
$pageCss    = ['account.css'];

if ($user['ruolo'] === 'admin') {
    require __DIR__ . '/../src/partials/header.php';
    ?>
    <main class="page-content">
        <div class="page-title-block">
            <p class="eyebrow">Profilo amministratore</p>
            <h1>Accesso admin attivo</h1>
            <div class="divider"></div>
            <p>Stai usando un account amministratore. Apri il pannello dedicato per gestire prodotti, ordini, report e dati di base.</p>
            <p style="margin-top:24px;"><a class="btn btn-primary" href="admin.php">Apri pannello admin</a></p>
        </div>
    </main>
    <?php
    require __DIR__ . '/../src/partials/footer.php';
    exit;
}

if (!$customer) {
    require __DIR__ . '/../src/partials/header.php';
    echo '<main class="page-content"><h1>Profilo cliente non trovato.</h1></main>';
    require __DIR__ . '/../src/partials/footer.php';
    exit;
}

$message = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nome      = trim((string)($_POST['firstName'] ?? ''));
    $cognome   = trim((string)($_POST['lastName']  ?? ''));
    $indirizzo = trim((string)($_POST['address']   ?? ''));
    $citta     = trim((string)($_POST['city']      ?? ''));
    $telefono  = trim((string)($_POST['phone']     ?? ''));

    if ($nome === '' || $cognome === '' || $indirizzo === '' || $citta === '') {
        $error = 'Nome, cognome, indirizzo e citta\' sono obbligatori.';
    } else {
        $stmt = db()->prepare(
            'UPDATE clienti SET nome = :nome, cognome = :cognome,
                    indirizzo_spedizione = :indirizzo, citta = :citta, telefono = :telefono
             WHERE id_cliente = :id'
        );
        $stmt->execute([
            ':nome' => $nome, ':cognome' => $cognome,
            ':indirizzo' => $indirizzo, ':citta' => $citta,
            ':telefono' => $telefono !== '' ? $telefono : null,
            ':id' => (int)$customer['id_cliente'],
        ]);
        $customer = currentCustomer();
        $message  = 'Profilo aggiornato.';
        flash($message, 'success');
    }
}

// Storico ordini
$ordersStmt = db()->prepare(
    'SELECT * FROM ordini WHERE id_cliente = :id ORDER BY data_ordine DESC'
);
$ordersStmt->execute([':id' => (int)$customer['id_cliente']]);
$orders = $ordersStmt->fetchAll();

$detailsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id_ordine');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $detailStmt = db()->prepare(
        "SELECT d.*, p.nome_prodotto, p.link_immagine
         FROM dettaglio_ordini d
         JOIN prodotti p ON p.id_prodotto = d.id_prodotto
         WHERE d.id_ordine IN ($placeholders)"
    );
    $detailStmt->execute($ids);
    foreach ($detailStmt->fetchAll() as $row) {
        $detailsByOrder[(int)$row['id_ordine']][] = $row;
    }
}

require __DIR__ . '/../src/partials/header.php';
?>

<main class="page-content">
    <div class="page-title-block">
        <p class="eyebrow">Area personale</p>
        <h1>Il tuo account</h1>
        <div class="divider"></div>
        <p>Modifica i tuoi dati di spedizione e consulta lo storico degli ordini. Ogni dettaglio mantiene il prezzo applicato al momento del checkout.</p>
    </div>

    <div class="account-layout">

        <!-- Profilo -->
        <section class="card">
            <h2>Dati profilo</h2>
            <p style="color:var(--gray-light); margin-bottom:18px;">Login: <?= e($user['email']) ?> (<?= e($user['username']) ?>)</p>

            <?php if ($error): ?><div class="flash flash--error" style="position:static; margin-bottom:18px;"><?= e($error) ?></div><?php endif; ?>
            <?php if ($message): ?><div class="flash flash--success" style="position:static; margin-bottom:18px;"><?= e($message) ?></div><?php endif; ?>

            <form method="post">
                <?= csrf_field() ?>
                <div class="field-row">
                    <div class="field"><label>Nome</label><input class="input" type="text" name="firstName" required value="<?= e($customer['nome']) ?>"></div>
                    <div class="field"><label>Cognome</label><input class="input" type="text" name="lastName" required value="<?= e($customer['cognome']) ?>"></div>
                </div>
                <div class="field"><label>Indirizzo spedizione</label><input class="input" type="text" name="address" required value="<?= e($customer['indirizzo_spedizione']) ?>"></div>
                <div class="field-row">
                    <div class="field"><label>Citta'</label><input class="input" type="text" name="city" required value="<?= e($customer['citta']) ?>"></div>
                    <div class="field"><label>Telefono</label><input class="input" type="text" name="phone" value="<?= e((string)$customer['telefono']) ?>"></div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Salva modifiche</button>
            </form>
        </section>

        <!-- Ordini -->
        <section class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:14px; flex-wrap:wrap;">
                <h2 style="margin:0;">Storico ordini</h2>
                <a class="btn" href="catalogo.php">Nuovo ordine</a>
            </div>

            <?php if (!$orders): ?>
                <div class="empty-state">Non hai ancora effettuato ordini.</div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <details class="order-item" open>
                        <summary>
                            <div>
                                <span class="order-id">Ordine #<?= (int)$order['id_ordine'] ?></span>
                                <div class="order-date"><?= e(fmtDate($order['data_ordine'])) ?> / <?= e(pagamentoLabel($order['metodo_pagamento'])) ?></div>
                            </div>
                            <span class="order-state state-<?= e($order['stato_ordine']) ?>"><?= e(statoLabel($order['stato_ordine'])) ?></span>
                            <span class="order-total"><?= eur((float)$order['totale_ordine']) ?></span>
                        </summary>
                        <div class="order-details">
                            <?php foreach (($detailsByOrder[(int)$order['id_ordine']] ?? []) as $detail): ?>
                                <div class="row">
                                    <div>
                                        <strong><?= e($detail['nome_prodotto']) ?></strong>
                                        <div style="color:var(--gray-light); font-size:12px;">
                                            <?= (int)$detail['quantita_ordinata'] ?> &times; <?= eur((float)$detail['prezzo_unitario_applicato']) ?> / <?= e((string)$detail['formato_acquisto']) ?>
                                        </div>
                                    </div>
                                    <span style="font-family:'Oswald',sans-serif; color:var(--accent);">
                                        <?= eur((float)$detail['prezzo_unitario_applicato'] * (int)$detail['quantita_ordinata']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php require __DIR__ . '/../src/partials/footer.php'; ?>
