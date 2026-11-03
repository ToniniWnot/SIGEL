<?php
// Procesa el formulario de login
require_once __DIR__ . '/../../utils/db.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';

// Solo acepta peticiones POST, si alguien 
// intenta acceder a este script directamente, lo redirige al index
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

// Verifica el token CSRF, si el token no coincide, entonces
// verifyCsrf() redirige al index con el error csrf_invalid 
// y detiene el script
verifyCsrf();

// Lee los datos que envió el formulario (null si no vienen)
$correo = $_POST['correo'] ?? null;
$password = $_POST['password'] ?? null;

// Antes validamos que sean texto que vacio
// y que el correo no sea demasiado largo
if (
    !is_string($correo) || !is_string($password) 
    || trim($correo) === '' || trim($password) === '' 
    || strlen($correo) > 150
    || $password === ''
) {
    header('Location: /index.php?error=invalid_input');
    exit;
}

$correo = trim($correo);

try {
    $pdo = obtener_conexion();

    // La consulta preparada, el correo viaja separado del SQL
    // así evitamos inyecciones SQL
    $stmt = $pdo->prepare(
        'SELECT u.id, u.nombre, u.password_hash, u.activo, r.nombre AS rol, u.intentos_fallidos, 
        (u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW()) AS bloqueado
        FROM usuarios u
        INNER JOIN roles r ON r.id = u.rol_id
        WHERE u.correo = :correo AND u.eliminado_en IS NULL
        LIMIT 1'
    );
    $stmt->execute(['correo' => $correo]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    // Si la base llega a fallar, el detalle va al log y el usuario solo ve un 
    // error genérico
    error_log('Error de la base de datos en login:' . $e->getMessage());
    header('Location: /index.php?error=server_error');
    exit;
}

// Rechaza al que no puede iniciar sesión,
// antes de revisar la contraseña
// no existe, está incactivo, es profesor o no tiene contraseña
// Siempre va a mostrar el mismo mensaje de error para no revelar
// cual fue el motivo
if (
    $usuario === false
    || (int) $usuario['activo'] !== 1
    || $usuario['rol'] === 'profesor'
    || $usuario['password_hash'] === null
) {
    header('Location: /index.php?error=invalid_credentials');
    exit;
}

// Si la cuenta está bloqueada por intentos, se rechaza 
// sin comprobar la contraseña
if ((int) $usuario['bloqueado'] === 1) {
    header('Location: /index.php?error=account_locked');
    exit;
}

// Comparamos la contraseña que envió el usuario con la que está en la base
if (!password_verify($password, $usuario['password_hash'])) {
    // Suma un intento fallido, al llegar a 3 se bloquea la cuenta por 2 minutos
    $intentos = (int) $usuario['intentos_fallidos'] + 1;

    try {
        if ($intentos >= 3) {
            $stmt = $pdo->prepare(
                'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 2 MINUTE) WHERE id = :id'
            );
            $stmt->execute(['id' => $usuario['id']]);
        } else {
            $stmt = $pdo->prepare('UPDATE usuarios SET intentos_fallidos = :intentos WHERE id = :id');
            $stmt->execute(['intentos' => $intentos, 'id' => $usuario['id']]);
        }
    } catch (PDOException $e) {
        error_log('Error al registrar intento fallido' . $e->getMessage());
    }
    header('Location: /index.php?error=invalid_credentials');
    exit;
}

// Credenciales correctas, reinicia el contador de intentos fallidos al iniciar sesión correctamente
try {
    $stmt = $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id');
    $stmt->execute(['id' => $usuario['id']]); 
    } catch (PDOException $e) {
        error_log('Error al reiniciar los intentos fallidos' . $e->getMessage());
}

// Al tener la credenciales correctas, se abre la sesión
// con un id nuevo
startSession();
session_regenerate_id(true);

// Si el usuario marcó "Recordarme" entonces creamos el token de larga duración
if (isset($_POST['remember'])) {
    try {
        createRememberMeToken($pdo, (int) $usuario['id']);
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

$_SESSION['user_id'] = (int) $usuario['id'];
$_SESSION['user_name'] = $usuario['nombre'];
$_SESSION['user_role'] = $usuario['rol'];
$_SESSION['last_activity'] = time();

header('Location: /pages/dashboard.php');
exit;