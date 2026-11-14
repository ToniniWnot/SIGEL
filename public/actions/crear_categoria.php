<?php
// Procesa el formulario de nueva categoria
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/categorias.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/categorias.php');
    exit;
}

requireRole(['administrador']);
verifyCsrf();

$datos = datosCategoriaDesdePost($_POST);

try {
    $pdo = obtener_conexion();
    
    // Si algo no es valido, se guarda lo escrito y se vuelve al formulario con el error
    $error = validarCategoria($pdo, $datos);
    if ($error !== null) {
        $_SESSION['categoria_form'] = ['id' => null] + $datos;
        header('Location: /pages/categoria_form.php?error=' . $error);
        exit;
    }
    crearCategoria($pdo, $datos, (int) $_SESSION['user_id']);
} catch (PDOException $e) {
    // 23000 codigo violacion UNIQUE, otro admin creó ese nombre ahora
    if ($e->getCode() === '23000') {
        $_SESSION['categoria_form'] = ['id' => null] + $datos;
        header('Location: /pages/categoria_form.php?error=category_exists');
        exit;
    }
    error_log($e->getMessage());
    header('Location: /pages/categoria_form.php?error=server_error');
    exit;
}

header('Location: /pages/categorias.php?success=category_created');
exit;