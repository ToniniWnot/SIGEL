<?php
// Aprueba, entrega o cancela un prestamo, la devolucion tiene su propia accion
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/prestamos.php';
require_once __DIR__ . '/../../utils/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/prestamos.php');
    exit;
}

requireRole(['operador']);
verifyCsrf();

// Que estado y que prestamo de exito corresponde a cada accion, cualquier otra la rechaza
const ACCIONES = [
    'aprobar' => ['aprobado', 'loan_approved'],
    'entregar' => ['entregado', 'loan_delivered'],
    'cancelar' => ['cancelado', 'loan_cancelled'],
];

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: /pages/prestamos.php?error=loan_not_found');
    exit;
}

$accion = textoPost($_POST, 'accion');
if (!isset(ACCIONES[$accion])) {
    header('Location: /pages/prestamos.php?error=invalid_transition');
    exit;
}

try {
    // Devuelve null si se pudo o el código del motivo por el que no se pudo
    $error = cambiarEstadoPrestamo(obtener_conexion(), $id, ACCIONES[$accion][0], (int) $_SESSION['user_id']);
    if ($error !== null) {
        header('Location: /pages/prestamos.php?error=' . $error);
        exit;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/prestamos.php?error=server_error');
    exit;
}

header('Location: /pages/prestamos.php?success=' . ACCIONES[$accion][1]);
exit;
