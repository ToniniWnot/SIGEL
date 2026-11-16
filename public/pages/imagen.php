<?php
// Entrega una imagen de equipo, los archivos están fuera de 
// public, solo este script los sirve
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/imagenes.php';

requireRole(['operador', 'administrador']);

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$imagen = ($id === false || $id < 1) ? null : buscarImagen(obtener_conexion(), $id);
$ruta = $imagen === null ? '' : rutaImagen($imagen['ruta']);

if ($imagen === null || !is_file($ruta)) {
    http_response_code(404);
    echo 'Imagen no encontrada';
    exit;
}

// El tipo se saca del contenido real, no del nombre, y nosniff evita que el navegador lo reinterprete
$tipo = mimeReal($ruta);
if (!isset(IMAGEN_TIPOS[$tipo])) {
    http_response_code(404);
    echo 'Imagen no encontrada';
    exit;
}
header('Content-Type: ' . $tipo);
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($ruta);
exit;