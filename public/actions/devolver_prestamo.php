<?php
// Registra la devolución de un préstamo con la condición de cada equipo
// Parte de mi regla individual #9
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/prestamos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/prestamos.php');
    exit;
}

requireRole(['operador']);
verifyCsrf();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: /pages/prestamos.php?error=loan_not_found');
    exit;
}

// Llega condicion[id_equipo] = bueno o danado todo lo demás se descarta o se rechaza después
$condiciones = [];
$recibidas = isset($_POST['condicion']) && is_array($_POST['condicion']) ? $_POST['condicion'] : [];
foreach ($recibidas as $equipoId => $condicion) {
    $equipoId = filter_var($equipoId, FILTER_VALIDATE_INT);
    if ($equipoId !== false && $equipoId >= 1 && is_string($condicion)) {
        $condiciones[$equipoId] = $condicion;
    }
}

try {
    // devolverPrestamo exige exactamente una condición válida por cada equipo del préstamo
    $error = devolverPrestamo(obtener_conexion(), $id, $condiciones, (int) $_SESSION['user_id']);
    if ($error !== null) {
        // Si faltó o sobró una condición, se vuelve a la pantalla de devolución, si no, al listado
        $destino = $error === 'invalid_condition' ? '/pages/prestamo_devolver.php?id=' . $id . '&' : '/pages/prestamos.php?';
        header('Location: ' . $destino . 'error=' . $error);
        exit;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/prestamos.php?error=server_error');
    exit;
}

header('Location: /pages/prestamos.php?success=loan_returned');
exit;