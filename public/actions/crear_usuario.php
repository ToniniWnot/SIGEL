<?php
// Procesa el formulario de nuevo usuario
require_once __DIR__ . '/../../utils/usuarios.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/auth.php';

// Solo va a aceptar peticiones de tipo POST, sin alguien abre esta URL
// directamente vuelve al listado 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/usuarios.php');
    exit;
}

// Solo el admin puede crear usuarios, se valida en el servidor en cada peticion
requireRole(['administrador']);

// Si el token csrf no coincide, el metodo verifyCsrf() redirige con el error y detiene el script
verifyCsrf();

// Deja los datos limpios y con el tipo correcto
$datos = datosUsuarioDesdePost($_POST);

try {
    $pdo = obtener_conexion();
    // Si algo no es valido, lo guarda escrito sin la contrasena y se vuelve al formulario
    $error = validarUsuario($pdo, $datos);
    if ($error !== null) {
        $_SESSION['usuario_form'] = [
            'id' => null,
            'nombre' => $datos['nombre'],
            'correo' => $datos['correo'],
            'rol_id' => $datos['rol_id'],
            'activo' => $datos['activo'],
        ];
        header('Location: /pages/usuario_form.php?error=' . $error);
        exit;
    }
    //Creamos el usuario y registra la accion en la bitacora, todo en una transaccion
    crearUsuario($pdo, $datos, (int) $_SESSION['user_id']);
} catch (PDOException $e) {
    // 23000 es codigo de error, violacion de UNIQUE, otro administrador ya creó ese correo justo antes
    if ($e->getCode() === '23000') {
        header('Location: /pages/usuario_form.php?error=email_exists');
        exit;
    }
    error_log($e->getMessage());
    header('Location: /pages/usuarios.php?error=server_error');
    exit;
}

// Se redirige para que recargar la pagina no vuelva a crear el usuario, patrón prg
header('Location: /pages/usuarios.php?success=user_created');
exit;
