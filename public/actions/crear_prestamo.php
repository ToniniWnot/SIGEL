<?php
// Procesa el formulario de nuevo prestamo
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/prestamos.php';
require_once __DIR__ . '/../../utils/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/prestamos.php');
    exit;
}

requireRole(['operador']);
verifyCsrf();

$datos = datosPrestamoDesdePost($_POST);

try {
    $pdo = obtener_conexion();
    // Primero una validacion rapida, luego crearPrestamo vuelve a revisar dentro de la transaccion
    $error = validarPrestamo($pdo, $datos);
    if ($error === null) {
        $error = crearPrestamo($pdo, $datos, (int) $_SESSION['user_id']);
    }
    if ($error !== null) {
        // Se guarda lo escrito y se vuelve al formulario con el error
        $_SESSION['prestamo_form'] = $datos;
        header('Location: /pages/prestamo_form.php?error=' . urlencode($error));
        exit;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/prestamos.php?error=server_error');
    exit;
}

// Si todo salió bien, se redirige al listado con un mensaje de exito, patron prg
header('Location: /pages/prestamos.php?success=loan_created');
exit;