-- Datos de prueba lo podemos ejecutar varias veces sin duplicar datos
-- El INSERT IGNORE omite las filas cuyo valor único ya existe
USE sigel;

-- Roles del sistema.
INSERT IGNORE INTO roles (nombre) VALUES
    ('administrador'),
    ('operador'),
    ('profesor');

-- Usuarios de prueba y sus contraseñas:
--   admin@sigel.local     -> Admin123
--   operador@sigel.local  -> Operador123
-- Los profesores no inician sesión, por eso password_hash va en NULL
-- El rol se busca por nombre para no depender del número de id
INSERT IGNORE INTO usuarios (rol_id, nombre, correo, password_hash) VALUES
    ((SELECT id FROM roles WHERE nombre = 'administrador'), 'Administrador SIGEL', 'admin@sigel.local',
        '$2y$10$COCobk58O/5VhnyE49eOEeoCP1v9LHirBwUpcjM8bHnzHk3r/QodO'),
    ((SELECT id FROM roles WHERE nombre = 'operador'), 'Operador SIGEL', 'operador@sigel.local',
        '$2y$10$f97Czqg75qhPq1IKBRRfvOl3wtOMxEy5Esgz3ICG0y4CJUPa.L9DK'),
    ((SELECT id FROM roles WHERE nombre = 'profesor'), 'Ana Mora', 'ana.mora@sigel.local', NULL),
    ((SELECT id FROM roles WHERE nombre = 'profesor'), 'Luis Rojas', 'luis.rojas@sigel.local', NULL);

-- Categorías de equipos. dias_max_prestamo: duración máxima del préstamo.
INSERT IGNORE INTO categorias (nombre, dias_max_prestamo) VALUES
    ('Medición', 7),
    ('Cómputo', 14),
    ('Audiovisual', 3);

-- Equipos de prueba y la categoría se busca por nombre
-- El último está en mantenimiento, para probar que no se pueda prestar :D
INSERT IGNORE INTO equipos (categoria_id, codigo, nombre, estado) VALUES
    ((SELECT id FROM categorias WHERE nombre = 'Medición'), 'OSC-001', 'Osciloscopio digital', 'disponible'),
    ((SELECT id FROM categorias WHERE nombre = 'Medición'), 'MUL-001', 'Multímetro Fluke', 'disponible'),
    ((SELECT id FROM categorias WHERE nombre = 'Cómputo'), 'LAP-001', 'Laptop Dell Latitude', 'disponible'),
    ((SELECT id FROM categorias WHERE nombre = 'Cómputo'), 'LAP-002', 'Laptop Lenovo ThinkPad', 'disponible'),
    ((SELECT id FROM categorias WHERE nombre = 'Audiovisual'), 'PRO-001', 'Proyector Epson', 'disponible'),
    ((SELECT id FROM categorias WHERE nombre = 'Audiovisual'), 'CAM-001', 'Cámara Canon', 'mantenimiento');

-- Préstamos de ejemplo en todos los estados, para que el listado y el reporte tengan datos
-- Inserción omite préstamos existentes
-- usuario_id es el profesor que recibe el equipo y operador_id quien lo registró
INSERT INTO prestamos (usuario_id, operador_id, estado, fecha_inicio, fecha_fin)
SELECT (SELECT id FROM usuarios WHERE correo = 'ana.mora@sigel.local'),
       (SELECT id FROM usuarios WHERE correo = 'operador@sigel.local'),
       'devuelto', '2026-11-02 08:00:00', '2026-11-04 08:00:00'
WHERE NOT EXISTS (SELECT 1 FROM prestamos WHERE fecha_inicio = '2026-11-02 08:00:00');

INSERT INTO prestamos (usuario_id, operador_id, estado, fecha_inicio, fecha_fin)
SELECT (SELECT id FROM usuarios WHERE correo = 'luis.rojas@sigel.local'),
       (SELECT id FROM usuarios WHERE correo = 'operador@sigel.local'),
       'devuelto', '2026-11-05 09:00:00', '2026-11-07 09:00:00'
WHERE NOT EXISTS (SELECT 1 FROM prestamos WHERE fecha_inicio = '2026-11-05 09:00:00');

INSERT INTO prestamos (usuario_id, operador_id, estado, fecha_inicio, fecha_fin)
SELECT (SELECT id FROM usuarios WHERE correo = 'ana.mora@sigel.local'),
       (SELECT id FROM usuarios WHERE correo = 'operador@sigel.local'),
       'devuelto', '2026-11-09 10:00:00', '2026-11-11 10:00:00'
WHERE NOT EXISTS (SELECT 1 FROM prestamos WHERE fecha_inicio = '2026-11-09 10:00:00');

INSERT INTO prestamos (usuario_id, operador_id, estado, fecha_inicio, fecha_fin)
SELECT (SELECT id FROM usuarios WHERE correo = 'ana.mora@sigel.local'),
       (SELECT id FROM usuarios WHERE correo = 'operador@sigel.local'),
       'cancelado', '2026-11-12 09:00:00', '2026-11-13 09:00:00'
WHERE NOT EXISTS (SELECT 1 FROM prestamos WHERE fecha_inicio = '2026-11-12 09:00:00');

INSERT INTO prestamos (usuario_id, operador_id, estado, fecha_inicio, fecha_fin)
SELECT (SELECT id FROM usuarios WHERE correo = 'luis.rojas@sigel.local'),
       (SELECT id FROM usuarios WHERE correo = 'operador@sigel.local'),
       'entregado', '2026-11-14 08:00:00', '2026-11-18 17:00:00'
WHERE NOT EXISTS (SELECT 1 FROM prestamos WHERE fecha_inicio = '2026-11-14 08:00:00');

INSERT INTO prestamos (usuario_id, operador_id, estado, fecha_inicio, fecha_fin)
SELECT (SELECT id FROM usuarios WHERE correo = 'ana.mora@sigel.local'),
       (SELECT id FROM usuarios WHERE correo = 'operador@sigel.local'),
       'aprobado', '2026-11-20 09:00:00', '2026-11-22 09:00:00'
WHERE NOT EXISTS (SELECT 1 FROM prestamos WHERE fecha_inicio = '2026-11-20 09:00:00');

INSERT INTO prestamos (usuario_id, operador_id, estado, fecha_inicio, fecha_fin)
SELECT (SELECT id FROM usuarios WHERE correo = 'luis.rojas@sigel.local'),
       (SELECT id FROM usuarios WHERE correo = 'operador@sigel.local'),
       'solicitado', '2026-11-25 09:00:00', '2026-11-27 09:00:00'
WHERE NOT EXISTS (SELECT 1 FROM prestamos WHERE fecha_inicio = '2026-11-25 09:00:00');

-- Equipos de cada préstamo, las fk evitan repetirlos al ejecutar de nuevo
-- Los devueltos traen fecha y condición de devolución
INSERT IGNORE INTO prestamo_detalle (prestamo_id, equipo_id, fecha_devolucion, condicion_devolucion)
SELECT p.id, e.id, '2026-11-04 07:45:00', 'bueno'
FROM prestamos p JOIN equipos e ON e.codigo IN ('OSC-001', 'LAP-001')
WHERE p.fecha_inicio = '2026-11-02 08:00:00';

INSERT IGNORE INTO prestamo_detalle (prestamo_id, equipo_id, fecha_devolucion, condicion_devolucion)
SELECT p.id, e.id, '2026-11-07 08:50:00', 'bueno'
FROM prestamos p JOIN equipos e ON e.codigo IN ('OSC-001', 'MUL-001')
WHERE p.fecha_inicio = '2026-11-05 09:00:00';

INSERT IGNORE INTO prestamo_detalle (prestamo_id, equipo_id, fecha_devolucion, condicion_devolucion)
SELECT p.id, e.id, '2026-11-11 09:30:00', 'bueno'
FROM prestamos p JOIN equipos e ON e.codigo = 'PRO-001'
WHERE p.fecha_inicio = '2026-11-09 10:00:00';

INSERT IGNORE INTO prestamo_detalle (prestamo_id, equipo_id)
SELECT p.id, e.id FROM prestamos p JOIN equipos e ON e.codigo = 'LAP-002'
WHERE p.fecha_inicio = '2026-11-12 09:00:00';

INSERT IGNORE INTO prestamo_detalle (prestamo_id, equipo_id)
SELECT p.id, e.id FROM prestamos p JOIN equipos e ON e.codigo = 'LAP-001'
WHERE p.fecha_inicio = '2026-11-14 08:00:00';

INSERT IGNORE INTO prestamo_detalle (prestamo_id, equipo_id)
SELECT p.id, e.id FROM prestamos p JOIN equipos e ON e.codigo = 'PRO-001'
WHERE p.fecha_inicio = '2026-11-20 09:00:00';

INSERT IGNORE INTO prestamo_detalle (prestamo_id, equipo_id)
SELECT p.id, e.id FROM prestamos p JOIN equipos e ON e.codigo IN ('OSC-001', 'MUL-001')
WHERE p.fecha_inicio = '2026-11-25 09:00:00';