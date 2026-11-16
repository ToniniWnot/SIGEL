<?php
// Elimina una imagen de un equipo, solo el administrador
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/imagenes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/equipos.php');
    exit;
}

requireRole(['administrador']);
verifyCsrf();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: /pages/equipos.php?error=image_not_found');
    exit;
}

try {
    $pdo = obtener_conexion();
    // Se busca antes de eliminar para saber a qué equipo regresar
    $imagen = buscarImagen($pdo, $id);
    $error = eliminarImagen($pdo, $id, (int) $_SESSION['user_id']);
    if ($error !== null || $imagen === null) {
        header('Location: /pages/equipos.php?error=image_not_found');
        exit;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/equipos.php?error=server_error');
    exit;
}

header('Location: /pages/equipo.php?id=' . (int) $imagen['equipo_id'] . '&success=image_deleted');
exit;