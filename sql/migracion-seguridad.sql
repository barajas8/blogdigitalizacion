-- =============================================================
--  Migración v3 (seguridad). NORMALMENTE NO HACE FALTA:
--  la web la aplica sola la primera vez que alguien entra al panel.
--  Úsala solo si el panel te muestra "Falta actualizar la base de datos".
--  phpMyAdmin > tu base de datos > pestaña SQL > pegar > Continuar.
--  Solo AÑADE columnas y una tabla: no borra ni cambia datos ni contraseñas.
-- =============================================================
ALTER TABLE usuarios
  ADD COLUMN totp_secret VARCHAR(64) NULL,
  ADD COLUMN totp_activo TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN totp_ultimo BIGINT NULL,
  ADD COLUMN totp_recovery TEXT NULL,
  ADD COLUMN sesion_version INT UNSIGNED NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS accesos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  ip VARCHAR(45) NOT NULL,
  agente VARCHAR(200) NOT NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_accesos_usuario (usuario_id, creado_en),
  CONSTRAINT fk_accesos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
