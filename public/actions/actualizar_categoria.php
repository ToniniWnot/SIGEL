<?php
// Procesa el formulario de edicion de un categoria
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/categorias.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/categorias.php');
    exit;
}

requireRole(['administrador']);
verifyCsrf();           

// El id viene en un campo oculto que cualquiera puede cambiar, se valida como entero positivo
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: /pages/categorias.php?error=category_not_found');
    exit;
}

$datos = datosCategoriaDesdePost($_POST);

try {
    $pdo = obtener_conexion();
    
    $error = validarCategoria($pdo, $datos, $id);
    if ($error !== null) {
        $_SESSION['categoria_form'] = ['id' => $id] + $datos;
        header('Location: /pages/categoria_form.php?id=' . $id . '&error=' . $error);
        exit;
    }

    // Devuelve false si la categoria no existe o ya fue eliminada
    if (!actualizarCategoria($pdo, $id, $datos, (int) $_SESSION['user_id'])) {
        header('Location: /pages/categorias.php?error=category_not_found');
        exit;
    }
} catch (PDOException $e) {
    // 23000 codigo violacion UNIQUE
    if ($e->getCode() === '23000') {
        header('Location: /pages/categoria_form.php?id=' . $id . '&error=category_exists');
        exit;
    }
    error_log($e->getMessage());
    header('Location: /pages/categorias.php?error=server_error');
    exit;
}

header('Location: /pages/categorias.php?success=category_updated');
exit;