<?php
// Bitácora:, quién hizo qué y cuándo. Solo la ve el admin y no se puede editar nada
require_once __DIR__ . '/../../utils/consulta_bitacora.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/preferencias.php';

requireRole(['administrador']);

// Si algún filtro viene mal, se devuelve con el error
[$filtros, $error] = filtrosBitacoraDesdeGet($_GET);
if ($error !== null) {
    header('Location: /pages/bitacora.php?error=' . $error);
    exit;
}

// Va antes del HTML porque puede guardar la cookie
$porPagina = obtenerPorPagina('bitacora');

try {
    $pdo = obtener_conexion();
    $total = contarBitacora($pdo, $filtros);
    [$pagina, $totalPaginas] = calcularPagina($total, $porPagina);
    $movimientos = listarBitacora($pdo, $filtros, $porPagina, ($pagina - 1) * $porPagina);
    $usuarios = listarUsuariosBitacora($pdo);
} catch (PDOException $e) {
    // El detalle queda en el log y el usuario solo ve un error genérico
    error_log($e->getMessage());
    header('Location: /pages/dashboard.php?error=server_error');
    exit;
}

// Filtros ya validados, para que Anterior y Siguiente no los pierdan
$parametros = array_filter([
    'entidad' => $filtros['entidad'],
    'accion' => $filtros['accion'],
    'usuario' => $filtros['usuario'] > 0 ? $filtros['usuario'] : null,
    'desde' => $filtros['desde']?->format('Y-m-d'),
    'hasta' => $filtros['hasta']?->format('Y-m-d'),
], fn($valor) => $valor !== null);
$enlace = fn(int $p) => htmlspecialchars('/pages/bitacora.php?' . http_build_query($parametros + ['pagina' => $p]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Color de la etiqueta según la acción
const COLOR_ACCION_BITACORA = [
    'crear' => 'success',
    'editar' => 'info',
    'eliminar' => 'danger',
];

$pageTitle = 'Bitácora';
require_once __DIR__ . '/common/header.php';
?>
<h2>Bitácora</h2>

<form method="get" action="/pages/bitacora.php" class="form-row mb-3">
    <div class="form-group col-md-2">
        <label for="entidad">Entidad</label>
        <select name="entidad" id="entidad" class="form-control">
            <option value="">Todas</option>
            <?php foreach (BITACORA_ENTIDADES as $clave => $etiqueta): ?>
                <option value="<?= htmlspecialchars($clave, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $filtros['entidad'] === $clave ? 'selected' : '' ?>>
                    <?= htmlspecialchars($etiqueta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group col-md-2">
        <label for="accion">Acción</label>
        <select name="accion" id="accion" class="form-control">
            <option value="">Todas</option>
            <?php foreach (BITACORA_ACCIONES as $clave => $etiqueta): ?>
                <option value="<?= htmlspecialchars($clave, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $filtros['accion'] === $clave ? 'selected' : '' ?>>
                    <?= htmlspecialchars($etiqueta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group col-md-2">
        <label for="usuario">Usuario</label>
        <select name="usuario" id="usuario" class="form-control">
            <option value="">Todos</option>
            <?php foreach ($usuarios as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= $filtros['usuario'] === (int) $u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group col-md-2">
        <label for="desde">Desde</label>
        <input type="date" name="desde" id="desde" class="form-control"
               value="<?= htmlspecialchars($filtros['desde']?->format('Y-m-d') ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </div>
    <div class="form-group col-md-2">
        <label for="hasta">Hasta</label>
        <input type="date" name="hasta" id="hasta" class="form-control"
               value="<?= htmlspecialchars($filtros['hasta']?->format('Y-m-d') ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </div>
    <div class="form-group col-md-2">
        <label for="per_page">Por página</label>
        <select name="per_page" id="per_page" class="form-control">
            <?php foreach (PER_PAGE_OPTIONS as $opcion): ?>
                <option value="<?= $opcion ?>" <?= $opcion === $porPagina ? 'selected' : '' ?>><?= $opcion ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a href="/pages/bitacora.php" class="btn btn-secondary">Limpiar</a>
    </div>
</form>

<?php if (count($movimientos) === 0): ?>
    <p class="text-muted">No hay movimientos con esos filtros.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Entidad</th>
                    <th>IP</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movimientos as $m): ?>
                    <tr>
                        <td><?= (int) $m['id'] ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($m['creado_en'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($m['usuario'] ?? 'Usuario eliminado', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td>
                            <span class="badge badge-<?= COLOR_ACCION_BITACORA[$m['accion']] ?? 'secondary' ?>">
                                <?= htmlspecialchars(BITACORA_ACCIONES[$m['accion']] ?? $m['accion'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars(BITACORA_ENTIDADES[$m['entidad']] ?? $m['entidad'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            #<?= (int) $m['entidad_id'] ?>
                        </td>
                        <td><?= htmlspecialchars($m['ip'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td>
                            <?php if ($m['valores_antes'] === null && $m['valores_despues'] === null): ?>
                                <span class="text-muted">—</span>
                            <?php else: ?>
                                <details>
                                    <summary>Ver</summary>
                                    <?php if ($m['valores_antes'] !== null): ?>
                                        <small class="text-muted">Antes</small>
                                        <pre class="mb-1"><?= htmlspecialchars(formatearJsonBitacora($m['valores_antes']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
                                    <?php endif; ?>
                                    <?php if ($m['valores_despues'] !== null): ?>
                                        <small class="text-muted">Después</small>
                                        <pre class="mb-0"><?= htmlspecialchars(formatearJsonBitacora($m['valores_despues']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
                                    <?php endif; ?>
                                </details>
                                <?php // Antes y después salen escapados, nunca como HTML ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <nav aria-label="Paginación">
        <ul class="pagination">
            <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $enlace(max(1, $pagina - 1)) ?>">Anterior</a></li>
            <li class="page-item disabled"><span class="page-link">Página <?= $pagina ?> de <?= $totalPaginas ?> (Total: <?= $total ?>)</span></li>
            <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>"><a class="page-link" href="<?= $enlace(min($totalPaginas, $pagina + 1)) ?>">Siguiente</a></li>
        </ul>
    </nav>
<?php endif; ?>
<?php require_once __DIR__ . '/common/footer.php'; ?>