<?php
// Formulario para crear o editar un equipo, solo visible para administradores
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/categorias.php';
require_once __DIR__ . '/../../utils/equipos.php';
require_once __DIR__ . '/../../utils/csrf.php';

requireRole(['administrador']);

// Si llega ?id= se edita ese equipo, si no se crea uno nuevo
$id = null;
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        header('Location: /pages/equipos.php?error=equipment_not_found');
        exit;
    }
}

try {
    $pdo = obtener_conexion();
    $equipo = $id === null ? null : buscarEquipo($pdo, $id);
    // Esto para llenar el select de categorias
    $categorias = listarCategoriasActivas($pdo);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/equipos.php?error=server_error');
    exit;
}

if ($id !== null && $equipo === null) {
    header('Location: /pages/equipos.php?error=equipment_not_found');
    exit;
}

// Valores del formulario, los del equipo, o los predeterminados si es nuevo
$valores = $equipo ?? ['codigo' => '', 'nombre' => '', 'categoria_id' => '', 'estado' => 'disponible'];

// Si la accion rechazó el envio, dejó lo escrito en la sesion, se usa una sola vez
// y solo si es del mismo formulario (mismo id)
$guardado = $_SESSION['equipo_form'] ?? null;
unset($_SESSION['equipo_form']);
if (is_array($guardado) && ($guardado['id'] ?? null) === $id) {
    $valores = $guardado;
}

$pageTitle = $id === null ? 'Nuevo equipo' : 'Editar equipo';
require_once __DIR__ . '/common/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><?= $id === null ? 'Nuevo equipo' : 'Editar equipo' ?></h4>
            </div>
            <div class="card-body">
                <form action="<?= $id === null ? '/actions/crear_equipo.php' : '/actions/actualizar_equipo.php' ?>" method="post">
                    <?php csrfField(); ?>
                    <?php if ($id !== null): ?>
                        <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" maxlength="30" required
                               value="<?= htmlspecialchars($valores['codigo'], ENT_QUOTES, 'UTF-8') ?>">
                        <small class="form-text text-muted">Letras, números, punto, guion o guion bajo. Se guarda en mayúsculas.</small>
                    </div>

                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" required
                               value="<?= htmlspecialchars($valores['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-group">
                        <label for="categoria_id">Categoría</label>
                        <select class="form-control" id="categoria_id" name="categoria_id" required>
                            <option value="">Seleccione una categoría</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (int) $valores['categoria_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select class="form-control" id="estado" name="estado" required>
                            <?php foreach (ESTADOS_EQUIPO as $clave => $etiqueta): ?>
                                <option value="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?>" <?= $valores['estado'] === $clave ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Un equipo en mantenimiento o dañado no se puede prestar.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="/pages/equipos.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/common/footer.php'; ?>
