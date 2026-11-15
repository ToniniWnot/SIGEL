<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bitacora.php';
require_once __DIR__ . '/helpers.php';

// Estados que puede tener un equipo, la llave es lo que se guarda en la BD
const ESTADOS_EQUIPO = [
    'disponible' => 'Disponible',
    'mantenimiento' => 'En mantenimiento',
    'danado' => 'Dañado',
];

// Cuenta los equipos no eliminados que coinciden con la búsqueda como código, nombre o categoría
function contarEquipos(PDO $pdo, string $busqueda): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM equipos e
                           JOIN categorias c ON c.id = e.categoria_id
                           WHERE e.eliminado_en IS NULL
                             AND (e.codigo LIKE :q1 OR e.nombre LIKE :q2 OR c.nombre LIKE :q3)');
    $patron = patronBusqueda($busqueda);
    $stmt->execute([':q1' => $patron, ':q2' => $patron, ':q3' => $patron]);
    return (int) $stmt->fetchColumn();
}

// Trae una página de equipos con el nombre de su categoría
function listarEquipos(PDO $pdo, string $busqueda, int $limite, int $offset): array {
    $stmt = $pdo->prepare('SELECT e.id, e.codigo, e.nombre, e.estado, e.categoria_id, c.nombre AS categoria
                           FROM equipos e
                           JOIN categorias c ON c.id = e.categoria_id
                           WHERE e.eliminado_en IS NULL
                             AND (e.codigo LIKE :q1 OR e.nombre LIKE :q2 OR c.nombre LIKE :q3)
                           ORDER BY e.codigo ASC, e.id ASC
                           LIMIT :limite OFFSET :offset');
    $patron = patronBusqueda($busqueda);
    $stmt->bindValue(':q1', $patron);
    $stmt->bindValue(':q2', $patron);
    $stmt->bindValue(':q3', $patron);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Busca un equipo por id, devuelve null si no existe o está eliminado
function buscarEquipo(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT id, categoria_id, codigo, nombre, estado FROM equipos WHERE id = :id AND eliminado_en IS NULL LIMIT 1');
    $stmt->execute([':id' => $id]);
    $fila = $stmt->fetch();
    return $fila === false ? null : $fila;
}

// Deja lo que llegó del formulario limpio y con el tipo correcto
function datosEquipoDesdePost(array $post): array {
    return [
        // El código siempre en mayúsculas, para que por ejemplo CAM-001 y cam-001 sean el mismo
        'codigo' => mb_strtoupper(trim(textoPost($post, 'codigo'))),
        'nombre' => trim(textoPost($post, 'nombre')),
        // Solo enteros, cualquier otra cosa queda en 0 y la validación la rechaza
        'categoria_id' => filter_var(textoPost($post, 'categoria_id'), FILTER_VALIDATE_INT) ?: 0,
        'estado' => textoPost($post, 'estado'),
    ];
}

// Valida un equipo, devuelve un código de error o null si todo está bien
// $idActual es el id del equipo que se edita o null si es nuevo
function validarEquipo(PDO $pdo, array $datos, ?int $idActual = null): ?string {
    // Solo letras, números, punto, guion y guion bajo, de 2 a 30 caracteres
    if (!preg_match('/^[A-Z0-9._-]{2,30}$/', $datos['codigo'])) {
        return 'invalid_equipment_code';
    }

    $largo = mb_strlen($datos['nombre']);
    if ($largo < 2 || $largo > 100) {
        return 'invalid_equipment_name';
    }

    // El estado tiene que ser una de las llaves permitidas
    if (!array_key_exists($datos['estado'], ESTADOS_EQUIPO)) {
        return 'invalid_state';
    }

    // La categoría tiene que existir y no estar eliminada
    if (!categoriaActiva($pdo, $datos['categoria_id'])) {
        return 'invalid_category';
    }

    // Código repetido, también cuenta el de un equipo eliminado porque la columna es UNIQUE
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM equipos WHERE codigo = :codigo AND id <> :id');
    $stmt->execute([':codigo' => $datos['codigo'], ':id' => $idActual ?? 0]);
    if ((int) $stmt->fetchColumn() > 0) {
        return 'equipment_exists';
    }

    return null;
}

// Revisa que la categoría exista y no esté eliminada
function categoriaActiva(PDO $pdo, int $categoriaId): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE id = :id AND eliminado_en IS NULL');
    $stmt->execute([':id' => $categoriaId]);
    return (int) $stmt->fetchColumn() > 0;
}

// Crea un equipo con su registro en la bitácora
// Devuelve null si se creó, o 'invalid_category' si la categoría se eliminó justo ahora
function crearEquipo(PDO $pdo, array $datos, int $actorId): ?string {
    $pdo->beginTransaction();
    try {
        // FOR UPDATE bloquea la categoría, así no se puede eliminar mientras se le agrega el equipo
        $stmt = $pdo->prepare('SELECT id FROM categorias WHERE id = :id AND eliminado_en IS NULL FOR UPDATE');
        $stmt->execute([':id' => $datos['categoria_id']]);
        if ($stmt->fetch() === false) {
            $pdo->rollBack();
            return 'invalid_category';
        }

        $stmt = $pdo->prepare('INSERT INTO equipos (categoria_id, codigo, nombre, estado) VALUES (:categoria, :codigo, :nombre, :estado)');
        $stmt->execute([
            ':categoria' => $datos['categoria_id'],
            ':codigo' => $datos['codigo'],
            ':nombre' => $datos['nombre'],
            ':estado' => $datos['estado'],
        ]);
        $id = (int) $pdo->lastInsertId();

        registrarBitacora($pdo, $actorId, 'crear', 'equipo', $id, null, $datos);

        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Actualiza un equipo
// Devuelve null si se guardó, o 'equipment_not_found' / 'invalid_category' si no se pudo
function actualizarEquipo(PDO $pdo, int $id, array $datos, int $actorId): ?string {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT categoria_id, codigo, nombre, estado FROM equipos WHERE id = :id AND eliminado_en IS NULL FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $antes = $stmt->fetch();
        if ($antes === false) {
            $pdo->rollBack();
            return 'equipment_not_found';
        }

        $stmt = $pdo->prepare('SELECT id FROM categorias WHERE id = :id AND eliminado_en IS NULL FOR UPDATE');
        $stmt->execute([':id' => $datos['categoria_id']]);
        if ($stmt->fetch() === false) {
            $pdo->rollBack();
            return 'invalid_category';
        }

        $stmt = $pdo->prepare('UPDATE equipos SET categoria_id = :categoria, codigo = :codigo, nombre = :nombre, estado = :estado WHERE id = :id');
        $stmt->execute([
            ':categoria' => $datos['categoria_id'],
            ':codigo' => $datos['codigo'],
            ':nombre' => $datos['nombre'],
            ':estado' => $datos['estado'],
            ':id' => $id,
        ]);

        registrarBitacora($pdo, $actorId, 'editar', 'equipo', $id,
            ['categoria_id' => (int) $antes['categoria_id'], 'codigo' => $antes['codigo'], 'nombre' => $antes['nombre'], 'estado' => $antes['estado']],
            $datos);

        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Elimina un equipo de manera lógica
// Devuelve null si se eliminó, o 'equipment_not_found' / 'equipment_in_use' si no se pudo
function eliminarEquipo(PDO $pdo, int $id, int $actorId): ?string {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT categoria_id, codigo, nombre, estado FROM equipos WHERE id = :id AND eliminado_en IS NULL FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $antes = $stmt->fetch();
        if ($antes === false) {
            $pdo->rollBack();
            return 'equipment_not_found';
        }

        // No se elimina un equipo que está en un préstamo que todavía no terminó
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM prestamo_detalle d
                               JOIN prestamos p ON p.id = d.prestamo_id
                               WHERE d.equipo_id = :id AND p.estado IN ('solicitado', 'aprobado', 'entregado')");
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            return 'equipment_in_use';
        }

        $stmt = $pdo->prepare('UPDATE equipos SET eliminado_en = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);

        registrarBitacora($pdo, $actorId, 'eliminar', 'equipo', $id,
            ['categoria_id' => (int) $antes['categoria_id'], 'codigo' => $antes['codigo'], 'nombre' => $antes['nombre'], 'estado' => $antes['estado']],
            null);

        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}