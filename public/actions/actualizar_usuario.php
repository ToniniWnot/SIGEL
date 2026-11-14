<?php
// Procesa el formulario de edicion de un usuario
require_once __DIR__ . '/../../utils/usuarios.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/auth.php';

// Solo acepta peticiones de tipo POST, si alguien abre directamente vuelve al listado
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/usuarios.php');
    exit;
}

// Solo el admin puede editar usuarios
requireRole(['administrador']);

// Si el token csrf no coincide, el metodo verifyCsrf() redirige con el error y detiene el script
verifyCsrf();

// El id del usuario a editar viene en un campo oculto,
// pero eso lo puede cambiar cualquier, por eso validamos como un entero positivo
// igual que si viniera de la URL
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: /pages/usuarios.php?error=user_not_found');
    exit;
}

$datos = datosUsuarioDesdePost($_POST);
$actorId = (int) $_SESSION['user_id'];

try {
    $pdo = obtener_conexion();
    // Un admin no puede desactivarse ni quitarse el rol a sí mismo
    // Así siempre queda al menos un admin con acceso al sistema
    if ($id === $actorId) {
        $actual = buscarUsuario($pdo, $id);
        if ($actual !== null && ($datos['activo'] !== 1 || $datos['rol_id'] !== (int) $actual['rol_id'])) {
            header('Location: /pages/usuario_form.php?id=' . $id . '&error=cannot_modify_self');
            exit;
        }
    }

    // Si algo no es valido, se guarda lo escrito sin la contrasen y se vuelve al formulario
    $error = validarUsuario($pdo, $datos, $id);
    if ($error !== null) {
        $_SESSION['usuario_form'] = [
            'id' => $id,
            'nombre' => $datos['nombre'],
            'correo' => $datos['correo'],
            'rol_id' => $datos['rol_id'],
            'activo' => $datos['activo'],
        ];
        header('Location: /pages/usuario_form.php?id=' . $id . '&error=' . $error);
        exit;
    }
    
    // Actualiza el usuario y registra la accion en la bitacora, todo en una sola transaccion
    // Devuelve false si el usuario no existe o ya estaba eliminado
    if (!actualizarUsuario($pdo, $id, $datos, $actorId)) {
        header('Location: /pages/usuarios.php?error=user_not_found');
        exit;
    }
} catch (PDOException $e) {
    // 23000 es una violación de UNIQUE: otro administrador tomó ese correo justo antes
    if ($e->getCode() === '23000') {
        header('Location: /pages/usuario_form.php?id=' . $id . '&error=email_exists');
        exit;
    }
    error_log($e->getMessage());
    header('Location: /pages/usuarios.php?error=server_error');
    exit;
}

// Si el admin se editó a sí mismo, se actualiza el nombre que muestra la sesion
if ($id === $actorId) {
    $_SESSION['user_name'] = $datos['nombre'];
}

// Recargar la pagina no vuelve a enviar el formulario, patrón prg
header('Location: /pages/usuarios.php?success=user_updated');
exit;