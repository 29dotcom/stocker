<?php
require_once __DIR__ . '/../config/auth.php';

$pageTitle  = '&Stocker - Home';
$activePage = 'home';
$pageCss    = ['homepage.css'];

require __DIR__ . '/../src/partials/header.php';
?>

<main class="container">

    <!-- ═══ SLIDE 1: HERO ═══ -->
    <section id="hero" class="slide">
        <div class="hero-content">
            <p class="hero-eyebrow">LAVORO 3 - CONSIGLIO FOLLI GARAVINO</p>
            <h1 class="hero-title">
                <span class="brand-mark">&amp;Stocker</span>
                Tutto cio' che<br>
                <em>ami</em><br>
                in un click
            </h1>
            <p class="hero-sub">Sistema per la Gestione Logistica, di Magazzino ed E-commerce.</p>
            <div class="hero-actions">
                <a href="catalogo.php" class="btn btn-primary">Apri catalogo</a>
                <a href="login.php" class="btn">Accedi o registrati</a>
            </div>
        </div>
        <p class="hero-counter">01 / 06</p>
    </section>

    <!-- ═══ SLIDE 2: ABSTRACT ═══ -->
    <section id="abstract">
        <div class="abstract-visual">
            <p class="abstract-tag">Panoramica del progetto</p>
        </div>
        <div class="abstract-content">
            <p class="eyebrow">Abstract</p>
            <h2>Il cuore<br>del sistema</h2>
            <p>
                Il progetto &amp;Stocker e' un sistema full-stack per la gestione della logistica
                di magazzino integrata con un portale e-commerce. Implementa un backend completo
                per il controllo di scorte, fornitori e ordini.
            </p>
            <ul class="abstract-list">
                <li><span>01</span><span>Database 3FN e Trigger MySQL</span></li>
                <li><span>02</span><span>Reportistica (GROUP BY / JOIN)</span></li>
                <li><span>03</span><span>CRUD con PDO Prepared Statements</span></li>
            </ul>
        </div>
    </section>

    <!-- ═══ SLIDE 3: STACK ═══ -->
    <section id="stack" class="slide">
        <div class="section-header">
            <div>
                <p class="hero-eyebrow">Architettura</p>
                <h2>Stack tecnologico</h2>
            </div>
            <a href="#specifiche" class="btn">Vedi specifiche</a>
        </div>
        <div class="stack-grid">
            <div class="stack-card">
                <p class="role">Backend</p>
                <div><p class="value">PHP 8.x</p><p class="note">Logica di business</p></div>
            </div>
            <div class="stack-card">
                <p class="role">Database</p>
                <div><p class="value">MySQL 8.0</p><p class="note">RDBMS relazionale</p></div>
            </div>
            <div class="stack-card">
                <p class="role">Frontend</p>
                <div><p class="value">HTML5 &amp; CSS3</p><p class="note">Interfaccia utente</p></div>
            </div>
            <div class="stack-card">
                <p class="role">Server</p>
                <div><p class="value">XAMPP (Apache)</p><p class="note">Ambiente di hosting</p></div>
            </div>
        </div>
    </section>

    <!-- ═══ SLIDE 4: SPECIFICHE ═══ -->
    <section id="specifiche" class="slide">
        <div>
            <p class="hero-eyebrow">Progettazione dati</p>
            <h2 style="font-family:'Oswald',sans-serif; font-size:clamp(2.2rem,5vw,4.5rem); font-weight:500; letter-spacing:4px; text-transform:uppercase; margin-top:14px;">
                Caratteristiche<br>del sistema
            </h2>
            <div class="features-grid">
                <div class="feature-card">
                    <p class="feature-code">3FN</p>
                    <h3>Modello relazionale</h3>
                    <p>Database normalizzato in Terza Forma Normale. Tabelle per Utenti, Categorie, Prodotti, Fornitori e Ordini relazionate con PK/FK e integrita' referenziale.</p>
                </div>
                <div class="feature-card">
                    <p class="feature-code">TRIG</p>
                    <h3>Automazione MySQL</h3>
                    <p>Trigger AFTER INSERT configurato su dettaglio_ordini per l'aggiornamento automatico e istantaneo delle quantita' a magazzino.</p>
                </div>
                <div class="feature-card">
                    <p class="feature-code">PDO</p>
                    <h3>Sicurezza &amp; crittografia</h3>
                    <p>Query protette tramite Prepared Statements contro la SQL Injection e password utenti sottoposte ad hashing avanzato con l'algoritmo BCrypt.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ SLIDE 5: RUOLI ═══ -->
    <section id="ruoli">
        <div>
            <p class="hero-eyebrow">Modello dei privilegi</p>
            <h2 style="font-family:'Oswald',sans-serif; font-size:clamp(2.2rem,5vw,4.5rem); font-weight:500; letter-spacing:4px; text-transform:uppercase; margin-top:14px;">
                Gestione<br>accessi
            </h2>
            <div class="roles-list">
                <div class="item"><span class="symbol">&#x2B22;</span><span class="name">Amministratore (Admin)</span><span class="desc">Gestione totale CRUD e Report</span></div>
                <div class="item"><span class="symbol">&#x2B22;</span><span class="name">Cliente (User)</span><span class="desc">Area riservata e storico ordini</span></div>
                <div class="item"><span class="symbol">&#x2B22;</span><span class="name">Utente Ospite</span><span class="desc">Esplorazione catalogo categorie</span></div>
                <div class="item"><span class="symbol">&#x2B22;</span><span class="name">Gestione Fornitori</span><span class="desc">Solo per ruolo Admin</span></div>
                <div class="item"><span class="symbol">&#x2B22;</span><span class="name">Automazione Scorte</span><span class="desc">Gestita dal Trigger</span></div>
            </div>
        </div>
        <div class="roles-visual">
            <p>Controllo<br>completo<br>di ogni<br>processo.</p>
        </div>
    </section>

    <!-- ═══ SLIDE 6: CTA ═══ -->
    <section id="cta" class="slide">
        <h2>Scarica qua<br>il codice latex<br>della documentazione<br>tecnica</h2>
        <p>Documentazione tecnica del sistema integrato &amp;Stocker.<br>Consiglio, Folli, Garavino.</p>
        <div class="actions">
            <a href="catalogo.php" class="btn btn-primary">Prova ordini dinamici</a>
            <a href="admin.php" class="btn">Pannello admin</a>
        </div>
    </section>

</main>

<?php require __DIR__ . '/../src/partials/footer.php'; ?>
