<?php
// Manejo de errores: nunca se muestran al usuario, lo veremos en logs/app.log

// No mostrar errores en pantalla ya que podemos revelar rutas,
// consultas SQL, etc.
ini_set('display_errors', 0);
// Guardamos los errores en un archivo de log
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/app.log');
error_reporting(E_ALL); // Reportar todos los errores

// Excepciones sin capturar por ejemplo un fallo de PDO cuando se registran
// Tener detalle y al usuario mostrarle un mensaje genérico
set_exception_handler(function (Throwable $e) {
    error_log(get_class($e) . ": " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo 'Ocurrió un error inesperado. Por favor, inténtelo más tarde.';
});