<?php
// Procesa el formulario de edición de un equipo
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/equipos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/equipos.php');
    exit;
}

requireRole(['administrador']);
verifyCsrf();

// El id viene en un campo oculto que cualquiera puede cambiar, se valida como entero positivo
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: /pages/equipos.php?error=equipment_not_found');
    exit;
}

$datos = datosEquipoDesdePost($_POST);

try {
    $pdo = obtener_conexion();

    $error = validarEquipo($pdo, $datos, $id);
    if ($error === null) {
        $error = actualizarEquipo($pdo, $id, $datos, (int) $_SESSION['user_id']);
    }
    if ($error === 'equipment_not_found') {
        header('Location: /pages/equipos.php?error=equipment_not_found');
        exit;
    }
    if ($error !== null) {
        $_SESSION['equipo_form'] = ['id' => $id] + $datos;
        header('Location: /pages/equipo_form.php?id=' . $id . '&error=' . $error);
        exit;
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        header('Location: /pages/equipo_form.php?id=' . $id . '&error=equipment_exists');
        exit;
    }
    error_log($e->getMessage());
    header('Location: /pages/equipos.php?error=server_error');
    exit;
}

header('Location: /pages/equipos.php?success=equipment_updated');
exit;