<?php
// Formulario para crear o editar un usuario, solo para administradores
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/usuarios.php';

requireRole(['administrador']);

// Si llega ?id= se edita ese usuario, si no se crea uno nuevo
$id = null;
if (isset($_GET['id'])) {
    // Solo sirve un entero positivo, cualquier otra cosa se trata como usuario inexistente
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        header('Location: /pages/usuarios.php?error=user_not_found');
        exit;
    }
}

try {
    $pdo = obtener_conexion();
    $roles = listarRoles($pdo);
    $usuario = $id === null ? null : buscarUsuario($pdo, $id);
} catch (PDOException $e) {
    // El detalle del error va al log y al usuario solo se le va a mostrar un mensaje general
    error_log($e->getMessage());
    header('Location: /pages/usuarios.php?error=server_error');
    exit;    
}

// Si se pide editar un usuarios que no existe o ya fue eliminado
if ($id !== null && $usuario === null) {
    header('Location: /pages/usuarios.php?error=user_not_found');
    exit;
}

// Valores con los que se llena el formulario, los del usuario, o vacios si es nuevo
$valores = $usuario ?? ['nombre' => '', 'correo' => '', 'rol_id' => 0, 'activo' => 1];

// Si la accion rechaza el envio, dejó en la sesion lo que el admin habia escrito para no perderlo
// Solo se usa vez y solo si es del mismo formulario (mismo id)
$guardado = $_SESSION['usuario_form'] ?? null;
unset($_SESSION['usuario_form']);
if (is_array($guardado) && ($guardado['id'] ?? null) === $id) {
    $valores = $guardado;
}

$pageTitle = $id === null ? 'Nuevo usuario' : 'Editar usuario';
require_once __DIR__ . '/common/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><?= $id === null ? 'Nuevo usuario' : 'Editar usuario' ?></h4>
            </div>
            <div class="card-body">
                <form action="<?= $id === null ? '/actions/crear_usuario.php' : '/actions/actualizar_usuario.php' ?>" method="post">
                    <?php csrfField(); ?>
                    <?php if ($id !== null): ?>
                        <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" required
                               value="<?= htmlspecialchars($valores['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-group">
                        <label for="correo">Correo</label>
                        <input type="email" class="form-control" id="correo" name="correo" maxlength="150" required
                               value="<?= htmlspecialchars($valores['correo'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-group">
                        <label for="rol_id">Rol</label>
                        <select class="form-control" id="rol_id" name="rol_id" required>
                            <option value="">Seleccione un rol</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?= (int) $rol['id'] ?>" <?= (int) $rol['id'] === (int) $valores['rol_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rol['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password"
                               maxlength="72" autocomplete="new-password">
                        <small class="form-text text-muted">
                            <?php if ($id === null): ?>
                                Entre 8 y 72 caracteres. Los profesores no inician sesión, por eso no llevan contraseña.
                            <?php else: ?>
                                Déjela vacía para conservar la contraseña actual.
                            <?php endif; ?>
                        </small>
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1"
                               <?= (int) $valores['activo'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Usuario activo</label>
                    </div>

                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="/pages/usuarios.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/common/footer.php'; ?>






