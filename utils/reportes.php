<?php
// Reporte de equipos mas prestados y su exportación a CSV

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// Rango mas largo que se puede consultar en dias 
const REPORTE_MAX_DIAS = 366;

// Convierte un texto yyyy-mm-dd en fecha o null si no es una fecha real
function parsearDia(string $texto): ?DateTimeImmutable {
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
    if ($fecha === false || $fecha->format('Y-m-d') !== $texto) {
        return null;
    }
    return $fecha;
}

// Lee el rango de fechas de la URL
// Si no se manda ninguna se usan los últimos 30 días
// Devuelve (desde, hasta, error) el error es null si el rango sirve o 'invalid_date_range'
function rangoDesdeGet(array $get): array {
    $hoy = new DateTimeImmutable('today');
    $textoDesde = trim(textoPost($get, 'desde'));
    $textoHasta = trim(textoPost($get, 'hasta'));

    if ($textoDesde === '' && $textoHasta === '') {
        return [$hoy->modify('-30 days'), $hoy, null];
    }

    $desde = parsearDia($textoDesde);
    $hasta = parsearDia($textoHasta);
    if ($desde === null || $hasta === null || $desde > $hasta) {
        return [null, null, 'invalid_date_range'];
    }
    if ($desde->diff($hasta)->days > REPORTE_MAX_DIAS) {
        return [null, null, 'invalid_date_range'];
    }
    return [$desde, $hasta, null];
}

// Equipos ordenados por cuántas veces se prestaron entre dos días (ambos incluidos)
// Cuenta los préstamos entregados o devueltos que son los que salieron de verdad según su fecha de inicio
function equiposMasPrestados(PDO $pdo, DateTimeImmutable $desde, DateTimeImmutable $hasta): array {
    $stmt = $pdo->prepare("SELECT e.id, e.codigo, e.nombre, c.nombre AS categoria, COUNT(*) AS prestamos
                           FROM prestamo_detalle d
                           JOIN prestamos p ON p.id = d.prestamo_id
                           JOIN equipos e ON e.id = d.equipo_id
                           JOIN categorias c ON c.id = e.categoria_id
                           WHERE p.estado IN ('entregado', 'devuelto')
                             AND p.fecha_inicio >= :desde AND p.fecha_inicio < :hasta
                           GROUP BY e.id, e.codigo, e.nombre, c.nombre
                           ORDER BY prestamos DESC, e.codigo");
    $stmt->execute([
        ':desde' => $desde->format('Y-m-d 00:00:00'),
        // El día final se incluye completo, por eso se usa el inicio del día siguiente
        ':hasta' => $hasta->modify('+1 day')->format('Y-m-d 00:00:00'),
    ]);
    return $stmt->fetchAll();
}

// Evita la inyeccion de formulas en Excel, si el 
// texto empieza con =, +, - o @ tabulador
// se le antepone una comilla simple para que se lea como texto y no se ejecute como formula
function neutralizarCsv(string $valor): string {
    if ($valor !== '' && strpos("=+-@\t\r", $valor[0]) !== false) {
        return "'" . $valor;
    }
    return $valor;
}