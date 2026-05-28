-- &Stocker - schema MySQL 8.x normalizzato in 3FN, completo di trigger e viste.
-- Import: phpMyAdmin -> nuovo DB `stocker_db` (utf8mb4_unicode_ci) -> Importa questo file.

CREATE DATABASE IF NOT EXISTS stocker_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE stocker_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS v_top_prodotti;
DROP VIEW IF EXISTS v_fatturato_mensile_categoria;
DROP VIEW IF EXISTS v_prodotti_sottoscorta;
DROP TRIGGER IF EXISTS tr_check_stock_before_detail_insert;
DROP TRIGGER IF EXISTS tr_aggiorna_magazzino_checkout;
DROP TABLE IF EXISTS dettaglio_ordini;
DROP TABLE IF EXISTS ordini;
DROP TABLE IF EXISTS prodotti;
DROP TABLE IF EXISTS fornitori;
DROP TABLE IF EXISTS categorie;
DROP TABLE IF EXISTS clienti;
DROP TABLE IF EXISTS utenti;

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================================================================
-- TABELLE
-- ===========================================================================

CREATE TABLE utenti (
  id_utente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  ruolo ENUM('admin','user') NOT NULL DEFAULT 'user',
  data_creazione DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE clienti (
  id_cliente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_utente INT UNSIGNED NOT NULL UNIQUE,
  nome VARCHAR(80) NOT NULL,
  cognome VARCHAR(80) NOT NULL,
  indirizzo_spedizione VARCHAR(180) NOT NULL,
  citta VARCHAR(100) NOT NULL,
  telefono VARCHAR(30) NULL,
  CONSTRAINT fk_clienti_utenti
    FOREIGN KEY (id_utente) REFERENCES utenti(id_utente)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categorie (
  id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome_macro_categoria VARCHAR(100) NOT NULL,
  nome_micro_categoria VARCHAR(100) NOT NULL,
  nome_nano_categoria  VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_categoria_livelli (nome_macro_categoria, nome_micro_categoria, nome_nano_categoria)
) ENGINE=InnoDB;

CREATE TABLE fornitori (
  id_fornitore INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ragione_sociale VARCHAR(150) NOT NULL,
  p_iva VARCHAR(24) NOT NULL UNIQUE,
  email_fornitore VARCHAR(120) NOT NULL,
  stato_partner ENUM('attivo','sospeso','cessato') NOT NULL DEFAULT 'attivo'
) ENGINE=InnoDB;

CREATE TABLE prodotti (
  id_prodotto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_categoria INT UNSIGNED NOT NULL,
  id_fornitore INT UNSIGNED NOT NULL,
  nome_prodotto VARCHAR(200) NOT NULL,
  marca_prodotto VARCHAR(100) NOT NULL,
  prezzo_acquisto DECIMAL(10,2) NOT NULL,
  prezzo_vendita DECIMAL(10,2) NOT NULL,
  qta_magazzino INT UNSIGNED NOT NULL DEFAULT 0,
  qta_minima_alert INT UNSIGNED NOT NULL DEFAULT 5,
  condizione_prodotto ENUM('nuovo','ricondizionato','usato') NOT NULL DEFAULT 'nuovo',
  formato_acquisto ENUM('Compra Subito','Asta','Proposta') NOT NULL DEFAULT 'Compra Subito',
  paese_fabbricazione VARCHAR(80) NULL,
  recensioni INT UNSIGNED NOT NULL DEFAULT 0,
  descrizione TEXT NULL,
  link_immagine VARCHAR(2048) NOT NULL,
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_prodotti_categorie
    FOREIGN KEY (id_categoria) REFERENCES categorie(id_categoria)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_prodotti_fornitori
    FOREIGN KEY (id_fornitore) REFERENCES fornitori(id_fornitore)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_prezzi_validi CHECK (prezzo_acquisto >= 0 AND prezzo_vendita > 0),
  CONSTRAINT chk_stock_validi CHECK (qta_magazzino >= 0 AND qta_minima_alert >= 0)
) ENGINE=InnoDB;

CREATE TABLE ordini (
  id_ordine INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_cliente INT UNSIGNED NOT NULL,
  data_ordine DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  metodo_pagamento ENUM('carta','bonifico','contrassegno') NOT NULL,
  stato_ordine ENUM('in_attesa','confermato','spedito','consegnato','annullato') NOT NULL DEFAULT 'in_attesa',
  totale_ordine DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_ordini_clienti
    FOREIGN KEY (id_cliente) REFERENCES clienti(id_cliente)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_totale_ordine CHECK (totale_ordine >= 0)
) ENGINE=InnoDB;

CREATE TABLE dettaglio_ordini (
  id_dettaglio INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_ordine INT UNSIGNED NOT NULL,
  id_prodotto INT UNSIGNED NOT NULL,
  quantita_ordinata INT UNSIGNED NOT NULL,
  prezzo_unitario_applicato DECIMAL(10,2) NOT NULL,
  formato_acquisto VARCHAR(50) NULL,
  CONSTRAINT fk_dettaglio_ordini_ordini
    FOREIGN KEY (id_ordine) REFERENCES ordini(id_ordine)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_dettaglio_ordini_prodotti
    FOREIGN KEY (id_prodotto) REFERENCES prodotti(id_prodotto)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_dettaglio_quantita CHECK (quantita_ordinata > 0),
  CONSTRAINT chk_dettaglio_prezzo CHECK (prezzo_unitario_applicato >= 0)
) ENGINE=InnoDB;

-- ===========================================================================
-- TRIGGER
-- ===========================================================================

DELIMITER $$

CREATE TRIGGER tr_check_stock_before_detail_insert
BEFORE INSERT ON dettaglio_ordini
FOR EACH ROW
BEGIN
  DECLARE stock_disponibile INT UNSIGNED;
  SELECT qta_magazzino INTO stock_disponibile
    FROM prodotti WHERE id_prodotto = NEW.id_prodotto FOR UPDATE;
  IF stock_disponibile IS NULL OR stock_disponibile < NEW.quantita_ordinata THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Stock insufficiente per completare ordine';
  END IF;
END$$

CREATE TRIGGER tr_aggiorna_magazzino_checkout
AFTER INSERT ON dettaglio_ordini
FOR EACH ROW
BEGIN
  UPDATE prodotti
     SET qta_magazzino = qta_magazzino - NEW.quantita_ordinata
   WHERE id_prodotto = NEW.id_prodotto;
END$$

DELIMITER ;

-- ===========================================================================
-- VISTE PER REPORT
-- ===========================================================================

CREATE VIEW v_prodotti_sottoscorta AS
SELECT p.id_prodotto, p.nome_prodotto, p.marca_prodotto,
       p.qta_magazzino, p.qta_minima_alert, f.ragione_sociale AS fornitore
  FROM prodotti p JOIN fornitori f ON f.id_fornitore = p.id_fornitore
 WHERE p.qta_magazzino <= p.qta_minima_alert
 ORDER BY p.qta_magazzino ASC;

CREATE VIEW v_fatturato_mensile_categoria AS
SELECT c.nome_macro_categoria, MONTH(o.data_ordine) AS mese,
       YEAR(o.data_ordine) AS anno,
       SUM(d.quantita_ordinata * d.prezzo_unitario_applicato) AS fatturato
  FROM dettaglio_ordini d
  JOIN prodotti  p ON p.id_prodotto = d.id_prodotto
  JOIN categorie c ON c.id_categoria = p.id_categoria
  JOIN ordini    o ON o.id_ordine = d.id_ordine
 WHERE o.stato_ordine <> 'annullato'
 GROUP BY c.nome_macro_categoria, anno, mese;

CREATE VIEW v_top_prodotti AS
SELECT p.id_prodotto, p.nome_prodotto, p.marca_prodotto,
       SUM(d.quantita_ordinata) AS unita_vendute,
       SUM(d.quantita_ordinata * d.prezzo_unitario_applicato) AS ricavo_totale
  FROM dettaglio_ordini d JOIN prodotti p ON p.id_prodotto = d.id_prodotto
 GROUP BY p.id_prodotto, p.nome_prodotto, p.marca_prodotto
 ORDER BY unita_vendute DESC;

-- ===========================================================================
-- DATI DEMO (le password vengono ri-hashate al primo accesso via PHP)
-- ===========================================================================

INSERT INTO utenti (id_utente, username, email, password_hash, ruolo) VALUES
  (1, 'admin',   'admin@stocker.local', 'SEED:admin123', 'admin'),
  (2, 'cliente', 'user@stocker.local',  'SEED:user123',  'user');

INSERT INTO clienti (id_cliente, id_utente, nome, cognome, indirizzo_spedizione, citta, telefono) VALUES
  (1, 2, 'Mario', 'Rossi', 'Via Torino 29', 'Torino', '+39 333 0102030');

INSERT INTO categorie (id_categoria, nome_macro_categoria, nome_micro_categoria, nome_nano_categoria) VALUES
  (1, 'Elettronica', 'Telefonia e Accessori', 'Cellulari'),
  (2, 'Elettronica', 'Telefonia e Accessori', 'Orologi digitali'),
  (3, 'Elettronica', 'Computer e Accessori',  'Portatili'),
  (4, 'Elettronica', 'Computer e Accessori',  'Schermi'),
  (5, 'Abbigliamento','Donna', 'Maglieria'),
  (6, 'Abbigliamento','Donna', 'Jeans'),
  (7, 'Abbigliamento','Uomo',  'Maglieria'),
  (8, 'Abbigliamento','Uomo',  'Jeans');

INSERT INTO fornitori (id_fornitore, ragione_sociale, p_iva, email_fornitore, stato_partner) VALUES
  (1, 'Techno Distribution S.r.l.', 'IT07890120010', 'riordini@techno.example',     'attivo'),
  (2, 'Wearhouse Italia S.p.A.',    'IT01988240991', 'supply@wearhouse.example',    'attivo'),
  (3, 'North Devices GmbH',         'DE293881991',   'orders@northdevices.example', 'sospeso');

INSERT INTO prodotti
 (id_prodotto, id_categoria, id_fornitore, nome_prodotto, marca_prodotto,
  prezzo_acquisto, prezzo_vendita, qta_magazzino, qta_minima_alert,
  condizione_prodotto, formato_acquisto, paese_fabbricazione, recensioni,
  descrizione, link_immagine)
VALUES
 (1,1,1,'iPhone 17','Apple',748.00,979.99,32,8,'nuovo','Compra Subito','Cina',150,
  'Smartphone premium con display ProMotion e connettivita Wi-Fi 7.',
  'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?q=80&w=1200&auto=format&fit=crop'),
 (2,1,1,'Samsung Galaxy S26 Ultra','Samsung',1020.00,1499.99,15,10,'nuovo','Compra Subito','Corea del Sud',45,
  'Top di gamma con fotocamera avanzata e funzioni AI.',
  'https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?q=80&w=1200&auto=format&fit=crop'),
 (3,2,3,'Apple Watch Ultra 3','Apple',640.00,909.99,6,7,'nuovo','Compra Subito','Cina',90,
  'Smartwatch robusto con autonomia estesa e sensori salute.',
  'https://images.unsplash.com/photo-1434493789847-2f02dc6ca35d?q=80&w=1200&auto=format&fit=crop'),
 (4,2,1,'Samsung Galaxy Watch 8 Classic','Samsung',340.00,529.99,26,9,'nuovo','Compra Subito','Corea del Sud',110,
  'Orologio digitale con ghiera fisica e funzioni salute.',
  'https://images.unsplash.com/photo-1523275335684-37898b6baf30?q=80&w=1200&auto=format&fit=crop'),
 (5,3,3,'MacBook Pro M5','Apple',1480.00,1949.99,9,5,'nuovo','Asta','Cina',45,
  'Notebook professionale con chip di nuova generazione.',
  'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?q=80&w=1200&auto=format&fit=crop'),
 (6,3,1,'Asus Vivobook S 14','Asus',590.00,859.99,21,6,'nuovo','Compra Subito','Taiwan',70,
  'Portatile leggero per studio e lavoro.',
  'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?q=80&w=1200&auto=format&fit=crop'),
 (7,5,2,'Maglione Donna Cashmere','North Loom',58.00,129.99,4,6,'nuovo','Compra Subito','Italia',82,
  'Capo morbido in maglieria fine.',
  'https://images.unsplash.com/photo-1525507119028-ed4c629a60a3?q=80&w=1200&auto=format&fit=crop'),
 (8,8,2,'Jeans Uomo Slim','Denim Works',34.00,79.99,38,12,'nuovo','Compra Subito','Portogallo',66,
  'Jeans slim fit con lavaggio scuro.',
  'https://images.unsplash.com/photo-1542272604-787c3835535d?q=80&w=1200&auto=format&fit=crop'),
 (9,4,1,'Monitor Studio 27','Viewline',210.00,349.99,18,6,'ricondizionato','Proposta','Germania',32,
  'Schermo 27 pollici per lavoro creativo.',
  'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?q=80&w=1200&auto=format&fit=crop');

INSERT INTO ordini (id_ordine, id_cliente, data_ordine, metodo_pagamento, stato_ordine, totale_ordine) VALUES
  (1, 1, '2026-05-12 10:30:00', 'carta', 'consegnato', 1239.97);

-- Inserimento diretto bypassando il trigger (dati storici), update manuale dello stock gia' eseguito sopra.
SET @TRIGGER_OFF = 1;
INSERT INTO dettaglio_ordini (id_dettaglio, id_ordine, id_prodotto, quantita_ordinata, prezzo_unitario_applicato, formato_acquisto) VALUES
  (1, 1, 1, 1, 979.99, 'Compra Subito'),
  (2, 1, 7, 2, 129.99, 'Compra Subito');
-- (lo stock degli inserts gia' include il decremento storico)
