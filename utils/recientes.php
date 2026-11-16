<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/https.php';

// Cookie con los ultimos equipos consultados
// Se guarda los ids separados por coma, por ejemplo "9, 2, 10"
const RECIENTES_COOKIE = 'sigel_recientes';
const RECIENTES_MAX = 5; 
const RECIENTES_DIAS = 30;

// Lee la cookie como una entrada NO confiable ya que el
// usuario puede editarla desde el navegador
// Nos devuelve hasta 5 ids que son enteros positivos y sin ser repetidos, se decarta cualquier otra cosa
function leerRecientes(): array {
    $valor = $_COOKIE[RECIENTES_COOKIE] ?? null;
    if (!is_string($valor)) {
        return [];
    }

    $ids = [];
    foreach (explode(',', $valor) as $parte) {
        $id = filter_var($parte, FILTER_VALIDATE_INT);
        if ($id !== false && $id >= 1 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
        if (count($ids) >= RECIENTES_MAX) {
            break;
        }
    }
    return $ids;
}

// Pone un equipo de primero en la lista y guarda la cookie
// Debemos llamar antes de imprimir cualquier html porque setcookie envia una cabecera
function registrarReciente(int $equipoId): void {
    // Si ya estaba en lista lo quitamos para que no quede repetido y asi suba al primer lugar
    $ids = array_values(array_filter(leerRecientes(), fn($id) => $id !== $equipoId));
    array_unshift($ids, $equipoId);
    $ids = array_slice($ids, 0, RECIENTES_MAX);
    $valor = implode(',', $ids);

    setcookie(RECIENTES_COOKIE, $valor, [
        'expires' => time() + (RECIENTES_DIAS * 86400),
        'path' => '/',
        'secure' => esHttps(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    // Así también se ve en esta misma petición
    $_COOKIE[RECIENTES_COOKIE] = $valor;
}

// Convierte los ids de la cookie en equipos reales que se pueden prestar
// Los ids que no existen, significan que están eliminados o no están disponibles
// se descartan
function listarEquiposRecientes(PDO $pdo): array {
    $ids = leerRecientes();
    if (count($ids) === 0) {
        return [];
    }

    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, codigo, nombre FROM equipos
                           WHERE id IN ($marcas) AND eliminado_en IS NULL AND estado = 'disponible'");
    $stmt->execute($ids);

    // La consulta no garantizará el orden, se vuelve a orden como estaba en la cookie
    $porId = [];
    foreach ($stmt->fetchAll() as $fila) {
        $porId[(int) $fila['id']] = $fila;
    }
    $resultado = [];
    foreach ($ids as $id) {
        if (isset($porId[$id])) {
            $resultado[] = $porId[$id];
        }
    }
    return $resultado;
}