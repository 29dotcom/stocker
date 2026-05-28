<?php
require_once __DIR__ . '/../config/auth.php';

if (currentUser()) {
    header('Location: ' . (isAdmin() ? 'admin.php' : 'account.php'));
    exit;
}

$identifier = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $password   = (string)($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $error = 'Inserisci email/username e password.';
    } else {
        $stmt = db()->prepare('SELECT * FROM utenti WHERE email = :email OR username = :username LIMIT 1');
        $stmt->execute([
            ':email'    => $identifier,
            ':username' => $identifier
        ]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id_utente'];
            flash('Accesso effettuato come ' . $user['ruolo'] . '.', 'success');
            header('Location: ' . ($user['ruolo'] === 'admin' ? 'admin.php' : 'account.php'));
            exit;
        }
        $error = 'Credenziali non valide.';
    }
}

$pageTitle  = '&Stocker - Login';
$activePage = 'login';
$pageCss    = ['auth.css'];
$bodyClass  = 'page-login';

require __DIR__ . '/../src/partials/header.php';
?>

<div class="auth-wrapper">
    <section class="auth-card">
        <p class="auth-eyebrow">Area riservata</p>
        <h1>Login</h1>
        <div class="divider"></div>

        <?php if ($error): ?>
            <div class="flash flash--error" style="position:static; margin-bottom:20px;"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <div class="field">
                <label>Email o username</label>
                <input class="input" type="text" name="identifier" required value="<?= e($identifier) ?>" autocomplete="username">
            </div>
            <div class="field">
                <label>Password</label>
                <input class="input" type="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary submit">Entra</button>

            <div class="demo-row">
                <button class="btn" type="button" onclick="document.querySelector('[name=identifier]').value='admin@stocker.local';document.querySelector('[name=password]').value='admin123';">Demo admin</button>
                <button class="btn" type="button" onclick="document.querySelector('[name=identifier]').value='user@stocker.local';document.querySelector('[name=password]').value='user123';">Demo cliente</button>
            </div>
        </form>

        <p class="switch-mode">Non hai un account? <a href="register.php">Registrati</a></p>
    </section>
</div>

<?php require __DIR__ . '/../src/partials/footer.php'; ?>