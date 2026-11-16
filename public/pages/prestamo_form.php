<?php
// Formulario para registrar un prestamo, solo accesible para el operador
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/prestamos.php';
require_once __DIR__ . '/../../utils/recientes.php';
require_once __DIR__ . '/../../utils/csrf.php';

requireRole(['operador']);

try {
    $pdo = obtener_conexion();
    $profesores = listarProfesores($pdo);
    $equipos = listarEquiposDisponibles($pdo);
    // Equipos consultados hace poco (cookie), para elegirlos más rápido
    $recientes = listarEquiposRecientes($pdo);
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: /pages/prestamos.php?error=server_error');
    exit;
}

// Valores del formulario, vacios si es nuevo
$valores = [
    'profesor_id' => 0,
    'fecha_inicio' => '',
    'fecha_fin' => '',
    'equipos' => []
];

// Si la accion rechazó el envio, deja lo escrito en la sesion, se usa una sola vez
$guardado = $_SESSION['prestamo_form'] ?? null;
unset($_SESSION['prestamo_form']);
if (is_array($guardado)) {
    $valores = $guardado;
}

$pageTitle = 'Nuevo préstamo';
require_once __DIR__ . '/common/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Nuevo préstamo</h4>
            </div>
            <div class="card-body">
                <form action="/actions/crear_prestamo.php" method="post">
                    <?php csrfField(); ?>

                    <div class="form-group">
                        <label for="profesor_id">Profesor que recibe el equipo</label>
                        <select class="form-control" id="profesor_id" name="profesor_id" required>
                            <option value="">Seleccione un profesor</option>
                            <?php foreach ($profesores as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" <?= (int) $valores['profesor_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="fecha_inicio">Inicio</label>
                            <input type="datetime-local" class="form-control" id="fecha_inicio" name="fecha_inicio" required
                                   value="<?= htmlspecialchars($valores['fecha_inicio'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="fecha_fin">Fin</label>
                            <input type="datetime-local" class="form-control" id="fecha_fin" name="fecha_fin" required
                                   value="<?= htmlspecialchars($valores['fecha_fin'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        </div>
                    </div>

                    <?php if (count($recientes) > 0): ?>
                        <div class="form-group">
                            <label>Consultados recientemente</label><br>
                            <?php foreach ($recientes as $r): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary mb-1 btn-reciente"
                                        data-id="<?= (int) $r['id'] ?>">
                                    <?= htmlspecialchars($r['codigo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="filtro">Equipos (máximo <?= MAX_EQUIPOS_PRESTAMO ?>)</label>
                        <input type="text" class="form-control mb-2" id="filtro" placeholder="Filtrar por código o nombre" autocomplete="off">
                        <?php if (count($equipos) === 0): ?>
                            <p class="text-muted">No hay equipos disponibles para prestar.</p>
                        <?php endif; ?>
                        <?php foreach ($equipos as $e): ?>
                            <?php $texto = $e['codigo'] . ' ' . $e['nombre'] . ' ' . $e['categoria']; ?>
                            <div class="custom-control custom-checkbox fila-equipo"
                                 data-texto="<?= htmlspecialchars(mb_strtolower($texto), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                <input type="checkbox" class="custom-control-input" id="eq<?= (int) $e['id'] ?>"
                                       name="equipos[]" value="<?= (int) $e['id'] ?>"
                                       <?= in_array((int) $e['id'], array_map('intval', $valores['equipos']), true) ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="eq<?= (int) $e['id'] ?>">
                                    <?= htmlspecialchars($e['codigo'] . ' - ' . $e['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    <small class="text-muted">
                                        (<?= htmlspecialchars($e['categoria'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>,
                                        máx. <?= (int) $e['dias_max_prestamo'] ?> días)
                                    </small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-primary">Registrar préstamo</button>
                    <a href="/pages/prestamos.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
// Filtro de la lista de equipos: oculta los que no coinciden con lo escrito
document.getElementById('filtro').addEventListener('input', function () {
    var texto = this.value.toLowerCase();
    document.querySelectorAll('.fila-equipo').forEach(function (fila) {
        fila.style.display = fila.dataset.texto.indexOf(texto) === -1 ? 'none' : '';
    });
});
// Los botones de recientes marcan la casilla del equipo (si sigue disponible)
document.querySelectorAll('.btn-reciente').forEach(function (boton) {
    boton.addEventListener('click', function () {
        var casilla = document.getElementById('eq' + this.dataset.id);
        if (casilla) { casilla.checked = true; }
    });
});
</script>
<?php require_once __DIR__ . '/common/footer.php'; ?>