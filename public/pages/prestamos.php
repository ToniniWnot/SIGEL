<?php
// Listado de prestamos, solo lo puede verlo el operado
// Es el rol que registra y los avanza de estado
require_once __DIR__ . '/../../utils/prestamos.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/preferencias.php';
require_once __DIR__ . '/../../utils/csrf.php';

requireRole(['operador']);

// Cuantos prestamos por pagina, puede guardar una cookie y por eso va antes de imprimir html
$porPagina = obtenerPorPagina('prestamos');

// Texto de busqueda, sollo texto y con maximo de 100 caracteres
$busqueda = mb_substr(trim(textoPost($_GET, 'q')), 0, 100);

try {
    $pdo = obtener_conexion();
    $total = contarPrestamos($pdo, $busqueda);
    [$pagina, $totalPaginas] = calcularPagina($total, $porPagina);
    $prestamos = listarPrestamos($pdo, $busqueda, $porPagina, ($pagina - 1) * $porPagina);   
} catch (PDOException $e) {
    // El detalle del error va al log y al usuario solo se le va a mostrar un mensaje general
    error_log($e->getMessage());
    header('Location: /pages/dashboard.php?error=server_error');
    exit;    
}

// Color de la etiqueta de cada estado
const COLOR_ESTADO_PRESTAMO = [
    'solicitado' => 'secondary',
    'aprobado' => 'info',
    'entregado' => 'primary',
    'devuelto' => 'success',
    'cancelado' => 'danger',
];

// Boton de cambio de estado, el texto, la accion que envia y el color
const ACCIONES_PRESTAMO = [
    'aprobar' => ['Aprobar', 'success'],
    'entregar' => ['Entregar', 'primary'],
    'cancelar' => ['Cancelar', 'danger'],
];

// Que botones se muestran en cada estado, deben coincidir con las transiciones permitidas
const BOTONES_POR_ESTADO = [
    'solicitado' => ['aprobar', 'cancelar'],
    'aprobado' => ['entregar', 'cancelar'],
];

$pageTitle = 'Préstamos';
require_once __DIR__ . '/common/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Préstamos</h2>
    <a href="/pages/prestamo_form.php" class="btn btn-primary">Nuevo préstamo</a>
</div>

<?php renderBuscador('/pages/prestamos.php', $busqueda, $porPagina, 'Buscar por profesor, equipo o estado'); ?>

<?php if (count($prestamos) === 0): ?>
    <p class="text-muted">No se encontraron préstamos.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Profesor</th>
                    <th>Equipos</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Estado</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prestamos as $p): ?>
                    <tr>
                        <td><?= (int) $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['profesor'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($p['equipos'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($p['fecha_inicio'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($p['fecha_fin'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td>
                            <span class="badge badge-<?= COLOR_ESTADO_PRESTAMO[$p['estado']] ?? 'secondary' ?>">
                                <?= htmlspecialchars(PRESTAMO_ESTADOS[$p['estado']] ?? $p['estado'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <?php foreach (BOTONES_POR_ESTADO[$p['estado']] ?? [] as $accion): ?>
                                <form action="/actions/cambiar_estado_prestamo.php" method="post" class="d-inline">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <input type="hidden" name="accion" value="<?= $accion ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?= ACCIONES_PRESTAMO[$accion][1] ?>"
                                        <?= $accion === 'cancelar' ? "onclick=\"return confirm('¿Cancelar este préstamo?');\"" : '' ?>>
                                        <?= ACCIONES_PRESTAMO[$accion][0] ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                            <?php if ($p['estado'] === 'entregado'): ?>
                                <a href="/pages/prestamo_devolver.php?id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-success">Devolver</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderPaginacion('/pages/prestamos.php', $busqueda, $pagina, $totalPaginas, $total); ?>
<?php endif; ?>
<?php require_once __DIR__ . '/common/footer.php'; ?>