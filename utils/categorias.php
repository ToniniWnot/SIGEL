<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bitacora.php';
require_once __DIR__ . '/helpers.php';

// Cuenta las categorías no eliminadas que coinciden con la búsqueda
function contarCategorias(PDO $pdo, string $busqueda): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE eliminado_en IS NULL AND nombre LIKE :q');
    $stmt->execute([':q' => patronBusqueda($busqueda)]);
    return (int) $stmt->fetchColumn();
}

// Trae una página de categorías
function listarCategorias(PDO $pdo, string $busqueda, int $limite, int $offset): array {
    $stmt = $pdo->prepare('SELECT id, nombre, dias_max_prestamo FROM categorias
                           WHERE eliminado_en IS NULL AND nombre LIKE :q
                           ORDER BY nombre ASC, id ASC
                           LIMIT :limite OFFSET :offset');
    $stmt->bindValue(':q', patronBusqueda($busqueda));
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Todas las categorias activas, para llenar el select del formularios de equipos
function listarCategoriasActivas(PDO $pdo): array {
    return $pdo->query('SELECT id, nombre, dias_max_prestamo FROM categorias WHERE eliminado_en IS NULL ORDER BY nombre')->fetchAll();
}

// Busca una categoria por id, devuelve null si no existe o está eliminada
function buscarCategoria(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT id, nombre, dias_max_prestamo FROM categorias WHERE id = :id AND eliminado_en IS NULL LIMIT 1');
    $stmt->execute([':id' => $id]);
    $fila = $stmt->fetch();
    return $fila === false ? null : $fila;
}

// Deja lo que llegó del formulario limpiio y con el tipo correcto
function datosCategoriaDesdePost(array $post): array {
    return [
        'nombre' => trim(textoPost($post, 'nombre')),
        // Solo números enteros, cualquier otra cosa devuelve 0
        'dias_max_prestamo' => filter_var(textoPost($post, 'dias_max_prestamo'), FILTER_VALIDATE_INT) ?: 0,
    ];
}

// Valida una categoria, devuelve un codigo de error o null si toda está bien
// $Idactual es el id de la categoria que se edita o null si es nueva
function validarCategoria(PDO $pdo, array $datos, ?int $idActual = null): ?string {
    $largo = mb_strlen($datos['nombre']);
    if ($largo < 2 || $largo > 80) {
        return 'invalid_category_name';
    }

    if ($datos['dias_max_prestamo'] < 1 || $datos['dias_max_prestamo'] > 365) {
        return 'invalid_days';
    }

    // Nombre repetido, también cuenta el de una categoría eliminada porque la columna es UNIQUE
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE nombre = :nombre AND id <> :id');
    $stmt->execute([':nombre' => $datos['nombre'], ':id' => $idActual ?? 0]);
    if ((int) $stmt->fetchColumn() > 0) {
        return 'category_exists';
    }

    return null;
}

// Crea una categoria y devuelve su id con su registro en la bitacora
function crearCategoria(PDO $pdo, array $datos, int $actorId): int {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO categorias (nombre, dias_max_prestamo) VALUES (:nombre, :dias)');
        $stmt->execute([':nombre' => $datos['nombre'], ':dias' => $datos['dias_max_prestamo']]);
        $id = (int) $pdo->lastInsertId();

        registrarBitacora($pdo, $actorId, 'crear', 'categoria', $id, null, $datos);

        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Actualiza una categoria, devuelve false si no existe o ya estaba eliminada
function actualizarCategoria(PDO $pdo, int $id, array $datos, int $actorId): bool {
    $pdo->beginTransaction();
    try {
        $antes = buscarCategoria($pdo, $id);
        if ($antes === null) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare('UPDATE categorias SET nombre = :nombre, dias_max_prestamo = :dias WHERE id = :id AND eliminado_en IS NULL');
        $stmt->execute([':nombre' => $datos['nombre'], ':dias' => $datos['dias_max_prestamo'], ':id' => $id]);

        registrarBitacora($pdo, $actorId, 'editar', 'categoria', $id,
            ['nombre' => $antes['nombre'], 'dias_max_prestamo' => (int) $antes['dias_max_prestamo']],
            $datos);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Elimina una categoria de manera logica
// Devuelve null si se eliminó o un codigo de error como category_not_found o category_in_use
function eliminarCategoria(PDO $pdo, int $id, int $actorId): ?string {
    $pdo->beginTransaction();
    try {
        // FOR UPDATE bloquea la fila, mientras dura la transacción nadie puede asignarle un equipo nuevo
        $stmt = $pdo->prepare('SELECT id, nombre, dias_max_prestamo FROM categorias WHERE id = :id AND eliminado_en IS NULL FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $antes = $stmt->fetch();
        if ($antes === false) {
            $pdo->rollBack();
            return 'category_not_found';
        }

        // No se elimina una categoría que todavía tiene equipos
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM equipos WHERE categoria_id = :id AND eliminado_en IS NULL');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            return 'category_in_use';
        }

        $stmt = $pdo->prepare('UPDATE categorias SET eliminado_en = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);

        registrarBitacora($pdo, $actorId, 'eliminar', 'categoria', $id,
            ['nombre' => $antes['nombre'], 'dias_max_prestamo' => (int) $antes['dias_max_prestamo']],
            null);

        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}