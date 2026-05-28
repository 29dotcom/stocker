<div align="center">

# &Stocker
### Sistema Full-Stack per la Gestione Logistica, di Magazzino ed E-commerce

**Esame di Stato del Secondo Ciclo di Istruzione**
*Indirizzo: Informatica e Telecomunicazioni — Anno Scolastico 2025/2026*
*Classe V AIF — LAVORO 3*

---

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat-square&logo=css3&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-FB7A24?style=flat-square&logo=xampp&logoColor=white)

</div>

---

## Abstract

Il progetto **&Stocker** è un sistema full-stack per la gestione della logistica di magazzino integrata con un portale e-commerce, sviluppato nell'ambito del LAVORO 3 . Seguendo la traccia  «Gestore Magazzino e Logistica per E-commerce», l'applicazione implementa un backend completo per il controllo di scorte, fornitori e ordini, esponendo le funzionalità attraverso un'interfaccia web dinamica.

Il cuore del sistema è un database relazionale normalizzato in **Terza Forma Normale (3FN)** con tabelle per Prodotti, Categorie, Fornitori, Ordini e Dettaglio_Ordini. L'automazione delle scorte di magazzino è delegata a un **Trigger MySQL** che aggiorna automaticamente le quantità disponibili all'inserimento di ogni nuovo ordine. La reportistica avanzata sfrutta query aggregate con `GROUP BY` e `JOIN` complesse per generare statistiche di vendita mensili. Sul lato PHP, il sistema gestisce sessioni utente con ruoli differenziati (Admin vs User), implementa tutte le operazioni CRUD tramite PDO Prepared Statements e offre una navigazione fluida tra catalogo, area personale e pannello amministrativo.

---


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
