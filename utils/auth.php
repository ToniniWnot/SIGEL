<?php

// Inicia la sesión una sola vez por petición 
// con la cookie de sesión protegida

function startSession() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Solo acepta identificadores de sesión creada por el servidor
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'path' => '/',
            'httponly' => true, // Javascript no puede leer la cookie y así frenamos el robo por XSS
            'samesite' => 'Lax' // el navegador no la envía en peticiones POST desde otros sitioss
        ]);
        session_start();
    }
}

// Segundos que hay de inactividad durante los cuales la sesion deja de ser válida
const SESSION_TIMEOUT = 1800; // 30 minutos

// Cierra la sesión por completo
// Vacía los datos, borra la cookie y destruye en el servidor
function destroySession() {
    // Borra los datos de la sesión
    startSession();
    $_SESSION = [];
    // Borra la cookie de sesión del navegador, la envia ya vencida para que el navegador la borre
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600, 
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ]);
    session_destroy();
}

// Seguridad de acceso, lo llamamos al inicio de cada página protegida
function requireLogin() {
    startSession();
    
    // Si no hay usuario logueado, redirige a login (index.php) con un mensaje de error
    if (!isset($_SESSION['user_id'])) {
        header('Location: /index.php?error=login_required');
        exit;
    }

    // Si pasa mucho tiempo sin actividad se cierra la sesión
    if (time() - ($_SESSION['last_activity'] ?? time()) > SESSION_TIMEOUT) {
        destroySession();
        header('Location: /index.php?error=session_expired');
        exit;
    }

    // Cada vista renueva el tiempo de actividad
    $_SESSION['last_activity'] = time();
}

// Solo deja pasar a los roles indicados previamente
// requireRole(['administrador'])
function requireRole(array $roles) {
    // Primero debe de tener una sesion válida
    requireLogin();

    // Si el rol del usuario no está en la lista, se le niega completamente el acceso
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        header('Location: /pages/dashboard.php?error=forbidden');
        exit;
    }
}