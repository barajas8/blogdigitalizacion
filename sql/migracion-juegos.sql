-- =============================================================
--  Migración: sección de Juegos (versión 3.1)
--  Normalmente NO hace falta: la tabla se crea sola la primera vez
--  que entras en Panel > Juegos o en /juegos.
--  Úsala solo si ves el aviso «Falta actualizar la base de datos».
--  phpMyAdmin > tu base de datos > pestaña SQL > pega esto > Continuar.
-- =============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS juegos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo          VARCHAR(120) NOT NULL,
  tipo            VARCHAR(20)  NOT NULL,
  descripcion     VARCHAR(255) NULL,
  contenido       MEDIUMTEXT   NOT NULL,
  por_partida     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  orden           INT NOT NULL DEFAULT 0,
  estado          ENUM('borrador','publicado') NOT NULL DEFAULT 'borrador',
  creado_en       DATETIME NOT NULL,
  actualizado_en  DATETIME NOT NULL,
  actualizado_por INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_juegos_estado (estado, orden),
  CONSTRAINT fk_juegos_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
