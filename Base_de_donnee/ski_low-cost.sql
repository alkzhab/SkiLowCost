--
-- PostgreSQL database dump (corrected)
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

-- Reset schema proprement
DROP SCHEMA IF EXISTS public CASCADE;
CREATE SCHEMA public;
COMMENT ON SCHEMA public IS 'standard public schema';

SET search_path TO public;

-- =========================
-- TABLES
-- =========================

CREATE TABLE chambre (
    id_chambre integer PRIMARY KEY,
    numero_chambre integer NOT NULL,
    nombre_lits integer NOT NULL,
    superfecie real NOT NULL,
    balcon boolean NOT NULL,
    vue text NOT NULL CHECK (vue IN ('Parking','Piste','')),
    etage integer NOT NULL,
    batiment character varying(2) NOT NULL,
    libre boolean DEFAULT true
);

CREATE TABLE groupes (
    id_groupe integer PRIMARY KEY,
    nom character varying(255) NOT NULL UNIQUE
);

CREATE TABLE clients (
    id_client integer PRIMARY KEY,
    nom character varying(255) NOT NULL,
    prenom character varying(255) NOT NULL,
    date_naissance date NOT NULL,
    adresse text,
    email character varying(255),
    telephone character varying(50),
    niveau text NOT NULL CHECK (niveau IN ('débutant','moyen','confirmé')),
    taille integer NOT NULL,
    poids integer NOT NULL,
    pointure integer NOT NULL,
    formule text NOT NULL CHECK (formule IN ('skieur','non-skieur')),
    groupe_id integer
);

CREATE TABLE "user" (
    id_user integer PRIMARY KEY,
    identifiant character varying(255) NOT NULL,
    mot_de_passe character varying(255) NOT NULL,
    admin boolean NOT NULL
);

CREATE TABLE reservation (
    id_reservation integer PRIMARY KEY,
    id_groupe integer NOT NULL,
    date_de_debut date NOT NULL,
    date_de_fin date NOT NULL,
    tarif numeric(10,2) NOT NULL,
    chambre character varying(50),
    statut character varying(20) DEFAULT 'en attente'
);

CREATE TABLE log_actions (
    id integer PRIMARY KEY,
    id_user integer,
    action text NOT NULL,
    "timestamp" timestamp DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- SEQUENCES
-- =========================

CREATE SEQUENCE chambre_id_chambre_seq OWNED BY chambre.id_chambre;
ALTER TABLE chambre ALTER COLUMN id_chambre SET DEFAULT nextval('chambre_id_chambre_seq');

CREATE SEQUENCE groupes_id_groupe_seq OWNED BY groupes.id_groupe;
ALTER TABLE groupes ALTER COLUMN id_groupe SET DEFAULT nextval('groupes_id_groupe_seq');

CREATE SEQUENCE clients_id_client_seq OWNED BY clients.id_client;
ALTER TABLE clients ALTER COLUMN id_client SET DEFAULT nextval('clients_id_client_seq');

CREATE SEQUENCE user_id_user_seq OWNED BY "user".id_user;
ALTER TABLE "user" ALTER COLUMN id_user SET DEFAULT nextval('user_id_user_seq');

CREATE SEQUENCE reservation_id_reservation_seq OWNED BY reservation.id_reservation;
ALTER TABLE reservation ALTER COLUMN id_reservation SET DEFAULT nextval('reservation_id_reservation_seq');

CREATE SEQUENCE log_actions_id_seq OWNED BY log_actions.id;
ALTER TABLE log_actions ALTER COLUMN id SET DEFAULT nextval('log_actions_id_seq');

-- =========================
-- DONNÉES
-- =========================

INSERT INTO groupes VALUES
(23,'François'),
(24,'Foufou');

INSERT INTO chambre VALUES
(1,101,2,20.5,true,'Parking',1,'A',true),
(2,102,4,35,false,'Piste',1,'A',true),
(3,103,6,50,true,'Parking',1,'A',true),
(22,108,2,20.5,true,'Piste',1,'A',true);

INSERT INTO clients VALUES
(122,'François','Jean','1978-10-14','1 rue la cité de Tchoupi','jean@gmail.com','0707070707','moyen',183,80,45,'skieur',23),
(123,'François','Clara','1980-04-19',NULL,NULL,NULL,'débutant',170,60,38,'skieur',23);

INSERT INTO "user" VALUES
(7,'admin','$2y$10$hash',true),
(8,'zarzaski','$2y$10$hash',false);

INSERT INTO reservation VALUES
(5,23,'2025-06-08','2025-06-13',1578.00,'102','refusée'),
(6,24,'2025-06-15','2025-06-20',1020.00,'101','acceptée');

-- =========================
-- CLÉS ÉTRANGÈRES
-- =========================

ALTER TABLE clients
ADD CONSTRAINT fk_clients_groupes
FOREIGN KEY (groupe_id) REFERENCES groupes(id_groupe) ON DELETE SET NULL;

ALTER TABLE reservation
ADD CONSTRAINT fk_reservation_groupes
FOREIGN KEY (id_groupe) REFERENCES groupes(id_groupe) ON DELETE CASCADE;

ALTER TABLE log_actions
ADD CONSTRAINT fk_log_user
FOREIGN KEY (id_user) REFERENCES "user"(id_user) ON DELETE SET NULL;
