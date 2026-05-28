<?php
// ============================================================
// Converte i placeholder `SEED:password` salvati nel dump SQL
// in veri hash BCrypt al primo caricamento dell'app.
// ============================================================

declare(strict_types=1);

function stocker_seed_passwords(): void
{
    try {
        $pdo  = db();
        $stmt = $pdo->query("SELECT id_utente, password_hash FROM utenti WHERE password_hash LIKE 'SEED:%'");
    } catch (Throwable $e) {
        // Il database potrebbe non essere ancora importato: ignoriamo silenziosamente.
        return;
    }

    $rows = $stmt->fetchAll();
    if (!$rows) {
        return;
    }

    $update = $pdo->prepare('UPDATE utenti SET password_hash = :hash WHERE id_utente = :id');

    foreach ($rows as $row) {
        $plain = substr((string)$row['password_hash'], 5); // rimuove "SEED:"
        $hash  = password_hash($plain, PASSWORD_BCRYPT);
        $update->execute([
            ':hash' => $hash,
            ':id'   => (int)$row['id_utente'],
        ]);
    }
}
