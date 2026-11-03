<?php

require_once __DIR__ . '/db.php';

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
// Nombre de la cookie de "Recordarme" y duanto dura
const REMEMBER_ME_COOKIE = 'remember_me';
const REMEMBER_DAYS = 30; // 30 días 

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
    
    // Si no hay usuario logueado, probamos con la cookie de "Recordarme"
    if (!isset($_SESSION['user_id'])) {
        $entro = false;
        try {
            $entro = loginWithRememberCookie(obtener_conexion());
        } catch (PDOException $e) {
            error_log($e->getMessage());
        }
        // Si tampoco se pudo iniciar sesión con la cookie, redirige al login
        if (!$entro) {
            header('Location: /index.php?error=login_required');
            exit;
        }
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

// Crea el token de "Recordarme" para un usuario
// Se guarda el hash en la BD y envia la cookie al navegador
function createRememberMeToken(PDO $pdo, int $userId) {
    // Selector, identifica la fila en la BD de 32 caracteres
    $selector = bin2hex(random_bytes(16));
    // Validador, es el secreto, solo viaja en la cookie, 64 caracteres
    $validator = bin2hex(random_bytes(32));
    // En la BD solo se guarda el hash del validador, nunca el validador 
    $validatorHash = hash('sha256', $validator);

    // Guardamos selector, hash del validador y la fecha en la cual vence
    $stmt = $pdo->prepare('INSERT INTO auth_tokens (usuario_id, selector, validador_hash, expira_en) 
                           VALUES (:usuario_id, :selector, :validador_hash, DATE_ADD(NOW(), INTERVAL :dias DAY))');
    $stmt->execute([
        ':usuario_id' => $userId,
        ':selector' => $selector,
        ':validador_hash' => $validatorHash,
        ':dias' => REMEMBER_DAYS
    ]);

    // Envia la cookie con selector y validador al navegador
    setcookie(REMEMBER_ME_COOKIE, $selector . ':' . $validator, [
        'expires' => time() + (REMEMBER_DAYS * 86400), // 30 días
        'path' => '/',
        'httponly' => true, // Javascript no puede leer la cookie y así frenamos el robo por XSS
        'samesite' => 'Lax'
    ]);
}

// Intenta iniciar sesión con la cookie de "Recordarme"
// Devuelve true si se pudo iniciar sesión, false si no
function loginWithRememberCookie(PDO $pdo): bool {
    // Si no hay cookie, no hay nada que hacer
    if (!isset($_COOKIE[REMEMBER_ME_COOKIE]) || !is_string($_COOKIE[REMEMBER_ME_COOKIE])) {
        return false;
    }

    // La cookie tiene el formato selector:validador
    $partes = explode(':', $_COOKIE[REMEMBER_ME_COOKIE]);
    if (count($partes) !== 2) {
        return false;
    }
    [$selector, $validator] = $partes;

    // Buscamos la fila por selector, solo si los tokens no han vencido
        $stmt = $pdo->prepare('SELECT t.id, t.validador_hash, u.id AS usuario_id, u.nombre, r.nombre AS rol
                           FROM auth_tokens t
                           INNER JOIN usuarios u ON u.id = t.usuario_id
                           INNER JOIN roles r ON r.id = u.rol_id
                           WHERE t.selector = :selector AND t.expira_en > NOW()
                           AND u.activo = 1 AND u.eliminado_en IS NULL AND r.nombre <> \'profesor\'
                           LIMIT 1');
    $stmt->execute([':selector' => $selector]);
    $token = $stmt->fetch();

    // Si no existe el token o el validador no coincide, falla
    if ($token === false || !hash_equals($token['validador_hash'], hash('sha256', $validator))) {
        return false;
    }

    // Y si todo coincide, inicia la sesión del usuario
    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $token['usuario_id'];
    $_SESSION['user_name'] = $token['nombre'];
    $_SESSION['user_role'] = $token['rol'];
    $_SESSION['last_activity'] = time();
    return true;
}