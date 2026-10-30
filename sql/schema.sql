-- =============================================================
--  Esquema del blog "Digitalización aplicada al sector productivo"
--  MySQL 5.7+ / MariaDB 10.3+  ·  InnoDB  ·  utf8mb4
--  Importable desde phpMyAdmin (pestaña "Importar") sobre la base
--  de datos ya creada en InfinityFree. No crea la base de datos.
--  Los 2 administradores NO se crean aquí: se crean con install.php
--  (las contraseñas tienen que pasar por password_hash de PHP).
-- =============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario        VARCHAR(40)  NOT NULL,
  nombre         VARCHAR(80)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  creado_en      DATETIME     NOT NULL,
  ultimo_acceso  DATETIME     NULL,
  totp_secret    VARCHAR(64)  NULL,
  totp_activo    TINYINT(1)   NOT NULL DEFAULT 0,
  totp_ultimo    BIGINT       NULL,
  totp_recovery  TEXT         NULL,
  sesion_version INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorias (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre       VARCHAR(80)  NOT NULL,
  slug         VARCHAR(100) NOT NULL,
  descripcion  VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS etiquetas (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre  VARCHAR(60)  NOT NULL,
  slug    VARCHAR(80)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_etiquetas_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS imagenes (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  archivo          VARCHAR(100) NOT NULL,
  nombre_original  VARCHAR(255) NOT NULL,
  tamano           INT UNSIGNED NOT NULL,
  ancho            SMALLINT UNSIGNED NOT NULL,
  alto             SMALLINT UNSIGNED NOT NULL,
  subida_por       INT UNSIGNED NULL,
  creada_en        DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_imagenes_archivo (archivo),
  CONSTRAINT fk_imagenes_usuario FOREIGN KEY (subida_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entradas (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo          VARCHAR(200) NOT NULL,
  slug            VARCHAR(220) NOT NULL,
  resumen         VARCHAR(400) NULL,
  contenido       MEDIUMTEXT   NOT NULL,
  imagen          VARCHAR(100) NULL,
  imagen_alt      VARCHAR(200) NULL,
  categoria_id    INT UNSIGNED NULL,
  autor_id        INT UNSIGNED NOT NULL,
  editada_por     INT UNSIGNED NULL,
  estado          ENUM('borrador','publicada') NOT NULL DEFAULT 'borrador',
  fijada          TINYINT(1)   NOT NULL DEFAULT 0,
  creada_en       DATETIME NOT NULL,
  actualizada_en  DATETIME NOT NULL,
  publicada_en    DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entradas_slug (slug),
  KEY idx_entradas_publicacion (estado, publicada_en),
  KEY idx_entradas_fijada (estado, fijada, publicada_en),
  KEY idx_entradas_categoria (categoria_id),
  CONSTRAINT fk_entradas_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE SET NULL,
  CONSTRAINT fk_entradas_autor     FOREIGN KEY (autor_id)     REFERENCES usuarios (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_entradas_editor    FOREIGN KEY (editada_por)  REFERENCES usuarios (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entrada_etiqueta (
  entrada_id   INT UNSIGNED NOT NULL,
  etiqueta_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (entrada_id, etiqueta_id),
  KEY idx_ee_etiqueta (etiqueta_id),
  CONSTRAINT fk_ee_entrada  FOREIGN KEY (entrada_id)  REFERENCES entradas (id)  ON DELETE CASCADE,
  CONSTRAINT fk_ee_etiqueta FOREIGN KEY (etiqueta_id) REFERENCES etiquetas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS paginas (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug             VARCHAR(60)  NOT NULL,
  titulo           VARCHAR(200) NOT NULL,
  contenido        MEDIUMTEXT   NOT NULL,
  actualizada_en   DATETIME     NOT NULL,
  actualizada_por  INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_paginas_slug (slug),
  CONSTRAINT fk_paginas_usuario FOREIGN KEY (actualizada_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS intentos_login (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip         VARCHAR(45) NOT NULL,
  usuario    VARCHAR(40) NOT NULL,
  creado_en  DATETIME    NOT NULL,
  PRIMARY KEY (id),
  KEY idx_intentos_ip (ip, creado_en),
  KEY idx_intentos_usuario (usuario, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accesos (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id  INT UNSIGNED NOT NULL,
  ip          VARCHAR(45)  NOT NULL,
  agente      VARCHAR(200) NOT NULL,
  creado_en   DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_accesos_usuario (usuario_id, creado_en),
  CONSTRAINT fk_accesos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Juegos para repasar las entradas (se crean en Panel > Juegos; empieza vacía)
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

-- Páginas editables desde el panel (texto inicial de ejemplo, se cambia en Panel > Páginas)
INSERT IGNORE INTO paginas (slug, titulo, contenido, actualizada_en) VALUES
('portada', 'Texto de portada',
'Aquí recogemos lo que aprendemos en el módulo: cómo los sensores, las redes industriales, la nube y los datos están cambiando la forma de fabricar y de gestionar una empresa.\n\nEdita este texto desde el panel, en **Páginas > Texto de portada**.',
NOW()),
('sobre-nosotros', 'Sobre nosotros y la asignatura',
'## La asignatura\n\n*Digitalización aplicada al sector productivo* es un módulo del ciclo de Administración de Sistemas Informáticos en Red (ASIR).\n\n## El equipo\n\nEdita esta página desde el panel, en **Páginas > Sobre nosotros**.',
NOW());
