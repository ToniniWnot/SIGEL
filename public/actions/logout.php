<?php
// Cierra la sesion del usuario
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';

// Solo aceptamos peticiones POST, si alguien abre esta URL 
// directamente, vuelve al index
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

// Si el token CSRF no coincide, verifyCsrf() redirige al
// con el error y detiene el script
verifyCsrf();

// Destruye la sesión y redirige al con un mensaje de éxito
destroySession();
header('Location: /index.php?success=logged_out');
exit;