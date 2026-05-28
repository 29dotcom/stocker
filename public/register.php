<?php
require_once __DIR__ . '/../config/auth.php';

if (currentUser()) {
    header('Location: account.php');
    exit;
}

$form = [
    'username' => '', 'email' => '',
    'firstName' => '', 'lastName' => '',
    'address' => '', 'city' => '', 'phone' => '',
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (array_keys($form) as $field) {
        $form[$field] = trim((string)($_POST[$field] ?? ''));
    }
    $password = (string)($_POST['password'] ?? '');

    if (strlen($password) < 6) {
        $error = 'La password deve avere almeno 6 caratteri.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email non valida.';
    } elseif ($form['username'] === '' || $form['firstName'] === '' || $form['lastName'] === '' || $form['address'] === '' || $form['city'] === '') {
        $error = 'Compila tutti i campi obbligatori.';
    } else {
        $check = db()->prepare('SELECT COUNT(*) FROM utenti WHERE email = :email OR username = :username');
        $check->execute([':email' => $form['email'], ':username' => $form['username']]);
        if ((int)$check->fetchColumn() > 0) {
            $error = 'Username o email gia\' registrati.';
        } else {
            try {
                $pdo = db();
                $pdo->beginTransaction();

                $insertUser = $pdo->prepare(
                    'INSERT INTO utenti (username, email, password_hash, ruolo)
                     VALUES (:username, :email, :hash, "user")'
                );
                $insertUser->execute([
                    ':username' => $form['username'],
                    ':email'    => $form['email'],
                    ':hash'     => password_hash($password, PASSWORD_BCRYPT),
                ]);
                $userId = (int)$pdo->lastInsertId();

                $insertCustomer = $pdo->prepare(
                    'INSERT INTO clienti (id_utente, nome, cognome, indirizzo_spedizione, citta, telefono)
                     VALUES (:id, :nome, :cognome, :indirizzo, :citta, :telefono)'
                );
                $insertCustomer->execute([
                    ':id'        => $userId,
                    ':nome'      => $form['firstName'],
                    ':cognome'   => $form['lastName'],
                    ':indirizzo' => $form['address'],
                    ':citta'     => $form['city'],
                    ':telefono'  => $form['phone'] !== '' ? $form['phone'] : null,
                ]);

                $pdo->commit();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                flash('Registrazione completata e accesso effettuato.', 'success');
                header('Location: account.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Errore durante la registrazione: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle  = '&Stocker - Registrazione';
$activePage = 'register';
$pageCss    = ['auth.css'];
$bodyClass  = 'page-register';

require __DIR__ . '/../src/partials/header.php';
?>

<div class="auth-wrapper">
    <section class="auth-card">
        <p class="auth-eyebrow">Nuovo account</p>
        <h1>Registrazione</h1>
        <div class="divider"></div>

        <?php if ($error): ?>
            <div class="flash flash--error" style="position:static; margin-bottom:20px;"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <div class="field-row">
                <div class="field"><label>Nome</label><input class="input" type="text" name="firstName" required value="<?= e($form['firstName']) ?>"></div>
                <div class="field"><label>Cognome</label><input class="input" type="text" name="lastName" required value="<?= e($form['lastName']) ?>"></div>
            </div>
            <div class="field"><label>Username</label><input class="input" type="text" name="username" required value="<?= e($form['username']) ?>"></div>
            <div class="field"><label>Email</label><input class="input" type="email" name="email" required value="<?= e($form['email']) ?>"></div>
            <div class="field"><label>Password</label><input class="input" type="password" name="password" required minlength="6"></div>
            <div class="field-row">
                <div class="field"><label>Indirizzo</label><input class="input" type="text" name="address" required value="<?= e($form['address']) ?>"></div>
                <div class="field"><label>Citta'</label><input class="input" type="text" name="city" required value="<?= e($form['city']) ?>"></div>
            </div>
            <div class="field"><label>Telefono (opzionale)</label><input class="input" type="text" name="phone" value="<?= e($form['phone']) ?>"></div>

            <button type="submit" class="btn btn-primary submit">Crea account</button>
        </form>

        <p class="switch-mode">Hai gia' un account? <a href="login.php">Accedi</a></p>
    </section>
</div>

<?php require __DIR__ . '/../src/partials/footer.php'; ?>
