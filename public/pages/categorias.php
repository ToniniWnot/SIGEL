<?php
// Listado de categorias, solo accesible para rol admin
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/preferencias.php';
require_once __DIR__ . '/../../utils/categorias.php';

requireRole(['administrador']);

// Cuantas categorias por pag, esto puede guardar una cookie
// y por eso va antes de imprimir html
$porPagina = obtenerPorPagina('categorias');

// Texto de busqueda, solo texto y con un maximo de 100 caracteres
$busqueda = mb_substr(trim(textoPost($_GET, 'q')), 0, 100);

try {
    $pdo = obtener_conexion();
    $total = contarCategorias($pdo, $busqueda);
    [$pagina, $totalPaginas] = calcularPagina($total, $porPagina);
    $categorias = listarCategorias($pdo, $busqueda, $porPagina, ($pagina - 1) * $porPagina);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/dashboard.php?error=server_error');
    exit;
}

$pageTitle = 'Categorías';
require_once __DIR__ . '/common/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Categorías</h2>
    <a href="/pages/categoria_form.php" class="btn btn-primary">Nueva categoría</a>
</div>

<?php renderBuscador('/pages/categorias.php', $busqueda, $porPagina, 'Buscar por nombre'); ?>

<?php if (count($categorias) === 0): ?>
    <p class="text-muted">No se encontraron categorías.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Días máximos de préstamo</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) $c['dias_max_prestamo'] ?></td>
                        <td class="text-right">
                            <a href="/pages/categoria_form.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                            <form action="/actions/eliminar_categoria.php" method="post" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar esta categoría?');">
                                <?php csrfField(); ?>
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderPaginacion('/pages/categorias.php', $busqueda, $pagina, $totalPaginas, $total); ?>
<?php endif; ?>
<?php require_once __DIR__ . '/common/footer.php'; ?>