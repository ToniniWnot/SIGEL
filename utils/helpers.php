<?php
// Funciones pequenas que usan varios modulos como usuarios, categorias, equipos, etc.
require_once __DIR__ . '/preferencias.php';

// Lee un campo del formulario como texto
// Si no llegó como arreglo (?nombre[]=x)
// devuelve vacío para que no se cuele nada raro

function textoPost(array $post, string $clave): string
{
    $valor = $post[$clave] ?? '';
    return is_string($valor) ? $valor : '';
}

// Prepara el texto de busqueda para usarlo en LIKE
// Escapa % y _ para que busquen como letras normales y no como comodines
function patronBusqueda(string $busqueda): string
{
    $limpio = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda);
    return '%' . $limpio . '%';
}

// Calcula la pagin actual a partir de ?pagina= sin salirse del rango
// Devuelve [pagina actual, total de paginas]
function calcularPagina(int $total, int $porPagina): array
{
    // Minimo 1 pagina, para que la pagina 1 exista aunque no haya resultados
    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    // Un entero entre 1 y la total, cualquier otra cosa cae en la 1
    $pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT) ?: 1;
    return [min(max($pagina, 1), $totalPaginas), $totalPaginas];
}

// Imprime el buscador y el selector de registros por página
// Va por GET para que el enlace se pueda compartir
function renderBuscador(string $ruta, string $busqueda, int $porPagina, string $placeholder): void
{
    $ruta = htmlspecialchars($ruta, ENT_QUOTES, 'UTF-8');
    $busqueda = htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8');
    $placeholder = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');

    $opciones = '';
    foreach (PER_PAGE_OPTIONS as $opcion) {
        $marcada = $opcion === $porPagina ? ' selected' : '';
        $opciones .= '<option value="' . $opcion . '"' . $marcada . '>' . $opcion . '</option>';
    }

    echo '<form method="get" action="' . $ruta . '" class="form-inline mb-3">'
        . '<input type="text" name="q" class="form-control mr-2" placeholder="' . $placeholder . '" maxlength="100" value="' . $busqueda . '">'
        . '<label for="per_page" class="mr-2">Por página</label>'
        . '<select name="per_page" id="per_page" class="form-control mr-2" onchange="this.form.submit()">' . $opciones . '</select>'
        . '<button type="submit" class="btn btn-outline-secondary">Buscar</button>'
        . '</form>';
}

// Imprime los botones Anterior y Siguiente conservando la busqueda
function renderPaginacion(string $ruta, string $busqueda, int $pagina, int $totalPaginas, int $total): void
{
    $url = fn(int $p) => htmlspecialchars($ruta . '?' . http_build_query(['q' => $busqueda, 'pagina' => $p]), ENT_QUOTES, 'UTF-8');

    echo '<nav aria-label="Paginación"><ul class="pagination">'
        . '<li class="page-item ' . ($pagina <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . $url(max(1, $pagina - 1)) . '">Anterior</a></li>'
        . '<li class="page-item disabled"><span class="page-link">Página ' . $pagina . ' de ' . $totalPaginas . ' (Total: ' . $total . ')</span></li>'
        . '<li class="page-item ' . ($pagina >= $totalPaginas ? 'disabled' : '') . '"><a class="page-link" href="' . $url(min($totalPaginas, $pagina + 1)) . '">Siguiente</a></li>'
        . '</ul></nav>';
}
