<?php
// Sube una o varias imágenes a un equipo, solo el administrador
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/imagenes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/equipos.php');
    exit;
}

requireRole(['administrador']);

// Si lo enviado pasó el límite post_max_size de PHP entonces $_POST y $_FILES llegan vacíos y el token csrf
// también, se avisa antes de verificar el token para que el mensaje sea el correcto
if (count($_POST) === 0 && count($_FILES) === 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    header('Location: /pages/equipos.php?error=image_too_large');
    exit;
}

verifyCsrf();

$equipoId = filter_var($_POST['equipo_id'] ?? null, FILTER_VALIDATE_INT);
if ($equipoId === false || $equipoId < 1) {
    header('Location: /pages/equipos.php?error=equipment_not_found');
    exit;
}

try {
    // Devuelve null si se guardaron todas o el código del motivo, en ese caso no se guarda ninguna
    $error = guardarImagenes(obtener_conexion(), $equipoId, archivosSubidos('imagenes'), (int) $_SESSION['user_id']);
    if ($error === 'equipment_not_found') {
        header('Location: /pages/equipos.php?error=equipment_not_found');
        exit;
    }
    if ($error !== null) {
        header('Location: /pages/equipo.php?id=' . $equipoId . '&error=' . $error);
        exit;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/equipos.php?error=server_error');
    exit;
}

header('Location: /pages/equipo.php?id=' . $equipoId . '&success=image_uploaded');
exit;