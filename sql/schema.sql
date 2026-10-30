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