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