<?php
// Procesa el formulario de nuevo equipo
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/equipos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/equipos.php');
    exit;
}

requireRole(['administrador']);
verifyCsrf();

$datos = datosEquipoDesdePost($_POST);

try {
    $pdo = obtener_conexion();
    // Si algo no es valido, se guarda lo escrito y se vuelve al formulario con el error
    $error = validarEquipo($pdo, $datos);
    if ($error === null) {
        $error = crearEquipo($pdo, $datos, (int) $_SESSION['user_id']);
    }
    if ($error !== null) {
        $_SESSION['equipo_form'] = ['id' => null] + $datos;
        header('Location: /pages/equipo_form.php?error=' . $error);
        exit;
    }
} catch (PDOException $e) {
    // 23000 es una violación de UNIQUE
    if ($e->getCode() === '23000') {
        header('Location: /pages/equipo_form.php?error=equipment_exists');
        exit;
    }
    error_log($e->getMessage());
    header('Location: /pages/equipos.php?error=server_error');
    exit;
}

// Si todo salió bien, se redirige al listado con un mensaje de éxito, patron prg
header('Location: /pages/equipos.php?success=equipment_created');
exit;