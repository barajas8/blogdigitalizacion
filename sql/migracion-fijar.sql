-- =============================================================
--  Migración: entradas fijadas en la portada
--  Solo si ya instalaste el blog ANTES de esta versión.
--  phpMyAdmin > tu base de datos > pestaña SQL > pegar > Continuar.
--  (En una instalación nueva no hace falta: schema.sql ya lo incluye.)
-- =============================================================
ALTER TABLE entradas
  ADD COLUMN fijada TINYINT(1) NOT NULL DEFAULT 0 AFTER estado,
  ADD KEY idx_entradas_fijada (estado, fijada, publicada_en);
