<?php
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$section = (string)($_GET['section'] ?? 'report');
$validSections = ['report', 'products', 'orders', 'data'];
if (!in_array($section, $validSections, true)) $section = 'report';

// ---------------------------------------------------------------
// AZIONI POST
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $pdo = db();

    try {
        switch ($action) {

            // ─── PRODOTTI ──────────────────────────────────────────
            case 'product_create':
            case 'product_update':
                $id        = (int)($_POST['id_prodotto'] ?? 0);
                $payload = [
                    ':id_categoria' => (int)($_POST['id_categoria'] ?? 0),
                    ':id_fornitore' => (int)($_POST['id_fornitore'] ?? 0),
                    ':nome'         => trim((string)($_POST['nome_prodotto'] ?? '')),
                    ':marca'        => trim((string)($_POST['marca_prodotto'] ?? '')),
                    ':pa'           => (float)($_POST['prezzo_acquisto'] ?? 0),
                    ':pv'           => (float)($_POST['prezzo_vendita']  ?? 0),
                    ':qta'          => max(0, (int)($_POST['qta_magazzino'] ?? 0)),
                    ':qmin'         => max(0, (int)($_POST['qta_minima_alert'] ?? 0)),
                    ':cond'         => (string)($_POST['condizione_prodotto'] ?? 'nuovo'),
                    ':fmt'          => (string)($_POST['formato_acquisto'] ?? 'Compra Subito'),
                    ':paese'        => trim((string)($_POST['paese_fabbricazione'] ?? '')),
                    ':rec'          => (int)($_POST['recensioni'] ?? 0),
                    ':descr'        => trim((string)($_POST['descrizione'] ?? '')),
                    ':link'         => trim((string)($_POST['link_immagine'] ?? '')),
                ];

                if ($payload[':nome'] === '' || $payload[':marca'] === '' || $payload[':link'] === '' || $payload[':pv'] <= 0) {
                    throw new RuntimeException('Nome, marca, immagine e prezzo di vendita sono obbligatori.');
                }

                if ($action === 'product_create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO prodotti
                         (id_categoria, id_fornitore, nome_prodotto, marca_prodotto,
                          prezzo_acquisto, prezzo_vendita, qta_magazzino, qta_minima_alert,
                          condizione_prodotto, formato_acquisto, paese_fabbricazione,
                          recensioni, descrizione, link_immagine)
                         VALUES (:id_categoria, :id_fornitore, :nome, :marca,
                                 :pa, :pv, :qta, :qmin, :cond, :fmt, :paese,
                                 :rec, :descr, :link)'
                    );
                    $stmt->execute($payload);
                    flash('Prodotto creato.', 'success');
                } else {
                    $payload[':id'] = $id;
                    $stmt = $pdo->prepare(
                        'UPDATE prodotti SET
                            id_categoria = :id_categoria,
                            id_fornitore = :id_fornitore,
                            nome_prodotto = :nome,
                            marca_prodotto = :marca,
                            prezzo_acquisto = :pa,
                            prezzo_vendita = :pv,
                            qta_magazzino = :qta,
                            qta_minima_alert = :qmin,
                            condizione_prodotto = :cond,
                            formato_acquisto = :fmt,
                            paese_fabbricazione = :paese,
                            recensioni = :rec,
                            descrizione = :descr,
                            link_immagine = :link
                         WHERE id_prodotto = :id'
                    );
                    $stmt->execute($payload);
                    flash('Prodotto aggiornato.', 'success');
                }
                header('Location: admin.php?section=products');
                exit;

            case 'product_delete':
                $id = (int)($_POST['id_prodotto'] ?? 0);
                $check = $pdo->prepare('SELECT COUNT(*) FROM dettaglio_ordini WHERE id_prodotto = :id');
                $check->execute([':id' => $id]);
                if ((int)$check->fetchColumn() > 0) {
                    throw new RuntimeException('Prodotto presente in ordini storici: eliminazione bloccata.');
                }
                $pdo->prepare('DELETE FROM prodotti WHERE id_prodotto = :id')->execute([':id' => $id]);
                flash('Prodotto eliminato.', 'success');
                header('Location: admin.php?section=products');
                exit;

            // ─── ORDINI ────────────────────────────────────────────
            case 'order_status':
                $id     = (int)($_POST['id_ordine'] ?? 0);
                $status = (string)($_POST['stato'] ?? '');
                $allowed = ['in_attesa','confermato','spedito','consegnato','annullato'];
                if (!in_array($status, $allowed, true)) {
                    throw new RuntimeException('Stato ordine non valido.');
                }
                $pdo->prepare('UPDATE ordini SET stato_ordine = :s WHERE id_ordine = :id')
                    ->execute([':s' => $status, ':id' => $id]);
                flash('Stato ordine #' . $id . ' aggiornato.', 'success');
                header('Location: admin.php?section=orders');
                exit;

            // ─── CATEGORIE ─────────────────────────────────────────
            case 'category_create':
                $macro = trim((string)($_POST['macro'] ?? ''));
                $micro = trim((string)($_POST['micro'] ?? ''));
                $nano  = trim((string)($_POST['nano']  ?? ''));
                if ($macro === '' || $micro === '' || $nano === '') {
                    throw new RuntimeException('Tutti i livelli categoria sono obbligatori.');
                }
                $pdo->prepare(
                    'INSERT INTO categorie (nome_macro_categoria, nome_micro_categoria, nome_nano_categoria)
                     VALUES (:macro, :micro, :nano)'
                )->execute([':macro' => $macro, ':micro' => $micro, ':nano' => $nano]);
                flash('Categoria aggiunta.', 'success');
                header('Location: admin.php?section=data');
                exit;

            // ─── FORNITORI ─────────────────────────────────────────
            case 'supplier_create':
                $ragione = trim((string)($_POST['ragione_sociale'] ?? ''));
                $piva    = trim((string)($_POST['p_iva'] ?? ''));
                $email   = trim((string)($_POST['email_fornitore'] ?? ''));
                $stato   = (string)($_POST['stato_partner'] ?? 'attivo');
                if ($ragione === '' || $piva === '' || $email === '') {
                    throw new RuntimeException('Compila tutti i campi del fornitore.');
                }
                if (!in_array($stato, ['attivo','sospeso','cessato'], true)) $stato = 'attivo';
                $pdo->prepare(
                    'INSERT INTO fornitori (ragione_sociale, p_iva, email_fornitore, stato_partner)
                     VALUES (:r, :p, :e, :s)'
                )->execute([':r' => $ragione, ':p' => $piva, ':e' => $email, ':s' => $stato]);
                flash('Fornitore aggiunto.', 'success');
                header('Location: admin.php?section=data');
                exit;
        }
    } catch (Throwable $e) {
        flash('Errore: ' . $e->getMessage(), 'error');
        header('Location: admin.php?section=' . $section);
        exit;
    }
}

// ---------------------------------------------------------------
// Dati per le sezioni
// ---------------------------------------------------------------
$categories = db()->query('SELECT * FROM categorie ORDER BY nome_macro_categoria, nome_micro_categoria, nome_nano_categoria')->fetchAll();
$suppliers  = db()->query('SELECT * FROM fornitori ORDER BY ragione_sociale')->fetchAll();

$pageTitle  = '&Stocker - Admin';
$activePage = 'admin';
$pageCss    = ['admin.css', 'catalog.css', 'account.css'];

require __DIR__ . '/../src/partials/header.php';
?>

<main class="page-content">
    <div class="page-title-block">
        <p class="eyebrow">Pannello amministrativo</p>
        <h1>Magazzino &amp; ordini</h1>
        <div class="divider"></div>
        <p>Gestione completa di prodotti, ordini, categorie, fornitori e reportistica con query <code>GROUP BY</code> e viste SQL.</p>
    </div>

    <nav class="admin-tabs">
        <a href="?section=report"   class="<?= $section === 'report'   ? 'active' : '' ?>">Report</a>
        <a href="?section=products" class="<?= $section === 'products' ? 'active' : '' ?>">Prodotti (CRUD)</a>
        <a href="?section=orders"   class="<?= $section === 'orders'   ? 'active' : '' ?>">Ordini</a>
        <a href="?section=data"     class="<?= $section === 'data'     ? 'active' : '' ?>">Dati base</a>
    </nav>

<?php if ($section === 'report'): ?>

    <?php
    $kpiOrders   = (int)db()->query("SELECT COUNT(*) FROM ordini WHERE stato_ordine NOT IN ('consegnato','annullato')")->fetchColumn();
    $kpiRevenue  = (float)db()->query("SELECT COALESCE(SUM(totale_ordine),0) FROM ordini WHERE stato_ordine <> 'annullato'")->fetchColumn();
    $kpiProducts = (int)db()->query('SELECT COUNT(*) FROM prodotti')->fetchColumn();

    $lowStock = db()->query('SELECT * FROM v_prodotti_sottoscorta')->fetchAll();

    // GROUP BY: fatturato mensile per categoria
    $monthly = db()->query('SELECT * FROM v_fatturato_mensile_categoria ORDER BY anno DESC, mese DESC')->fetchAll();
    $maxFatt = 1;
    foreach ($monthly as $m) $maxFatt = max($maxFatt, (float)$m['fatturato']);

    $topProducts = db()->query('SELECT * FROM v_top_prodotti LIMIT 10')->fetchAll();
    ?>

    <div class="kpi-grid">
        <div class="kpi-card"><div class="label">Fatturato</div><div class="value"><?= eur($kpiRevenue) ?></div></div>
        <div class="kpi-card"><div class="label">Ordini attivi</div><div class="value"><?= $kpiOrders ?></div></div>
        <div class="kpi-card"><div class="label">Prodotti</div><div class="value"><?= $kpiProducts ?></div></div>
        <div class="kpi-card"><div class="label">Sottoscorta</div><div class="value"><?= count($lowStock) ?></div></div>
    </div>

    <div class="split-2">
        <section class="card">
            <h2>Fatturato mensile per categoria</h2>
            <?php if (!$monthly): ?>
                <p style="color:var(--gray-light);">Nessun dato vendita.</p>
            <?php else: foreach ($monthly as $row): ?>
                <div class="bar-row">
                    <div class="meta">
                        <span><?= e($row['nome_macro_categoria']) ?> &middot; <?= (int)$row['mese'] ?>/<?= (int)$row['anno'] ?></span>
                        <span><?= eur((float)$row['fatturato']) ?></span>
                    </div>
                    <div class="bar"><div style="width: <?= (float)$row['fatturato'] / $maxFatt * 100 ?>%;"></div></div>
                </div>
            <?php endforeach; endif; ?>
        </section>

        <section class="card">
            <h2>Top 10 prodotti</h2>
            <?php if (!$topProducts): ?>
                <p style="color:var(--gray-light);">Nessuna vendita registrata.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Prodotto</th><th>Marca</th><th>Unita'</th><th>Ricavo</th></tr></thead>
                    <tbody>
                    <?php foreach ($topProducts as $row): ?>
                        <tr>
                            <td><?= e($row['nome_prodotto']) ?></td>
                            <td><?= e($row['marca_prodotto']) ?></td>
                            <td><?= (int)$row['unita_vendute'] ?></td>
                            <td><?= eur((float)$row['ricavo_totale']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>

    <section class="card" style="margin-top:20px;">
        <h2>Alert sottoscorta</h2>
        <?php if (!$lowStock): ?>
            <p style="color:var(--gray-light);">Nessun prodotto sotto la soglia minima.</p>
        <?php else: ?>
            <div class="admin-grid-2">
                <?php foreach ($lowStock as $row): ?>
                    <div class="alert-row">
                        <p class="title"><?= e($row['nome_prodotto']) ?></p>
                        <p class="meta">Stock <?= (int)$row['qta_magazzino'] ?> / soglia <?= (int)$row['qta_minima_alert'] ?> &middot; <?= e($row['fornitore']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($section === 'products'): ?>

    <?php
    $products = db()->query(
        'SELECT p.*, c.nome_macro_categoria, c.nome_micro_categoria, c.nome_nano_categoria
         FROM prodotti p JOIN categorie c ON c.id_categoria = p.id_categoria
         ORDER BY p.id_prodotto DESC'
    )->fetchAll();

    $editing = null;
    $editId = (int)($_GET['edit'] ?? 0);
    if ($editId) {
        foreach ($products as $p) {
            if ((int)$p['id_prodotto'] === $editId) { $editing = $p; break; }
        }
    }
    ?>

    <div class="split-2" style="grid-template-columns: 1.4fr 1fr;">
        <section class="card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h2 style="margin:0;">Catalogo (<?= count($products) ?>)</h2>
                <a class="btn" href="?section=products">Nuovo prodotto</a>
            </div>
            <?php foreach ($products as $p): ?>
                <div class="product-row">
                    <img src="<?= e($p['link_immagine']) ?>" alt="">
                    <div>
                        <strong><?= e($p['nome_prodotto']) ?></strong>
                        <div style="font-size:12px; color:var(--gray-light); margin-top:4px;"><?= e(categoryLabel($p)) ?></div>
                        <div style="font-size:12px; margin-top:4px;">
                            <?= eur((float)$p['prezzo_vendita']) ?> &middot;
                            Stock <?= (int)$p['qta_magazzino'] ?> / soglia <?= (int)$p['qta_minima_alert'] ?>
                        </div>
                    </div>
                    <div class="actions">
                        <a class="btn" href="?section=products&edit=<?= (int)$p['id_prodotto'] ?>">Modifica</a>
                        <form method="post" class="inline-form" onsubmit="return confirm('Eliminare questo prodotto?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="product_delete">
                            <input type="hidden" name="id_prodotto" value="<?= (int)$p['id_prodotto'] ?>">
                            <button class="btn btn-danger" type="submit">Elimina</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="card">
            <h2><?= $editing ? 'Modifica prodotto #' . (int)$editing['id_prodotto'] : 'Crea prodotto' ?></h2>
            <form method="post" class="admin-product-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing ? 'product_update' : 'product_create' ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="id_prodotto" value="<?= (int)$editing['id_prodotto'] ?>">
                <?php endif; ?>

                <div class="field"><label>Nome</label><input class="input" name="nome_prodotto" required value="<?= e((string)($editing['nome_prodotto'] ?? '')) ?>"></div>
                <div class="field"><label>Marca</label><input class="input" name="marca_prodotto" required value="<?= e((string)($editing['marca_prodotto'] ?? '')) ?>"></div>

                <div class="field-row">
                    <div class="field">
                        <label>Categoria</label>
                        <select class="input" name="id_categoria">
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id_categoria'] ?>" <?= ($editing && (int)$editing['id_categoria'] === (int)$c['id_categoria']) ? 'selected' : '' ?>>
                                    <?= e(categoryLabel($c)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Fornitore</label>
                        <select class="input" name="id_fornitore">
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int)$s['id_fornitore'] ?>" <?= ($editing && (int)$editing['id_fornitore'] === (int)$s['id_fornitore']) ? 'selected' : '' ?>>
                                    <?= e($s['ragione_sociale']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field"><label>Prezzo acquisto</label><input class="input" name="prezzo_acquisto" type="number" step="0.01" value="<?= e((string)($editing['prezzo_acquisto'] ?? '0')) ?>"></div>
                    <div class="field"><label>Prezzo vendita</label><input class="input" name="prezzo_vendita" type="number" step="0.01" value="<?= e((string)($editing['prezzo_vendita'] ?? '0')) ?>"></div>
                </div>

                <div class="field-row">
                    <div class="field"><label>Stock</label><input class="input" name="qta_magazzino" type="number" min="0" value="<?= e((string)($editing['qta_magazzino'] ?? '0')) ?>"></div>
                    <div class="field"><label>Soglia alert</label><input class="input" name="qta_minima_alert" type="number" min="0" value="<?= e((string)($editing['qta_minima_alert'] ?? '5')) ?>"></div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Condizione</label>
                        <select class="input" name="condizione_prodotto">
                            <?php foreach (['nuovo','ricondizionato','usato'] as $c): ?>
                                <option value="<?= $c ?>" <?= ($editing && $editing['condizione_prodotto'] === $c) ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Formato acquisto</label>
                        <select class="input" name="formato_acquisto">
                            <?php foreach (['Compra Subito','Asta','Proposta'] as $f): ?>
                                <option value="<?= $f ?>" <?= ($editing && $editing['formato_acquisto'] === $f) ? 'selected' : '' ?>><?= $f ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field"><label>Paese</label><input class="input" name="paese_fabbricazione" value="<?= e((string)($editing['paese_fabbricazione'] ?? '')) ?>"></div>
                    <div class="field"><label>Recensioni</label><input class="input" name="recensioni" type="number" min="0" value="<?= e((string)($editing['recensioni'] ?? '0')) ?>"></div>
                </div>

                <div class="field"><label>URL immagine</label><input class="input" name="link_immagine" required value="<?= e((string)($editing['link_immagine'] ?? '')) ?>"></div>
                <div class="field"><label>Descrizione</label><textarea class="input" name="descrizione" rows="4"><?= e((string)($editing['descrizione'] ?? '')) ?></textarea></div>

                <button type="submit" class="btn btn-primary"><?= $editing ? 'Salva modifiche' : 'Crea prodotto' ?></button>
                <?php if ($editing): ?>
                    <a class="btn" href="?section=products">Annulla</a>
                <?php endif; ?>
            </form>
        </section>
    </div>

<?php elseif ($section === 'orders'): ?>

    <?php
    $orders = db()->query(
        'SELECT o.*, c.nome, c.cognome, c.citta, u.email
         FROM ordini o
         JOIN clienti c ON c.id_cliente = o.id_cliente
         JOIN utenti  u ON u.id_utente  = c.id_utente
         ORDER BY o.data_ordine DESC'
    )->fetchAll();
    ?>

    <section class="card">
        <h2>Tutti gli ordini (<?= count($orders) ?>)</h2>
        <?php if (!$orders): ?>
            <p style="color:var(--gray-light);">Nessun ordine registrato.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>ID</th><th>Cliente</th><th>Data</th><th>Pagamento</th><th>Totale</th><th>Stato</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= (int)$o['id_ordine'] ?></td>
                        <td><?= e($o['nome'] . ' ' . $o['cognome']) ?><div style="font-size:11px; color:var(--gray-light);"><?= e($o['email']) ?></div></td>
                        <td><?= e(fmtDate($o['data_ordine'])) ?></td>
                        <td><?= e(pagamentoLabel($o['metodo_pagamento'])) ?></td>
                        <td><?= eur((float)$o['totale_ordine']) ?></td>
                        <td>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="order_status">
                                <input type="hidden" name="id_ordine" value="<?= (int)$o['id_ordine'] ?>">
                                <select class="input" name="stato" style="padding:8px;">
                                    <?php foreach (['in_attesa','confermato','spedito','consegnato','annullato'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $o['stato_ordine'] === $s ? 'selected' : '' ?>><?= statoLabel($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn" type="submit" style="margin-left:6px;">Aggiorna</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

<?php elseif ($section === 'data'): ?>

    <div class="admin-grid-2">
        <section class="card">
            <h2>Categorie (<?= count($categories) ?>)</h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="category_create">
                <div class="field"><label>Macro</label><input class="input" name="macro" required></div>
                <div class="field"><label>Micro</label><input class="input" name="micro" required></div>
                <div class="field"><label>Nano</label><input class="input" name="nano" required></div>
                <button class="btn btn-primary" type="submit" style="width:100%;">Aggiungi categoria</button>
            </form>
            <table class="table" style="margin-top:24px;">
                <thead><tr><th>Macro</th><th>Micro</th><th>Nano</th></tr></thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><?= e($c['nome_macro_categoria']) ?></td>
                        <td><?= e($c['nome_micro_categoria']) ?></td>
                        <td><?= e($c['nome_nano_categoria']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2>Fornitori (<?= count($suppliers) ?>)</h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="supplier_create">
                <div class="field"><label>Ragione sociale</label><input class="input" name="ragione_sociale" required></div>
                <div class="field"><label>Partita IVA</label><input class="input" name="p_iva" required></div>
                <div class="field"><label>Email</label><input class="input" name="email_fornitore" type="email" required></div>
                <div class="field">
                    <label>Stato partner</label>
                    <select class="input" name="stato_partner">
                        <option value="attivo">attivo</option>
                        <option value="sospeso">sospeso</option>
                        <option value="cessato">cessato</option>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit" style="width:100%;">Aggiungi fornitore</button>
            </form>
            <table class="table" style="margin-top:24px;">
                <thead><tr><th>Ragione sociale</th><th>P. IVA</th><th>Stato</th></tr></thead>
                <tbody>
                <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td><?= e($s['ragione_sociale']) ?><div style="font-size:11px; color:var(--gray-light);"><?= e($s['email_fornitore']) ?></div></td>
                        <td><?= e($s['p_iva']) ?></td>
                        <td><?= e($s['stato_partner']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>

<?php endif; ?>

</main>

<?php require __DIR__ . '/../src/partials/footer.php'; ?>
