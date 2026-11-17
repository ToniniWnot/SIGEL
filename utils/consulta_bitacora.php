<?php
// Consultas de la bitácora para la pantalla del admin, aquí solo se lee
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reportes.php';

// Lo que se puede filtrar, la llave se guarda en la BD y el texto es lo que se ve
const BITACORA_ENTIDADES = [
    'usuario' => 'Usuario',
    'categoria' => 'Categoría',
    'equipo' => 'Equipo',
    'equipo_imagen' => 'Imagen de equipo',
    'prestamo' => 'Préstamo',
];
const BITACORA_ACCIONES = [
    'crear' => 'Crear',
    'editar' => 'Editar',
    'eliminar' => 'Eliminar',
];

// Lee los filtros de la URL, todos son opcionales y 
// Devuelve [filtros, error]
function filtrosBitacoraDesdeGet(array $get): array {
    $vacios = ['entidad' => null, 'accion' => null, 'usuario' => 0, 'desde' => null, 'hasta' => null];

    $entidad = trim(textoPost($get, 'entidad'));
    $accion = trim(textoPost($get, 'accion'));
    $usuario = trim(textoPost($get, 'usuario'));
    $textoDesde = trim(textoPost($get, 'desde'));
    $textoHasta = trim(textoPost($get, 'hasta'));

    // Entidad y acción solo valen si están en las listas de arriba
    if ($entidad !== '' && !isset(BITACORA_ENTIDADES[$entidad])) {
        return [$vacios, 'invalid_filter'];
    }
    if ($accion !== '' && !isset(BITACORA_ACCIONES[$accion])) {
        return [$vacios, 'invalid_filter'];
    }

    $idUsuario = 0;
    if ($usuario !== '') {
        $idUsuario = filter_var($usuario, FILTER_VALIDATE_INT);
        if ($idUsuario === false || $idUsuario < 1) {
            return [$vacios, 'invalid_filter'];
        }
    }

    // Las fechas deben ser reales y "desde" no puede ir después de "hasta"
    $desde = $textoDesde === '' ? null : parsearDia($textoDesde);
    $hasta = $textoHasta === '' ? null : parsearDia($textoHasta);
    if (($textoDesde !== '' && $desde === null) || ($textoHasta !== '' && $hasta === null)) {
        return [$vacios, 'invalid_filter'];
    }
    if ($desde !== null && $hasta !== null && $desde > $hasta) {
        return [$vacios, 'invalid_filter'];
    }

    return [[
        'entidad' => $entidad === '' ? null : $entidad,
        'accion' => $accion === '' ? null : $accion,
        'usuario' => $idUsuario,
        'desde' => $desde,
        'hasta' => $hasta,
    ], null];
}

// Arma el WHERE solo con los filtros que llegaron, los valores van como parámetros
function condicionesBitacora(array $f): array {
    $condiciones = [];
    $params = [];
    if ($f['entidad'] !== null) {
        $condiciones[] = 'b.entidad = :entidad';
        $params[':entidad'] = $f['entidad'];
    }
    if ($f['accion'] !== null) {
        $condiciones[] = 'b.accion = :accion';
        $params[':accion'] = $f['accion'];
    }
    if ($f['usuario'] > 0) {
        $condiciones[] = 'b.usuario_id = :usuario';
        $params[':usuario'] = $f['usuario'];
    }
    if ($f['desde'] !== null) {
        $condiciones[] = 'b.creado_en >= :desde';
        $params[':desde'] = $f['desde']->format('Y-m-d 00:00:00');
    }
    if ($f['hasta'] !== null) {
        // hasta incluye todo ese día, por eso se compara con el inicio del día siguiente
        $condiciones[] = 'b.creado_en < :hasta';
        $params[':hasta'] = $f['hasta']->modify('+1 day')->format('Y-m-d 00:00:00');
    }
    $where = count($condiciones) > 0 ? 'WHERE ' . implode(' AND ', $condiciones) : '';
    return [$where, $params];
}

// Cuántos movimientos cumplen los filtros
function contarBitacora(PDO $pdo, array $filtros): int {
    [$where, $params] = condicionesBitacora($filtros);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bitacora b $where");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

// Una página de movimientos, del más nuevo al más viejo, con el nombre de quien lo hizo
function listarBitacora(PDO $pdo, array $filtros, int $limite, int $offset): array {
    [$where, $params] = condicionesBitacora($filtros);
    $stmt = $pdo->prepare("SELECT b.id, b.accion, b.entidad, b.entidad_id, b.valores_antes, b.valores_despues,
                                  b.ip, b.creado_en, u.nombre AS usuario
                           FROM bitacora b LEFT JOIN usuarios u ON u.id = b.usuario_id
                           $where
                           ORDER BY b.id DESC
                           LIMIT :limite OFFSET :offset");
    foreach ($params as $nombre => $valor) {
        $stmt->bindValue($nombre, $valor);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Usuarios que tienen movimientos, para el select del filtro
function listarUsuariosBitacora(PDO $pdo): array {
    return $pdo->query('SELECT u.id, u.nombre FROM usuarios u
                        WHERE EXISTS (SELECT 1 FROM bitacora b WHERE b.usuario_id = u.id)
                        ORDER BY u.nombre')->fetchAll();
}

// Muestra el JSON ordenado, y si no es JSON lo deja tal cual
function formatearJsonBitacora(?string $json): string {
    if ($json === null || $json === '') {
        return '';
    }
    $datos = json_decode($json, true);
    if (!is_array($datos)) {
        return $json;
    }
    return json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}