<?php

require_once __DIR__ . '/auth.php';

// Devuelve el token CSRF de la sesión, si no existe entonces lo crea

function getCsrfToken() {
    startSession();

    if (empty($_SESSION['csrf_token'])) {
        // 32 bytes aleatorios seguros que son convertidos a hexadecimal de 64 caracteres
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Imprime un campo oculto que va dentro de cada formulario POST
function csrfField() {
    $token = getCsrfToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

// Valida el token enviado, si no coincide, termina con el error csrf_invalid
function verifyCsrf() {
    startSession();

    $sent = $_POST['csrf_token'] ?? '';
    $saved = $_SESSION['csrf_token'] ?? '';

    // hash_equals compara sin filtrar información por tiempos y is_string evita arreglos
    if (!is_string($sent) || $saved === '' || !hash_equals($saved, $sent)) {
        header('Location: /index.php?error=csrf_invalid');
        exit;
    }
}