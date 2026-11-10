<?php

require_once __DIR__ . '/db.php';

// Prepara el texto de busqueda para usurlo en un LIKE
// Escapa % y _ para que se busquen como letras normales y no como comodines
function patronBusqueda(string $busqueda): string {
    $limpio = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda);
    return '%' . $limpio . '%';
} 
// Cuenta cuántos usuarios hay sin contar los eliminados, y filtra por la búsqueda
function contarUsuarios(PDO $pdo, string $busqueda): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios u
                           WHERE u.eliminado_en IS NULL
                           AND (u.nombre LIKE :q1 OR u.correo LIKE :q2)');
    $patron = patronBusqueda($busqueda);
    $stmt->execute([':q1' => $patron, ':q2' => $patron]);
    return (int) $stmt->fetchColumn();
}

// Trae solo la página de usuarios, con su rol
// $limites es cuantos traer y $offset es cuantos saltarse antes de empezar
function listarUsuarios(PDO $pdo, string $busqueda, int $limite, int $offset): array {
    $stmt = $pdo->prepare('SELECT u.id, u.nombre, u.correo, u.activo, r.nombre AS rol
                           FROM usuarios u
                           INNER JOIN roles r ON r.id =u.rol_id
                           WHERE u.eliminado_en IS NULL
                           AND (u.nombre LIKE :q1 OR u.correo LIKE :q2)
                           ORDER BY u.nombre ASC, u.id ASC
                           LIMIT :limite OFFSET :offset');
    $patron = patronBusqueda($busqueda);
    $stmt->bindValue(':q1', $patron);
    $stmt->bindValue(':q2', $patron);
    // LIMIT y OFFSET exigen numeros, por eso indica PARAM_INT
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}