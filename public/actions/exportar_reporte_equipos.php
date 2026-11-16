<?php
// Descarga el reporte de equipos más prestados en CSV, solo el administrador
// Es una consulta, no cambia datos y por eso hago que vaya por GET
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/reportes.php';

requireRole(['administrador']);

[$desde, $hasta, $error] = rangoDesdeGet($_GET);
if ($error !== null) {
    header('Location: /pages/reporte_equipos.php?error=' . $error);
    exit;
}

try {
    $filas = equiposMasPrestados(obtener_conexion(), $desde, $hasta);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/reporte_equipos.php?error=server_error');
    exit;
}

// El nombre del archivo solo lleva las fechas ya validadas
$archivo = 'equipos_mas_prestados_' . $desde->format('Ymd') . '_' . $hasta->format('Ymd') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('X-Content-Type-Options: nosniff');

$salida = fopen('php://output', 'w');
// BOM para que Excel reconozca los acentos en UTF-8
fwrite($salida, "\xEF\xBB\xBF");
fputcsv($salida, ['Posicion', 'Codigo', 'Nombre', 'Categoria', 'Veces prestado'], ',', '"', '');
foreach ($filas as $i => $f) {
    // Los textos pasan por neutralizarCsv, los números no necesitan
    fputcsv($salida, [
        $i + 1,
        neutralizarCsv($f['codigo']),
        neutralizarCsv($f['nombre']),
        neutralizarCsv($f['categoria']),
        (int) $f['prestamos'],
    ], ',', '"', '');
}
fclose($salida);
exit;