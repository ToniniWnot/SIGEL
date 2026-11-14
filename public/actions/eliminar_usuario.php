<?php
// Elimina un usuario de forma logica, la fila queda en la tabla, solo se marca como eliminada
require_once __DIR__ . '/../../utils/usuarios.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/auth.php';

// Solo acepta peticiones de tipo POST, si alguien abre directamente vuelve al listado
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/usuarios.php');
    exit;
}

// Solo el admin puede eliminar usuarios
requireRole(['administrador']);

// Si el token csrf no coincide, el metodo verifyCsrf() redirige con el error y detiene el script
verifyCsrf();

// El id viene en un campo oculto, pero eso lo puede cambiar cualquier, por eso validamos como un entero positivo
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: /pages/usuarios.php?error=user_not_found');
    exit;
}

$actorId = (int) $_SESSION['user_id'];

// Un admin no puede eliminar su propia cuenta
// El listado ya oculta ese boton, pero aquí se válida porque el 
// boton se puede saltar con curl
if ($id === $actorId) {
    header('Location: /pages/usuarios.php?error=cannot_modify_self');
    exit;
}

try {
    // Marca la fecha de eliminación y registra la accion en la bitacora, todo en una sola transaccion
    // Devuelve false si el usuario no existe o ya estaba eliminado
    if (!eliminarUsuario(obtener_conexion(), $id, $actorId)) {
        header('Location: /pages/usuarios.php?error=user_not_found');
        exit;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/usuarios.php?error=server_error');
    exit;
}

// Se redirige para que recargar la pagina no vuelva a enviar el formulario, patrón prg
header('Location: /pages/usuarios.php?success=user_deleted');
exit;