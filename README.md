# &Stocker — Versione PHP / XAMPP

Implementazione PHP 8 + MySQL 8 + HTML5 + CSS3 del progetto &Stocker, fedele alla struttura del README originale (LAVORO 3). Tutta la logica utilizza **PDO Prepared Statements**, sessioni PHP, **password_hash()** BCrypt e un **trigger MySQL** `AFTER INSERT` su `dettaglio_ordini` per il decremento automatico dello stock.

## 1. Installazione su XAMPP

1. Scarica e installa XAMPP ≥ 8.0 con Apache e MySQL attivi.
2. Copia l'intera cartella `php-stocker/` in `htdocs`, ad esempio:
   - Windows: `C:/xampp/htdocs/stocker/`
   - Linux:   `/opt/lampp/htdocs/stocker/`
   - macOS:   `/Applications/XAMPP/htdocs/stocker/`
3. Avvia Apache e MySQL dal pannello XAMPP.
4. Apri `http://localhost/phpmyadmin`, crea il database `stocker_db` (collation `utf8mb4_unicode_ci`) e importa il file `db/stocker.sql`.
5. Apri `config/db.php` e verifica utente/password (default XAMPP: `root` / `""`).
6. Visita `http://localhost/stocker/public/index.php`.

## 2. Credenziali demo

| Ruolo | Email                  | Password   |
|-------|------------------------|------------|
| Admin | admin@stocker.local    | admin123   |
| User  | user@stocker.local     | user123    |

Le password vengono ri-hashate alla prima esecuzione (vedi `config/seed_passwords.php`).

## 3. Struttura cartelle

```
php-stocker/
├── db/
│   └── stocker.sql              # Schema 3FN + trigger + viste + dati demo
├── config/
│   ├── db.php                   # Connessione PDO centralizzata
│   ├── auth.php                 # Sessione + helper ruoli
│   └── seed_passwords.php       # Re-hash password demo al primo accesso
├── public/
│   ├── index.php                # Home page (vetrina + sezioni README)
│   ├── catalogo.php             # Catalogo dinamico con filtri/ricerca
│   ├── prodotto.php             # Scheda prodotto + add to cart
│   ├── login.php                # Login (form + POST handler)
│   ├── register.php             # Registrazione utente + cliente
│   ├── logout.php               # Distrugge la sessione
│   ├── account.php              # Area cliente: profilo + storico ordini
│   ├── carrello.php             # Carrello, checkout, conferma ordine
│   └── admin.php                # Pannello admin con sezioni (report, prodotti, ordini, dati)
├── src/
│   ├── partials/
│   │   ├── header.php           # Barra navigazione adattiva al ruolo
│   │   ├── footer.php
│   │   └── product-card.php     # Rendering scheda prodotto riutilizzabile
│   └── styles/
│       ├── base.css             # Reset + variabili + tipografia
│       ├── homepage.css
│       ├── auth.css
│       ├── catalog.css
│       ├── account.css
│       └── admin.css
└── README.md
```

## 4. Sicurezza implementata

- **SQL Injection** prevenuta con PDO Prepared Statements ovunque.
- **Password** salvate via `password_hash($pw, PASSWORD_BCRYPT)` e verificate con `password_verify()`.
- **CSRF token** generato a sessione per i form di scrittura.
- **Controllo ruoli** (`requireUser`, `requireAdmin`) all'inizio delle pagine protette.
- **Escape output** via `htmlspecialchars()` (helper `e()`).

## 5. Funzionalità

- Vetrina e-commerce filtrata per categorie (Macro → Micro → Nano).
- Carrello in sessione, checkout transazionale, decremento stock via trigger.
- Area cliente: modifica profilo + storico ordini con dettaglio righe.
- Pannello admin: report (fatturato mensile per categoria, top prodotti, sottoscorta), CRUD prodotti, gestione ordini, gestione categorie/fornitori.
- Viste SQL: `v_prodotti_sottoscorta`, `v_fatturato_mensile_categoria`, `v_top_prodotti`.
