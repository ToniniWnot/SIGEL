<?php
// Listado de usuarios, solo accesible para administradores
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/csrf.php';
require_once __DIR__ . '/../../utils/preferencias.php';
require_once __DIR__ . '/../../utils/usuarios.php';

requireRole(['administrador']);

// Cuántos usuarios por página, esto puede guardar una cookie 
// y por eso va antes de imprimir html

$porPagina = obtenerPorPagina('usuarios');

// Texto de busqueda, solo texto y con maximo de 100 caracteres
$busqueda = '';
if (isset($_GET['q']) && is_string($_GET['q'])) {
    $busqueda = mb_substr(trim($_GET['q']), 0, 100);
}

try {
    $pdo = obtener_conexion();
    $total = contarUsuarios($pdo, $busqueda);
    // Cantidad de paginas, minimo 1 para que la pagina 1 exista aunque no haya resultados
    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    // Página pedida, un entero entre 1 y la ultima, cualquier otra cosa cae en la 1
    $pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT) ?: 1;
    $pagina = min(max($pagina, 1), $totalPaginas);
    $usuarios = listarUsuarios($pdo, $busqueda, $porPagina, ($pagina - 1) * $porPagina);   
} catch (PDOException $e) {
    // El detalle del error va al log y al usuarios solo se le va a mostrar un mensaje general
    error_log($e->getMessage());
    header('Location: /pages/dashboard.php?error=server_error');
    exit;    
}

// Arma el enlace a una pagina conservando la busqueda
function urlUsuarios(string $busqueda, int $pagina): string {
    return '/pages/usuarios.php?' . http_build_query(['q' => $busqueda, 'pagina' => $pagina]);
}

$pageTitle = 'Usuarios';
require_once __DIR__ . '/common/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Usuarios</h2>
    <a href="/pages/usuario_form.php" class="btn btn-primary">Nuevo usuario</a>
</div>

<!-- Buscador y selector de registros por página, se envían por GET para poder compartir el enlace -->
<form method="get" action="/pages/usuarios.php" class="form-inline mb-3">
    <input type="text" name="q" class="form-control mr-2" placeholder="Buscar por nombre o correo"
           maxlength="100" value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>">
    <label for="per_page" class="mr-2">Por página</label>
    <select name="per_page" id="per_page" class="form-control mr-2" onchange="this.form.submit()">
        <?php foreach (PER_PAGE_OPTIONS as $opcion): ?>
            <option value="<?= $opcion ?>" <?= $opcion === $porPagina ? 'selected' : '' ?>><?= $opcion ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline-secondary">Buscar</button>
</form>

<?php if (count($usuarios) === 0): ?>
    <p class="text-muted">No se encontraron usuarios.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($u['rol'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ((int) $u['activo'] === 1): ?>
                                <span class="badge badge-success">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <a href="/pages/usuario_form.php?id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                            <?php // El administrador no puede eliminar su propia cuenta, el servidor también lo valida ?>
                            <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                                <form action="/actions/eliminar_usuario.php" method="post" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar este usuario?');">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <nav aria-label="Paginación de usuarios">
        <ul class="pagination">
            <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= htmlspecialchars(urlUsuarios($busqueda, max(1, $pagina - 1)), ENT_QUOTES, 'UTF-8') ?>">Anterior</a>
            </li>
            <li class="page-item disabled">
                <span class="page-link">Página <?= $pagina ?> de <?= $totalPaginas ?> (Total: <?= $total ?>)</span>
            </li>
            <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= htmlspecialchars(urlUsuarios($busqueda, min($totalPaginas, $pagina + 1)), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>
<?php require_once __DIR__ . '/common/footer.php'; ?>