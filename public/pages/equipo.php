<?php
// Detalle de un equipo con sus imágenes, lo ven el administrador y el operador
// Solo el administrador sube o elimina imágenes
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/equipos.php';
require_once __DIR__ . '/../../utils/imagenes.php';
require_once __DIR__ . '/../../utils/recientes.php';
require_once __DIR__ . '/../../utils/csrf.php';

requireRole(['administrador', 'operador']);
$esAdmin = $_SESSION['user_role'] === 'administrador';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: /pages/equipos.php?error=equipment_not_found');
    exit;
}

try {
    $pdo = obtener_conexion();
    $stmt = $pdo->prepare('SELECT e.id, e.codigo, e.nombre, e.estado, c.nombre AS categoria, c.dias_max_prestamo
                           FROM equipos e JOIN categorias c ON c.id = e.categoria_id
                           WHERE e.id = :id AND e.eliminado_en IS NULL LIMIT 1');
    $stmt->execute([':id' => $id]);
    $equipo = $stmt->fetch();
    $imagenes = $equipo === false ? [] : listarImagenes($pdo, $id);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/equipos.php?error=server_error');
    exit;
}

if ($equipo === false) {
    header('Location: /pages/equipos.php?error=equipment_not_found');
    exit;
}

// Consultar el equipo lo agrega a la cookie de recientes, va antes de imprimir html porque envía una cabecera
registrarReciente($id);

$pageTitle = 'Equipo ' . $equipo['codigo'];
require_once __DIR__ . '/common/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0"><?= htmlspecialchars($equipo['codigo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
    <div>
        <?php if ($esAdmin): ?>
            <a href="/pages/equipo_form.php?id=<?= (int) $equipo['id'] ?>" class="btn btn-outline-primary">Editar</a>
        <?php endif; ?>
        <a href="/pages/equipos.php" class="btn btn-secondary">Volver</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <p class="mb-1"><strong>Nombre:</strong> <?= htmlspecialchars($equipo['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <p class="mb-1"><strong>Categoría:</strong> <?= htmlspecialchars($equipo['categoria'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            (máximo <?= (int) $equipo['dias_max_prestamo'] ?> días de préstamo)</p>
        <p class="mb-0"><strong>Estado:</strong> <?= htmlspecialchars(ESTADOS_EQUIPO[$equipo['estado']] ?? $equipo['estado'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    </div>
</div>

<h4>Imágenes</h4>
<?php if (count($imagenes) === 0): ?>
    <p class="text-muted">Este equipo todavía no tiene imágenes.</p>
<?php else: ?>
    <div class="row">
        <?php foreach ($imagenes as $img): ?>
            <div class="col-6 col-md-4 col-lg-3 mb-3">
                <div class="card h-100">
                    <img src="/pages/imagen.php?id=<?= (int) $img['id'] ?>" class="card-img-top"
                         alt="Imagen de <?= htmlspecialchars($equipo['codigo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                         style="object-fit: cover; height: 160px;">
                    <?php if ($esAdmin): ?>
                        <div class="card-footer p-1 text-center">
                            <form action="/actions/eliminar_imagen_equipo.php" method="post"
                                  onsubmit="return confirm('¿Eliminar esta imagen?');">
                                <?php csrfField(); ?>
                                <input type="hidden" name="id" value="<?= (int) $img['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($esAdmin): ?>
    <div class="card mt-3">
        <div class="card-body">
            <form action="/actions/subir_imagen_equipo.php" method="post" enctype="multipart/form-data">
                <?php csrfField(); ?>
                <input type="hidden" name="equipo_id" value="<?= (int) $equipo['id'] ?>">
                <div class="form-group">
                    <label for="imagenes">Agregar imágenes</label>
                    <input type="file" class="form-control-file" id="imagenes" name="imagenes[]" multiple
                           accept="image/jpeg,image/png,image/webp" required>
                    <small class="form-text text-muted">
                        JPG, PNG o WEBP, máximo 2 MB cada una y hasta <?= IMAGENES_MAX_POR_EQUIPO ?> por equipo.
                    </small>
                </div>
                <button type="submit" class="btn btn-primary">Subir</button>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/common/footer.php'; ?>