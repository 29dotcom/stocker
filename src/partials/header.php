<?php
// Componente header riutilizzabile, menu adattivo al ruolo.
declare(strict_types=1);

if (!function_exists('currentUser')) {
    require_once __DIR__ . '/../../config/auth.php';
}

$activePage = $activePage ?? '';
$pageTitle  = $pageTitle  ?? '&Stocker';
$pageCss    = $pageCss    ?? [];
$user       = currentUser();
$cartCount  = array_sum(array_map('intval', $_SESSION['cart'] ?? []));
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Oswald:wght@200;300;500;700&family=Barlow:wght@300;400;500&display=swap">
<link rel="stylesheet" href="../src/styles/base.css">
<?php foreach ($pageCss as $css): ?>
    <link rel="stylesheet" href="../src/styles/<?= e($css) ?>">
<?php endforeach; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">

<header class="navbar">
    <a href="index.php" class="logo"><span>&amp;</span>Stocker</a>
    <nav>
        <ul>
            <li><a href="index.php" class="<?= $activePage === 'home' ? 'active' : '' ?>">Home</a></li>
            <li><a href="catalogo.php" class="<?= $activePage === 'catalog' ? 'active' : '' ?>">Catalogo</a></li>
            <?php if ($user && $user['ruolo'] === 'admin'): ?>
                <li><a href="admin.php" class="<?= $activePage === 'admin' ? 'active' : '' ?>">Pannello Admin</a></li>
            <?php elseif ($user): ?>
                <li><a href="account.php" class="<?= $activePage === 'account' ? 'active' : '' ?>">Account</a></li>
            <?php else: ?>
                <li><a href="login.php" class="<?= $activePage === 'login' ? 'active' : '' ?>">Login</a></li>
                <li><a href="register.php" class="<?= $activePage === 'register' ? 'active' : '' ?>">Registrati</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <div class="nav-right">
        <?php if ($user): ?>
            <span class="nav-user"><?= e($user['email']) ?></span>
        <?php endif; ?>
        <a class="nav-cart" href="carrello.php">Carrello (<?= (int)$cartCount ?>)</a>
        <?php if ($user): ?>
            <a class="nav-logout" href="logout.php">Logout</a>
        <?php endif; ?>
    </div>
</header>

<?php $flashItems = flashPull(); ?>
<?php if ($flashItems): ?>
    <div class="flash-stack">
        <?php foreach ($flashItems as $msg): ?>
            <div class="flash flash--<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
