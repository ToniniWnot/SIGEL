<?php

// Cantidades de registros por página que se permiten y el valor por defecto
const PER_PAGE_OPTIONS = [5, 10, 20, 50];
const PER_PAGE_DEFAULT = 10;
const PER_PAGE_DAYS = 365; // Días que se guardan las preferencias de paginación

// Revisa que un valor sea uno de los permitidos
// Y devuleve el número como un entero o null si no es válido
function perPageValido($valor): ?int {
    // Solo acepto texto o numeros, un arreglo de estilo (?per_page[]=1) se descarta
    if (!is_string($valor) && !is_int($valor)) {
        return null;
    }

    // Lo convierte a entero pero si trae letras o decimales, devuelve false
    $numero = filter_var($valor, FILTER_VALIDATE_INT);

    // El true obliga a comparar tipo y valor, solo pasan los de la lista
    if ($numero === false || !in_array($numero, PER_PAGE_OPTIONS, true)) {
        return null;
    }
    return $numero;
}

// Nos devuelve cuantos registros mostrar por pagina en un CRUD
// Debe  llamarse antes de imprimir cualquier HTML, porque setcookie envia una cabecera
function obtenerPorPagina(string $modulo): int {
    // Cada CRUD tiene su propia cookie, per_page_usuarios, per_page_productos, etc
    $cookie = 'per_page_' . $modulo;

    // Primero, si el usuario eligió un valor en la URL (?per_page=20) y es valido, lo guardamos
    if (isset($_GET['per_page'])) {
        $elegido = perPageValido($_GET['per_page']);
        if ($elegido !== null) {
            setcookie($cookie, (string) $elegido, [
                'expires' => time() + (PER_PAGE_DAYS * 86400), // 1 año
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            return $elegido;
        }
    }

    // Segundo, si no eligió nada o si eligió algo invalido, usamos la cookie pero validada
    $guardado = perPageValido($_COOKIE[$cookie] ?? null);
    if ($guardado !== null) {
        return $guardado;
    }

    // Tercero, sin cookie o con un valor raro, usamos el valor por defecto
    return PER_PAGE_DEFAULT;
}
