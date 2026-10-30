-- Esquema de la base de datos
-- Se ejecuta solo una vez para crear la base y las tablas necesarias
CREATE DATABASE IF NOT EXISTS sigel
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sigel;

-- Catalogo de roles como administrador, operador y profesor
CREATE TABLE IF NOT EXISTS roles (
    id INT NOT NULL AUTO_INCREMENT, 
    nombre VARCHAR(30) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (nombre)
);

-- Usuarios del sistema. Los profesores existen
-- pero como un registros (son los que reciben préstamos)
-- NO inician sesión en el sistema, por eso password_hash es NULL.
-- Ingresan solo operadores y administradores.
CREATE TABLE IF NOT EXISTS usuarios (
    id INT NOT NULL AUTO_INCREMENT,
    rol_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    intentos_fallidos INT NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    eliminado_en DATETIME NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE (correo),
    FOREIGN KEY (rol_id) REFERENCES roles(id)
);

-- Categorias de equipos. Tiene dias maximos de prestamo
-- limita la duracion de un prestamo.
CREATE TABLE IF NOT EXISTS categorias (
    id INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(80) NOT NULL,
    dias_max_prestamo INT NOT NULL DEFAULT 7,
    eliminado_en DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE (nombre)
);

-- Equipos del laboratorio. Estado: dispoible o mantenimiento.
CREATE TABLE IF NOT EXISTS equipos (
    id INT NOT NULL AUTO_INCREMENT,
    categoria_id INT NOT NULL,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'disponible',
    eliminado_en DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE (codigo),
    FOREIGN KEY (categoria_id) REFERENCES categorias (id)
);

-- Imagenes de un equipo (puede tener varias imagenes)
-- ruta guarda el nombre del archivo
CREATE TABLE IF NOT EXISTS equipo_imagenes (
    id INT NOT NULL AUTO_INCREMENT,
    equipo_id INT NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (equipo_id) REFERENCES equipos (id)
);

-- Prestamos de equipos. usuario_id es el profesor que recibe el equipo (no inicia sesion)
-- operador_id es el usuario que registra el prestamo.
-- estado: solicitado, aprobado
-- entregado, devuelto, cancelado
CREATE TABLE IF NOT EXISTS prestamos (
    id INT NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    operador_id INT NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'solicitado',
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    FOREIGN KEY (operador_id) REFERENCES usuarios (id)
);

-- Equipos incluidos en cada prestamo ya que
-- un prestamo puede incluir varios equipos.
-- fecha_devolucion queda NULL hasta que el equipo sea devuelto.
CREATE TABLE IF NOT EXISTS prestamo_detalle (
    prestamo_id INT NOT NULL,
    equipo_id INT NOT NULL,
    fecha_devolucion DATETIME NULL,
    PRIMARY KEY (prestamo_id, equipo_id),
    FOREIGN KEY (prestamo_id) REFERENCES prestamos (id),
    FOREIGN KEY (equipo_id) REFERENCES equipos (id)
);