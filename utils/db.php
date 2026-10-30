<?php
// Hacemos la conexión a la base de datos usando PDO
// Uso $pdo = obtener_conexion() para obtener la conexión
function obtener_conexion(): PDO {
    // será static la conexion se crea una sola vez y se reutiliza en la misma petición
    static $pdo = null;

    if ($pdo === null) {
        // Credenciales desde config/config.php
        $config = require __DIR__ . '/../config/config.php';
        $db = $config['db'];

        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";

        // Configuramos PDO para que lance excepciones en caso de error
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Los errores lanzan excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //Devuelve filas como arreglos asociativos
            PDO::ATTR_EMULATE_PREPARES => false, // Sentencias preparadas reales
        ]);

    }

    return $pdo;
}