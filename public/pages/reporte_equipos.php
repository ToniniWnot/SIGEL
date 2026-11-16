<?php
// Reporte de equipos más prestados por rango de fechas, solo lo ve el administrador
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/reportes.php';

requireRole(['administrador']);

[$desde, $hasta, $error] = rangoDesdeGet($_GET);
if ($error !== null) {
    header('Location: /pages/reporte_equipos.php?error=' . $error);
    exit;
}

try {
    $filas = equiposMasPrestados(obtener_conexion(), $desde, $hasta);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/dashboard.php?error=server_error');
    exit;
}

$totalPrestamos = array_sum(array_column($filas, 'prestamos'));
// Parámetros del rango ya validados, sirven para el enlace de exportación
$parametros = http_build_query(['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')]);

$pageTitle = 'Reporte de equipos más prestados';
require_once __DIR__ . '/common/header.php';
?>
<h2>Equipos más prestados</h2>

<form method="get" action="/pages/reporte_equipos.php" class="form-inline mb-3">
    <label for="desde" class="mr-2">Desde</label>
    <input type="date" class="form-control mr-3" id="desde" name="desde" required
           value="<?= htmlspecialchars($desde->format('Y-m-d'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <label for="hasta" class="mr-2">Hasta</label>
    <input type="date" class="form-control mr-3" id="hasta" name="hasta" required
           value="<?= htmlspecialchars($hasta->format('Y-m-d'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <button type="submit" class="btn btn-primary mr-2">Consultar</button>
    <a href="/actions/exportar_reporte_equipos.php?<?= htmlspecialchars($parametros, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
       class="btn btn-outline-success">Exportar CSV</a>
</form>
<p class="text-muted">
    Cuenta los préstamos entregados o devueltos cuya fecha de inicio está dentro del rango (ambos días incluidos).
    El rango máximo es de <?= REPORTE_MAX_DIAS ?> días.
</p>

<?php if (count($filas) === 0): ?>
    <p class="text-muted">No hay préstamos en ese rango.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th class="text-right">Veces prestado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $i => $f): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($f['codigo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($f['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($f['categoria'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td class="text-right"><?= (int) $f['prestamos'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="text-right">Total de equipos prestados</th>
                    <th class="text-right"><?= (int) $totalPrestamos ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/common/footer.php'; ?>