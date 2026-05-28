<?php
// ============================================================
// Sessione, sicurezza CSRF, helper ruoli e utilita comuni.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/seed_passwords.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

stocker_seed_passwords();

// ------------------------------------------------------------
// Output escape
// ------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// ------------------------------------------------------------
// CSRF
// ------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        die('Token CSRF non valido. Aggiorna la pagina e riprova.');
    }
}

// ------------------------------------------------------------
// Sessione utente
// ------------------------------------------------------------
function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cache = null;
    if ($cache && (int)$cache['id_utente'] === (int)$_SESSION['user_id']) {
        return $cache;
    }
    $stmt = db()->prepare('SELECT * FROM utenti WHERE id_utente = :id');
    $stmt->execute([':id' => (int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $cache = $user ?: null;
    if (!$user) {
        unset($_SESSION['user_id']);
    }
    return $cache;
}

function currentCustomer(): ?array
{
    $user = currentUser();
    if (!$user || $user['ruolo'] !== 'user') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM clienti WHERE id_utente = :id LIMIT 1');
    $stmt->execute([':id' => (int)$user['id_utente']]);
    return $stmt->fetch() ?: null;
}

function isAdmin(): bool
{
    $u = currentUser();
    return $u && $u['ruolo'] === 'admin';
}

function isUser(): bool
{
    $u = currentUser();
    return $u && $u['ruolo'] === 'user';
}

function requireLogin(): void
{
    if (!currentUser()) {
        flash('Devi accedere per continuare.', 'warning');
        header('Location: login.php');
        exit;
    }
}

function requireUser(): void
{
    requireLogin();
    if (!isUser()) {
        flash('Sezione riservata ai clienti.', 'warning');
        header('Location: index.php');
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        flash('Sezione riservata agli amministratori.', 'warning');
        header('Location: index.php');
        exit;
    }
}

// ------------------------------------------------------------
// Flash messages
// ------------------------------------------------------------
function flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function flashPull(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

// ------------------------------------------------------------
// Helpers formattazione
// ------------------------------------------------------------
function eur(float $value): string
{
    return number_format($value, 2, ',', '.') . ' &euro;';
}

function fmtDate(string $datetime): string
{
    return date('d/m/Y H:i', strtotime($datetime));
}

function statoLabel(string $status): string
{
    return [
        'in_attesa'  => 'In attesa',
        'confermato' => 'Confermato',
        'spedito'    => 'Spedito',
        'consegnato' => 'Consegnato',
        'annullato'  => 'Annullato',
    ][$status] ?? $status;
}

function pagamentoLabel(string $method): string
{
    return [
        'carta'        => 'Carta',
        'bonifico'     => 'Bonifico',
        'contrassegno' => 'Contrassegno',
    ][$method] ?? $method;
}

function categoryLabel(array $row): string
{
    return $row['nome_macro_categoria'] . ' / ' . $row['nome_micro_categoria'] . ' / ' . $row['nome_nano_categoria'];
}

function url(string $page = 'index.php', array $params = []): string
{
    $query = $params ? '?' . http_build_query($params) : '';
    return $page . $query;
}
