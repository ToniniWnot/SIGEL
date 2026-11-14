<?php
// Elimina una categoria de forma logica
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/categorias.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/categorias.php');
    exit;
}

requireRole(['administrador']);
verifyCsrf();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: /pages/categorias.php?error=category_not_found');
    exit;
}

try {
    // Devuelve null si se eliminó o el codigo del motivo por el que no se pudo
    $error = eliminarCategoria(obtener_conexion(), $id, (int) $_SESSION['user_id']);
    if ($error !== null) {
        header('Location: /pages/categorias.php?error=' . $error);
        exit;    
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/categorias.php?error=server_error');
    exit;
}

header('Location: /pages/categorias.php?success=category_deleted');
exit;