<?php 
// Formulario para crear o editar una categoria, solo para rol admin
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/categorias.php';

requireRole(['administrador']);

// Si llega un ?id= se edita esa categoria, si no se crea una nueva
$id = null;
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        header('Location: /pages/categorias.php?error=category_not_found');
        exit;    
    }
}

try {
    $categoria = $id === null ? null : buscarCategoria(obtener_conexion(), $id);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/categorias.php?error=server_error');
    exit;
}

if ($id !== null && $categoria === null) {
    header('Location: /pages/categorias.php?error=category_not_found');
    exit;
}

// Valores del formulario, los de la categoria o los por defecto si es nueva
$valores = $categoria ?? ['nombre' => '', 'dias_max_prestamo' => 7];

// Si la accion rechazó el envio, dejó lo escrito en la sesion, se usa una sola vez
// Y si solo es del mismo formulario
$guardado = $_SESSION['categoria_form'] ?? null;
unset($_SESSION['categoria_form']);
if (is_array($guardado) && ($guardado['id'] ?? null) === $id) {
    $valores = $guardado;
}

$pageTitle = $id === null ? 'Nueva categoría' : 'Editar categoría';
require_once __DIR__ . '/common/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><?= $id === null ? 'Nueva categoría' : 'Editar categoría' ?></h4>
            </div>
            <div class="card-body">
                <form action="<?= $id === null ? '/actions/crear_categoria.php' : '/actions/actualizar_categoria.php' ?>" method="post">
                    <?php csrfField(); ?>
                    <?php if ($id !== null): ?>
                        <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" maxlength="80" required
                               value="<?= htmlspecialchars($valores['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-group">
                        <label for="dias_max_prestamo">Días máximos de préstamo</label>
                        <input type="number" class="form-control" id="dias_max_prestamo" name="dias_max_prestamo"
                               min="1" max="365" required value="<?= (int) $valores['dias_max_prestamo'] ?>">
                        <small class="form-text text-muted">Un préstamo de un equipo de esta categoría no puede durar más que esto.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="/pages/categorias.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/common/footer.php'; ?>