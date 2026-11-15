<?php
// Elimina un equipo de forma lógica
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/equipos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/equipos.php');
    exit;
}

requireRole(['administrador']);
verifyCsrf();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: /pages/equipos.php?error=equipment_not_found');
    exit;
}

try {
    // Devuelve null si se eliminó, o el código del motivo por el que no se pudo
    $error = eliminarEquipo(obtener_conexion(), $id, (int) $_SESSION['user_id']);
    if ($error !== null) {
        header('Location: /pages/equipos.php?error=' . $error);
        exit;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/equipos.php?error=server_error');
    exit;
}

header('Location: /pages/equipos.php?success=equipment_deleted');
exit;