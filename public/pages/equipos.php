<?php
// Listado de equipos, lo ve solo el administrador y el operador, solamente 
// los modifica el administrador
require_once __DIR__ . '/../../utils/equipos.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/preferencias.php';
require_once __DIR__ . '/../../utils/csrf.php';

requireRole(['administrador', 'operador']);
$esAdmin = $_SESSION['user_role'] === 'administrador';

// Cuantos equipos por pagina, esto puede guardar una cookie y por eso va antes de imprimir html
$porPagina = obtenerPorPagina('equipos');

// Texto de busqueda, solo texto y con maximo de 100 caracteres
$busqueda = mb_substr(trim(textoPost($_GET, 'q')), 0, 100);

try {
    $pdo = obtener_conexion();
    $total = contarEquipos($pdo, $busqueda);
    [$pagina, $totalPaginas] = calcularPagina($total, $porPagina);
    $equipos = listarEquipos($pdo, $busqueda, $porPagina, ($pagina - 1) * $porPagina);   
} catch (PDOException $e) {
    // El detalle del error va al log y al equipos solo se le va a mostrar un mensaje general
    error_log($e->getMessage());
    header('Location: /pages/dashboard.php?error=server_error');
    exit;       
}

// Color de la etiqueta de cada estado
const COLOR_ESTADO = [
    'disponible' => 'success',
    'mantenimiento' => 'warning',
    'danado' => 'danger',
];

$pageTitle = 'Equipos';
require_once __DIR__ . '/common/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Equipos</h2>
    <?php if ($esAdmin): ?>
        <a href="/pages/equipo_form.php" class="btn btn-primary">Nuevo equipo</a>
    <?php endif; ?>
</div>

<?php renderBuscador('/pages/equipos.php', $busqueda, $porPagina, 'Buscar por código, nombre o categoría'); ?>

<?php if (count($equipos) === 0): ?>
    <p class="text-muted">No se encontraron equipos.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Estado</th>
                    <?php if ($esAdmin): ?>
                        <th class="text-right">Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipos as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($e['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($e['categoria'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge badge-<?= COLOR_ESTADO[$e['estado']] ?? 'secondary' ?>">
                                <?= htmlspecialchars(ESTADOS_EQUIPO[$e['estado']] ?? $e['estado'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <?php if ($esAdmin): ?>
                            <td class="text-right">
                                <a href="/pages/equipo_form.php?id=<?= (int) $e['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                <form action="/actions/eliminar_equipo.php" method="post" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar este equipo?');">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderPaginacion('/pages/equipos.php', $busqueda, $pagina, $totalPaginas, $total); ?>
<?php endif; ?>
<?php require_once __DIR__ . '/common/footer.php'; ?>