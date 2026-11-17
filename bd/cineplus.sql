-- =====================================================================
--  Cine+  ·  Modelo de la base de datos (tablas, llaves y roles fijos; sin datos de ejemplo)
--  Motor: MySQL 8 (compatible con MariaDB 10.6+ / XAMPP)
-- =====================================================================
DROP DATABASE IF EXISTS cineplus;
CREATE DATABASE cineplus CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE cineplus;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Catálogos
-- ---------------------------------------------------------------------
CREATE TABLE Rol (
  id_rol      TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(30)      NOT NULL,
  PRIMARY KEY (id_rol),
  UNIQUE KEY uq_rol_nombre (nombre)
) ENGINE=InnoDB;

-- Roles fijos del sistema (no son datos de ejemplo): el panel los busca por nombre.
INSERT INTO Rol (id_rol, nombre) VALUES
  (1, 'Administrador'),
  (2, 'Cliente');

CREATE TABLE Genero (
  id_genero   SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(50)       NOT NULL,
  descripcion VARCHAR(200)      NULL,
  PRIMARY KEY (id_genero),
  UNIQUE KEY uq_genero_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE Formato (
  id_formato  TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(30)      NOT NULL,
  descripcion VARCHAR(200)     NULL,
  PRIMARY KEY (id_formato),
  UNIQUE KEY uq_formato_nombre (nombre)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Usuarios
-- ---------------------------------------------------------------------
CREATE TABLE Usuario (
  id_usuario       INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  id_rol           TINYINT UNSIGNED NOT NULL,
  nombre           VARCHAR(100)     NOT NULL,
  correo           VARCHAR(120)     NOT NULL,
  contrasena_hash  VARCHAR(255)     NOT NULL,
  telefono         VARCHAR(15)      NULL,
  pais_region      VARCHAR(60)      NULL,
  foto             VARCHAR(255)     NULL,
  estado           ENUM('Activo','Suspendido') NOT NULL DEFAULT 'Activo',
  fecha_registro   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso    DATETIME         NULL,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuario_correo (correo),
  CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES Rol (id_rol)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Películas (cada registro es un título en un formato)
-- ---------------------------------------------------------------------
CREATE TABLE Pelicula (
  id_pelicula        INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  id_genero          SMALLINT UNSIGNED NOT NULL,
  id_formato         TINYINT UNSIGNED  NOT NULL,
  titulo             VARCHAR(150)      NOT NULL,
  director           VARCHAR(100)      NOT NULL,
  anio               SMALLINT UNSIGNED NULL,
  sinopsis           TEXT              NULL,
  calificacion       DECIMAL(3,1)      NULL,
  precio             DECIMAL(10,2)     NOT NULL,
  existencia         INT UNSIGNED      NOT NULL DEFAULT 0,
  imagen             VARCHAR(255)      NULL,
  estado             ENUM('Publicado','Borrador') NOT NULL DEFAULT 'Borrador',
  fecha_registro     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pelicula),
  KEY ix_pelicula_titulo (titulo),
  CONSTRAINT fk_pelicula_genero  FOREIGN KEY (id_genero)  REFERENCES Genero (id_genero)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pelicula_formato FOREIGN KEY (id_formato) REFERENCES Formato (id_formato)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT ck_pelicula_calificacion CHECK (calificacion IS NULL OR calificacion BETWEEN 0 AND 10),
  CONSTRAINT ck_pelicula_precio CHECK (precio >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Pedidos (compra simulada)
-- ---------------------------------------------------------------------
CREATE TABLE Pedido (
  id_pedido    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_usuario   INT UNSIGNED  NULL,          -- queda NULL si se elimina el usuario
  fecha        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  subtotal     DECIMAL(10,2) NOT NULL,
  impuestos    DECIMAL(10,2) NOT NULL DEFAULT 0,
  total        DECIMAL(10,2) NOT NULL,
  metodo_pago  VARCHAR(30)   NOT NULL,      -- solo el nombre del método, sin datos de tarjeta
  estado_pago  ENUM('Pendiente','Aprobado','Rechazado') NOT NULL DEFAULT 'Aprobado',
  estado       ENUM('Confirmado','Enviado','Entregado','Cancelado') NOT NULL DEFAULT 'Confirmado',
  PRIMARY KEY (id_pedido),
  CONSTRAINT fk_pedido_usuario FOREIGN KEY (id_usuario) REFERENCES Usuario (id_usuario)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE Envio (
  id_envio      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  id_pedido     INT UNSIGNED  NOT NULL,
  destinatario  VARCHAR(100)  NOT NULL,
  telefono      VARCHAR(15)   NOT NULL,
  direccion     VARCHAR(200)  NOT NULL,
  codigo_postal CHAR(5)       NOT NULL,
  ciudad        VARCHAR(80)   NOT NULL,
  metodo        ENUM('Estándar','Exprés') NOT NULL DEFAULT 'Estándar',
  costo         DECIMAL(10,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id_envio),
  UNIQUE KEY uq_envio_pedido (id_pedido),   -- garantiza la relación 1 a 1
  CONSTRAINT fk_envio_pedido FOREIGN KEY (id_pedido) REFERENCES Pedido (id_pedido)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Detalle_Pedido (
  id_pedido       INT UNSIGNED  NOT NULL,
  id_pelicula     INT UNSIGNED  NOT NULL,
  cantidad        INT UNSIGNED  NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,   -- precio al momento de la compra
  PRIMARY KEY (id_pedido, id_pelicula),     -- llave primaria compuesta por las dos llaves foráneas
  CONSTRAINT fk_detalle_pedido   FOREIGN KEY (id_pedido)   REFERENCES Pedido (id_pedido)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detalle_pelicula FOREIGN KEY (id_pelicula) REFERENCES Pelicula (id_pelicula)
    ON UPDATE CASCADE ON DELETE RESTRICT,   -- una película con ventas no se borra: se pasa a Borrador
  CONSTRAINT ck_detalle_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB;

-- Las estadísticas del panel administrativo no se guardan en la base de datos:
-- se calculan en vivo con consultas SELECT (SUM, COUNT, GROUP BY) desde PHP
-- sobre Pedido, Detalle_Pedido y Pelicula cada vez que se abre el panel.
