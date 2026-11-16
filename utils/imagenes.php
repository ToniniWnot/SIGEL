<?php
// Imagenes de los equipos, validacion del archivi, guardado fuera de public y borrado
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bitacora.php';

// Carpeta donde se guardan, está fuera de public para que 
// nadie pueda abrirlas con una URL directa
const IMAGENES_DIR = __DIR__ . '/../storage/equipos';
// Peso maxumo de cada imagen, 2MB
const IMAGEN_MAX_BYTES = 2 * 1024 * 1024;
// Maximo de imagenes por equipo
const IMAGENES_MAX_POR_EQUIPO = 5;
// Tipos mime permitidos y la extension con la que se guardan
const IMAGEN_TIPOS = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

// Extensiones que se aceptan en el nombre origial del archivo
const IMAGEN_EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp'];

// Imágenes de un equipo, la más antigua primero
function listarImagenes(PDO $pdo, int $equipoId): array {
    $stmt = $pdo->prepare('SELECT id, ruta FROM equipo_imagenes WHERE equipo_id = :id ORDER BY id');
    $stmt->execute([':id' => $equipoId]);
    return $stmt->fetchAll();
}

// Busca una imagen por id, solo si su equipo no está eliminado, devuelve null si no hay
function buscarImagen(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT i.id, i.equipo_id, i.ruta
                           FROM equipo_imagenes i JOIN equipos e ON e.id = i.equipo_id
                           WHERE i.id = :id AND e.eliminado_en IS NULL LIMIT 1');
    $stmt->execute([':id' => $id]);
    $fila = $stmt->fetch();
    return $fila === false ? null : $fila;
}

// Ruta completa del archivo en disco, basename evita que un valor raro salga de la carpeta
function rutaImagen(string $nombre): string {
    return IMAGENES_DIR . '/' . basename($nombre);
}

// Convierte $_FILES['imagenes'] en una lista simple, ignora 
// los campos que se dejaron vacíos
// Acepta un solo archivo o varios, cualquier otra forma se 
// marca con error para que se rechace
function archivosSubidos(string $campo): array {
    $f = $_FILES[$campo] ?? null;
    if (!is_array($f) || !isset($f['name'], $f['tmp_name'], $f['error'])) {
        return [];
    }
    // Si llegó un solo archivo los datos no vienen en arreglos, se envuelven
    if (!is_array($f['name'])) {
        $f = ['name' => [$f['name']], 'tmp_name' => [$f['tmp_name']], 'error' => [$f['error']]];
    }

    $archivos = [];
    foreach ($f['name'] as $i => $nombre) {
        $error = $f['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $tmp = $f['tmp_name'][$i] ?? '';
        // Si algo no es texto o número (alguien manipuló el formulario) se deja con error
        if (!is_string($nombre) || !is_string($tmp) || !is_int($error)) {
            $archivos[] = ['nombre' => '', 'tmp' => '', 'error' => UPLOAD_ERR_PARTIAL];
            continue;
        }
        $archivos[] = ['nombre' => $nombre, 'tmp' => $tmp, 'error' => $error];
    }
    return $archivos;
}

// Tipo mime real del archivo, se lee su contenido, no lo que diga el navegador ni el nombre
function mimeReal(string $ruta): string {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    return (string) $finfo->file($ruta);
}

// Valida un archivo subido, devuelve null si sirve o el código del error
function validarImagen(array $archivo): ?string {
    // Errores posibles de PHP al recibir el archivo, el tamaño de php.ini se trata como demasiado grande
    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
        return 'image_too_large';
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return 'upload_failed';
    }

    // La extensión del nombre original debe estar en la lista blanca
    $extension = strtolower(pathinfo($archivo['nombre'], PATHINFO_EXTENSION));
    if (!in_array($extension, IMAGEN_EXTENSIONES, true)) {
        return 'invalid_image';
    }

    // Debe ser un archivo que realmente llegó por una subida HTTP
    if (!is_uploaded_file($archivo['tmp'])) {
        return 'upload_failed';
    }

    $peso = filesize($archivo['tmp']);
    if ($peso === false || $peso === 0) {
        return 'invalid_image';
    }
    if ($peso > IMAGEN_MAX_BYTES) {
        return 'image_too_large';
    }

    // El contenido debe ser un tipo permitido y coincidir con la extensión
    $mime = mimeReal($archivo['tmp']);
    if (!isset(IMAGEN_TIPOS[$mime])) {
        return 'invalid_image';
    }
    if (($extension === 'jpeg' ? 'jpg' : $extension) !== IMAGEN_TIPOS[$mime]) {
        return 'invalid_image';
    }

    // Parte final de los filtros, que se pueda leer como imagen de verdad
    if (@getimagesize($archivo['tmp']) === false) {
        return 'invalid_image';
    }
    return null;
}

// Borra archivos del disco, se usa para deshacer cuando algo falla
function borrarArchivos(array $rutas): void {
    foreach ($rutas as $ruta) {
        if (is_file($ruta)) {
            unlink($ruta);
        }
    }
}

// Guarda varias imágenes de un equipo, o se guardan todas o no se guarda ninguna
// Devuelve null si se pudo, o 'no_image_selected', 'equipment_not_found', 'image_limit',
// 'upload_failed' y los errores de validarImagen
function guardarImagenes(PDO $pdo, int $equipoId, array $archivos, int $actorId): ?string {
    if (count($archivos) === 0) {
        return 'no_image_selected';
    }
    foreach ($archivos as $archivo) {
        $error = validarImagen($archivo);
        if ($error !== null) {
            return $error;
        }
    }
    if (!is_dir(IMAGENES_DIR) || !is_writable(IMAGENES_DIR)) {
        error_log('La carpeta de imágenes no existe o no tiene permiso de escritura: ' . IMAGENES_DIR);
        return 'upload_failed';
    }

    $guardados = [];
    $pdo->beginTransaction();
    try {
        // Se bloquea el equipo para que dos subidas a la vez no pasen del máximo
        $stmt = $pdo->prepare('SELECT id FROM equipos WHERE id = :id AND eliminado_en IS NULL FOR UPDATE');
        $stmt->execute([':id' => $equipoId]);
        if ($stmt->fetchColumn() === false) {
            $pdo->rollBack();
            return 'equipment_not_found';
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM equipo_imagenes WHERE equipo_id = :id');
        $stmt->execute([':id' => $equipoId]);
        if ((int) $stmt->fetchColumn() + count($archivos) > IMAGENES_MAX_POR_EQUIPO) {
            $pdo->rollBack();
            return 'image_limit';
        }

        $insert = $pdo->prepare('INSERT INTO equipo_imagenes (equipo_id, ruta) VALUES (:equipo, :ruta)');
        foreach ($archivos as $archivo) {
            // El nombre lo genera el servidor, la extensión sale del tipo real, nunca del nombre original
            $nombre = bin2hex(random_bytes(16)) . '.' . IMAGEN_TIPOS[mimeReal($archivo['tmp'])];
            $destino = rutaImagen($nombre);
            if (!move_uploaded_file($archivo['tmp'], $destino)) {
                $pdo->rollBack();
                borrarArchivos($guardados);
                return 'upload_failed';
            }
            $guardados[] = $destino;

            $insert->execute([':equipo' => $equipoId, ':ruta' => $nombre]);
            registrarBitacora($pdo, $actorId, 'crear', 'equipo_imagen', (int) $pdo->lastInsertId(), null,
                ['equipo_id' => $equipoId, 'ruta' => $nombre]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        borrarArchivos($guardados);
        throw $e;
    }
    return null;
}

// Elimina una imagen, primero la fila y después el archivo, devuelve null o 'image_not_found'
function eliminarImagen(PDO $pdo, int $id, int $actorId): ?string {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT i.id, i.equipo_id, i.ruta
                               FROM equipo_imagenes i JOIN equipos e ON e.id = i.equipo_id
                               WHERE i.id = :id AND e.eliminado_en IS NULL FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $imagen = $stmt->fetch();
        if ($imagen === false) {
            $pdo->rollBack();
            return 'image_not_found';
        }

        $pdo->prepare('DELETE FROM equipo_imagenes WHERE id = :id')->execute([':id' => $id]);
        registrarBitacora($pdo, $actorId, 'eliminar', 'equipo_imagen', $id,
            ['equipo_id' => (int) $imagen['equipo_id'], 'ruta' => $imagen['ruta']], null);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    // El archivo se borra al final, si la transacción falló no se pierde nada
    borrarArchivos([rutaImagen($imagen['ruta'])]);
    return null;
}