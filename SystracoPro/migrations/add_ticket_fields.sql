-- Migration: Add new ticket fields (type_passager, somme_percu, reliquat, observation, date_heure)
-- Run this in phpMyAdmin or MySQL CLI

ALTER TABLE tickets
    ADD COLUMN type_passager ENUM('adulte','enfant') DEFAULT 'adulte' AFTER transit_destination,
    ADD COLUMN somme_percu DECIMAL(10,2) DEFAULT 0 AFTER type_passager,
    ADD COLUMN reliquat DECIMAL(10,2) DEFAULT 0 AFTER somme_percu,
    ADD COLUMN observation TEXT AFTER reliquat,
    ADD COLUMN date_heure DATETIME DEFAULT NULL AFTER observation;