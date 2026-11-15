<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bitacora.php';
require_once __DIR__ . '/helpers.php';

// Estados de un prestamo, la llave es lo que se guarda en la BD
const PRESTAMO_ESTADOS = [
    'solicitado' => 'Solicitado',
    'aprobado' => 'Aprobado',
    'entregado' => 'Entregado',
    'cancelado' => 'Cancelado',
    'devuelto' => 'Devuelto'
];

// A que estados se puede pasar desde cada estado, devuelto y cancelado son finales
const TRANSICIONES_PRESTAMO = [
    'solicitado' => ['aprobado', 'cancelado'],
    'aprobado' => ['entregado', 'cancelado'],
    'entregado' => ['devuelto'],
    'cancelado' => [],
    'devuelto' => []
];

// Maximo de equipos en un solo prestamo
const MAX_EQUIPOS_PRESTAMO = 10;

// Cuenta los prestamos que coincides con la busqueda como profesor, equipo o estado
function contarPrestamos(PDO $pdo, string $busqueda): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM prestamos p
                           JOIN usuarios u ON u.id = p.usuario_id
                           WHERE (u.nombre LIKE :q1 OR p.estado LIKE :q2 OR EXISTS (
                               SELECT 1 FROM prestamo_detalle d
                               JOIN equipos e ON e.id = d.equipo_id
                               WHERE d.prestamo_id = p.id AND (e.codigo LIKE :q3 OR e.nombre LIKE :q4)))');
    $patron = patronBusqueda($busqueda);
    $stmt->execute([':q1' => $patron, ':q2' => $patron, ':q3' => $patron, ':q4' => $patron]);
    return (int) $stmt->fetchColumn();
}

// Trae una pagina de prestamos, con el nombre del profe y los codigos de sus equipos juntos
function listarPrestamos(PDO $pdo, string $busqueda, int $limite, int $offset): array {
    $stmt = $pdo->prepare("SELECT p.id, p.estado, p.fecha_inicio, p.fecha_fin, u.nombre AS profesor,
                                  (SELECT GROUP_CONCAT(e.codigo ORDER BY e.codigo SEPARATOR ', ')
                                   FROM prestamo_detalle d JOIN equipos e ON e.id = d.equipo_id
                                   WHERE d.prestamo_id = p.id) AS equipos
                           FROM prestamos p
                           JOIN usuarios u ON u.id = p.usuario_id
                           WHERE (u.nombre LIKE :q1 OR p.estado LIKE :q2 OR EXISTS (
                               SELECT 1 FROM prestamo_detalle d
                               JOIN equipos e ON e.id = d.equipo_id
                               WHERE d.prestamo_id = p.id AND (e.codigo LIKE :q3 OR e.nombre LIKE :q4)))
                           ORDER BY p.id DESC
                           LIMIT :limite OFFSET :offset");
    $patron = patronBusqueda($busqueda);
    $stmt->bindValue(':q1', $patron);
    $stmt->bindValue(':q2', $patron);
    $stmt->bindValue(':q3', $patron);
    $stmt->bindValue(':q4', $patron);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Busca un pretamo con su profe y la lista de equipos si no existe devuelve null
function buscarPrestamo(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT p.id, p.estado, p.fecha_inicio, p.fecha_fin, u.nombre AS profesor
                           FROM prestamos p JOIN usuarios u ON u.id = p.usuario_id
                           WHERE p.id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $prestamo = $stmt->fetch();
    if ($prestamo === false) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT e.id, e.codigo, e.nombre
                           FROM prestamo_detalle d JOIN equipos e ON e.id = d.equipo_id
                           WHERE d.prestamo_id = :id ORDER BY e.codigo');
    $stmt->execute([':id' => $id]);
    $prestamo['equipos'] = $stmt->fetchAll();
    return $prestamo;
}

// Profesores que pueden recibir un prestamo, no se loguean pero si aparecen en el select
function listarProfesores(PDO $pdo): array {
    return $pdo->query("SELECT u.id, u.nombre FROM usuarios u
                        JOIN roles r ON r.id = u.rol_id
                        WHERE r.nombre = 'profesor' AND u.activo = 1 AND u.eliminado_en IS NULL
                        ORDER BY u.nombre")->fetchAll();
}

// Equipos que se pueden prestar ahora, para las casillas del formulario
function listarEquiposDisponibles(PDO $pdo): array {
    return $pdo->query("SELECT e.id, e.codigo, e.nombre, c.nombre AS categoria, c.dias_max_prestamo
                        FROM equipos e JOIN categorias c ON c.id = e.categoria_id
                        WHERE e.eliminado_en IS NULL AND e.estado = 'disponible'
                        ORDER BY e.codigo")->fetchAll();
}

// Deja lo que llegó del formulario limpio y con el tipo correcto
function datosPrestamoDesdePost(array $post): array {
    // Los equipos llegan como un arreglo equipos[], cualquier otra forma se trata como vacía
    $recibidos = isset($post['equipos']) && is_array($post['equipos']) ? $post['equipos'] : [];
    $equipos = [];
    foreach ($recibidos as $valor) {
        // Un valor que no sea un entero positivo queda como 0 y la validación lo rechaza
        $id = is_string($valor) ? filter_var($valor, FILTER_VALIDATE_INT) : false;
        $equipos[] = ($id !== false && $id >= 1) ? $id : 0;
    }

    return [
        'profesor_id' => filter_var(textoPost($post, 'profesor_id'), FILTER_VALIDATE_INT) ?: 0,
        'fecha_inicio' => trim(textoPost($post, 'fecha_inicio')),
        'fecha_fin' => trim(textoPost($post, 'fecha_fin')),
        'equipos' => array_values(array_unique($equipos)),
    ];
}

// Convierte el texto de un campo datetime-local (2026-11-20T09:30) en fecha o null si no es valido
function parsearFecha(string $texto): ? DateTimeImmutable {
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $texto);
    // createFromFormat acepta cosas como 2026-02-31, comparar con el formato original lo evita
    if ($fecha === false || $fecha->format('Y-m-d\TH:i') !== $texto) {
        return null;
    }
    return $fecha;
}

// Revisa que el profe exista que esté activo y que no esté eliminado, y tenga rol 'profesor'
function profesorValido(PDO $pdo, int $id): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id = u.rol_id
                           WHERE u.id = :id AND r.nombre = 'profesor' AND u.activo = 1 AND u.eliminado_en IS NULL");
    $stmt->execute([':id' => $id]);
    return (int) $stmt->fetchColumn() > 0;
}

// Revisa las reglas de los equipos de un prestamo, que existan y que estén disponibles,
// además que la duracion respete el maximo de su categoria y que no se traslapen con otro prestamo activo
// Con $bloquear en true usa FOR UPDATE y solo se debe usar dentro de una transaccion 
function revisarEquiposPrestamo(PDO $pdo, array $datos, bool $bloquear): ?string {
    $inicio = parsearFecha($datos['fecha_inicio']);
    $fin = parsearFecha($datos['fecha_fin']);
    $ids = $datos['equipos'];
    if ($inicio === null || $fin === null) {
        return 'invalid_dates';
    }
    if (count($ids) === 0) {
        return 'invalid_equipment_selection';
    }
    // Siempre en el mismo orden, así dos préstamos a la vez no se bloquean entre sí
    sort($ids);

    // Un id en 0 significa que llegó algo que no era un entero positivo, o sea un formulario manipulado
    if (in_array(0, $ids, true)) {
        return 'equipment_unavailable';
    }

    $marcas = implode(',', array_fill(0, count($ids), '?'));

    // Los equipos tienen que existir, no estar eliminados y estar disponibles
    $stmt = $pdo->prepare("SELECT id, estado, categoria_id FROM equipos
                           WHERE id IN ($marcas) AND eliminado_en IS NULL
                           ORDER BY id" . ($bloquear ? ' FOR UPDATE' : ''));
    $stmt->execute($ids);
    $equipos = $stmt->fetchAll();
    if (count($equipos) !== count($ids)) {
        return 'equipment_unavailable';
    }
    $categorias = [];
    foreach ($equipos as $equipo) {
        if ($equipo['estado'] !== 'disponible') {
            return 'equipment_unavailable';
        }
        $categorias[(int) $equipo['categoria_id']] = true;
    }

    // La duración no puede pasar del máximo más bajo entre las categorías de los equipos
    $idsCategorias = array_keys($categorias);
    $marcasCat = implode(',', array_fill(0, count($idsCategorias), '?'));
    $stmt = $pdo->prepare("SELECT MIN(dias_max_prestamo) FROM categorias WHERE id IN ($marcasCat)");
    $stmt->execute($idsCategorias);
    $maxDias = (int) $stmt->fetchColumn();
    if ($fin->getTimestamp() - $inicio->getTimestamp() > $maxDias * 86400) {
        return 'loan_too_long';
    }

    // Traslape: existente.inicio < nuevo.fin AND existente.fin > nuevo.inicio
    // Solo cuentan los préstamos activos, uno devuelto o cancelado ya no ocupa el equipo
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM prestamo_detalle d
                           JOIN prestamos p ON p.id = d.prestamo_id
                           WHERE d.equipo_id IN ($marcas)
                             AND p.estado IN ('solicitado', 'aprobado', 'entregado')
                             AND p.fecha_inicio < ? AND p.fecha_fin > ?");
    $stmt->execute(array_merge($ids, [$fin->format('Y-m-d H:i:s'), $inicio->format('Y-m-d H:i:s')]));
    if ((int) $stmt->fetchColumn() > 0) {
        return 'loan_overlap';
    }

    return null;
}

// Valida un préstamo antes de crearlo, devuelve un código de error o null si todo está bien
function validarPrestamo(PDO $pdo, array $datos): ?string {
    if (!profesorValido($pdo, $datos['profesor_id'])) {
        return 'invalid_professor';
    }

    $inicio = parsearFecha($datos['fecha_inicio']);
    $fin = parsearFecha($datos['fecha_fin']);
    if ($inicio === null || $fin === null || $fin <= $inicio) {
        return 'invalid_dates';
    }
    // El préstamo no puede empezar en un día que ya pasó
    if ($inicio < new DateTimeImmutable('today')) {
        return 'date_in_past';
    }

    $cantidad = count($datos['equipos']);
    if ($cantidad < 1 || $cantidad > MAX_EQUIPOS_PRESTAMO) {
        return 'invalid_equipment_selection';
    }

    return revisarEquiposPrestamo($pdo, $datos, false);
}

// Crea un prestamo en estado solicitado con todos sus equipos o no se crea nada
// Devuelve null si se crea correctamente, o el codigo de error si no se pudo
function crearPrestamo(PDO $pdo, array $datos, int $actorId): ?string {
    $pdo->beginTransaction();
    try {
        // Se revisa otra vez dentro de la transaccion y con los equipos bloqueados, FOR UPDATE
        // Si otro operador está creando un prestamos del mismo equipo, este espera su turno
        $error = revisarEquiposPrestamo($pdo, $datos, true);
        if ($error !== null) {
            $pdo->rollBack();
            return $error;
        }

        $inicio = parsearFecha($datos['fecha_inicio'])->format('Y-m-d H:i:s');
        $fin = parsearFecha($datos['fecha_fin'])->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare("INSERT INTO prestamos (usuario_id, operador_id, estado, fecha_inicio, fecha_fin)
                               VALUES (:profesor, :operador, 'solicitado', :inicio, :fin)");
        $stmt->execute([':profesor' => $datos['profesor_id'], ':operador' => $actorId, ':inicio' => $inicio, ':fin' => $fin]);
        $id = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO prestamo_detalle (prestamo_id, equipo_id) VALUES (:prestamo, :equipo)');
        foreach ($datos['equipos'] as $equipoId) {
            $stmt->execute([':prestamo' => $id, ':equipo' => $equipoId]);
        }

        registrarBitacora($pdo, $actorId, 'crear', 'prestamo', $id, null, [
            'profesor_id' => $datos['profesor_id'],
            'estado' => 'solicitado',
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'equipos' => $datos['equipos'],
        ]);

        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Pasa un prestamo a aprobado, entregado o cancelado
// Devuelve null si se pudo, o loan_not_found', 'invalid_transition', 'equipment_unavailable'
// La devolucion es aparte, porque necesita la condicion de cada equipo
function cambiarEstadoPrestamo(PDO $pdo, int $id, string $nuevoEstado, int $actorId): ?string {
    if (!in_array($nuevoEstado, ['aprobado', 'entregado', 'cancelado'], true)) {
        return 'invalid_transition';
    }

    $pdo->beginTransaction();
    try {
        // Se bloquea el préstamo para que dos operadores no lo cambien a la vez
        $stmt = $pdo->prepare('SELECT estado FROM prestamos WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $estadoActual = $stmt->fetchColumn();
        if ($estadoActual === false) {
            $pdo->rollBack();
            return 'loan_not_found';
        }

        // Solo se permiten los pasos de la tabla de transiciones
        if (!in_array($nuevoEstado, TRANSICIONES_PRESTAMO[$estadoActual] ?? [], true)) {
            $pdo->rollBack();
            return 'invalid_transition';
        }

        // Para entregar, todos los equipos tienen que seguir disponibles
        // porque el administrador pudo ponerlos en mantenimiento o dañados mientras tanto
        if ($nuevoEstado === 'entregado') {
            $stmt = $pdo->prepare("SELECT e.estado, e.eliminado_en FROM prestamo_detalle d
                                   JOIN equipos e ON e.id = d.equipo_id
                                   WHERE d.prestamo_id = :id ORDER BY e.id FOR UPDATE");
            $stmt->execute([':id' => $id]);
            foreach ($stmt->fetchAll() as $equipo) {
                if ($equipo['estado'] !== 'disponible' || $equipo['eliminado_en'] !== null) {
                    $pdo->rollBack();
                    return 'equipment_unavailable';
                }
            }
        }

        $stmt = $pdo->prepare('UPDATE prestamos SET estado = :estado WHERE id = :id');
        $stmt->execute([':estado' => $nuevoEstado, ':id' => $id]);

        registrarBitacora($pdo, $actorId, 'editar', 'prestamo', $id,
            ['estado' => $estadoActual], ['estado' => $nuevoEstado]);

        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Mi regla individual #9
// Registra la devololucion de un prestamo entregado
// $condiciones trae la condicion de cada equipo: ['id_equipo' => 'bueno'|'danado']
// Un nuevo equipo danado pasa a estado danado y solo el admin puede reactivarlo
// Devuelve null si se pudo, o loan_not_found', 'invalid_transition', 'invalid_condition'
function devolverPrestamo(PDO $pdo, int $id, array $condiciones, int $actorId): ?string {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT estado FROM prestamos WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $estadoActual = $stmt->fetchColumn();
        if ($estadoActual === false) {
            $pdo->rollBack();
            return 'loan_not_found';
        }
        if (!in_array('devuelto', TRANSICIONES_PRESTAMO[$estadoActual] ?? [], true)) {
            $pdo->rollBack();
            return 'invalid_transition';
        }

        // Equipos del préstamo, bloqueados en orden
        $stmt = $pdo->prepare('SELECT e.id, e.estado FROM prestamo_detalle d
                               JOIN equipos e ON e.id = d.equipo_id
                               WHERE d.prestamo_id = :id ORDER BY e.id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $equipos = $stmt->fetchAll();

        // Debe venir la condición de TODOS los equipos del préstamo, ni más ni menos, y con valores válidos
        if (count($condiciones) !== count($equipos)) {
            $pdo->rollBack();
            return 'invalid_condition';
        }
        foreach ($equipos as $equipo) {
            $condicion = $condiciones[$equipo['id']] ?? null;
            if ($condicion !== 'bueno' && $condicion !== 'danado') {
                $pdo->rollBack();
                return 'invalid_condition';
            }
        }

        $marcarDevolucion = $pdo->prepare('UPDATE prestamo_detalle SET fecha_devolucion = NOW(), condicion_devolucion = :condicion
                                           WHERE prestamo_id = :prestamo AND equipo_id = :equipo');
        $marcarDanado = $pdo->prepare("UPDATE equipos SET estado = 'danado' WHERE id = :id");
        foreach ($equipos as $equipo) {
            $condicion = $condiciones[$equipo['id']];
            $marcarDevolucion->execute([':condicion' => $condicion, ':prestamo' => $id, ':equipo' => $equipo['id']]);

            if ($condicion === 'danado') {
                $marcarDanado->execute([':id' => $equipo['id']]);
                registrarBitacora($pdo, $actorId, 'editar', 'equipo', (int) $equipo['id'],
                    ['estado' => $equipo['estado']], ['estado' => 'danado', 'prestamo_id' => $id]);
            }
        }

        $stmt = $pdo->prepare("UPDATE prestamos SET estado = 'devuelto' WHERE id = :id");
        $stmt->execute([':id' => $id]);

        registrarBitacora($pdo, $actorId, 'editar', 'prestamo', $id,
            ['estado' => $estadoActual], ['estado' => 'devuelto', 'condiciones' => $condiciones]);

        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}