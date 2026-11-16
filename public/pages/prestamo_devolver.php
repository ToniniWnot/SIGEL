<?php
// Mi regla individual #9
// Pantalla de devolucion, el operador marca cada equipo como bueno o danado 
require_once __DIR__ . '/../../utils/prestamos.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';

requireRole(['operador']);

// El ide llega por la URL, se valida como un entero positivo
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: /pages/prestamos.php?error=loan_not_found');
    exit;
}

try {
    $prestamo = buscarPrestamo(obtener_conexion(), $id);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/prestamos.php?error=server_error');
    exit;
}

if ($prestamo === null) {
    header('Location: /pages/prestamos.php?error=loan_not_found');
    exit;
}

// Solo permite devolver un prestamo que esté en estado entregado
if ($prestamo['estado'] !== 'entregado') {
    header('Location: /pages/prestamos.php?error=invalid_transition');
    exit;
}

$pageTitle = 'Devolver préstamo';
require_once __DIR__ . '/common/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Devolver préstamo #<?= (int) $prestamo['id'] ?></h4>
            </div>
            <div class="card-body">
                <p>
                    Profesor: <strong><?= htmlspecialchars($prestamo['profesor'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong><br>
                    Debía devolverlo el <?= htmlspecialchars(date('d/m/Y H:i', strtotime($prestamo['fecha_fin'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </p>
                <p class="text-muted">Indique en qué condición regresó cada equipo. Un equipo dañado queda fuera de servicio hasta que un administrador lo reactive.</p>

                <form action="/actions/devolver_prestamo.php" method="post">
                    <?php csrfField(); ?>
                    <input type="hidden" name="id" value="<?= (int) $prestamo['id'] ?>">

                    <?php foreach ($prestamo['equipos'] as $e): ?>
                        <div class="form-group border-bottom pb-2">
                            <div>
                                <strong><?= htmlspecialchars($e['codigo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                                - <?= htmlspecialchars($e['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" id="b<?= (int) $e['id'] ?>"
                                       name="condicion[<?= (int) $e['id'] ?>]" value="bueno" checked>
                                <label class="custom-control-label" for="b<?= (int) $e['id'] ?>">Buen estado</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" id="d<?= (int) $e['id'] ?>"
                                       name="condicion[<?= (int) $e['id'] ?>]" value="danado">
                                <label class="custom-control-label" for="d<?= (int) $e['id'] ?>">Dañado</label>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-success">Registrar devolución</button>
                    <a href="/pages/prestamos.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/common/footer.php'; ?>