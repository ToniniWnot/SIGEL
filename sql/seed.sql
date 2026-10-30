-- Datos de prueba lo podemos ejecutar varias veces sin duplicar datos
-- El INSERT IGNORE omite las filas cuyo valor único ya existe
USE sigel;

-- Roles del sistema.
INSERT IGNORE INTO roles (nombre) VALUES
    ('administrador'),
    ('operador'),
    ('profesor');

-- Usuarios de prueba. Contraseñas (solo para pruebas):
--   admin@sigel.local     -> Admin123
--   operador@sigel.local  -> Operador123
-- Los profesores no inician sesión, por eso password_hash va en NULL.
-- El rol se busca por nombre para no depender del número de id.
INSERT IGNORE INTO usuarios (rol_id, nombre, correo, password_hash) VALUES
    ((SELECT id FROM roles WHERE nombre = 'administrador'), 'Administrador SIGEL', 'admin@sigel.local',
        '$2y$10$COCobk58O/5VhnyE49eOEeoCP1v9LHirBwUpcjM8bHnzHk3r/QodO'),
    ((SELECT id FROM roles WHERE nombre = 'operador'), 'Operador SIGEL', 'operador@sigel.local',
        '$2y$10$f97Czqg75qhPq1IKBRRfvOl3wtOMxEy5Esgz3ICG0y4CJUPa.L9DK'),
    ((SELECT id FROM roles WHERE nombre = 'profesor'), 'Ana Mora', 'ana.mora@sigel.local', NULL),
    ((SELECT id FROM roles WHERE nombre = 'profesor'), 'Luis Rojas', 'luis.rojas@sigel.local', NULL);