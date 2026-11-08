<?php

require_once __DIR__ . '/db.php';

// Guarda una linea en la bitácora, quien hizo qué, sobre qué, como estaba antes y después
// Acá usamos la misma conexión $pdo de la operación, asi si hay una transacción abierta, 
// la bitácora se guarda o se deshace junto con el cambio

function registrarBitacora(PDO $pdo, int $usuarioId, string $accion, string $entidad, int $entidadId, ?array $antes = null, ?array $despues = null): void {
    // Por seguridad nunca se guarda el hash de la contraseña en la bitácora
    unset($antes['password_hash'], $despues['password_hash']);

    // Los arreglos se guardan como texto JSON, y si no hay datos queda null
    $jsonAntes = $antes === null ? null : json_encode($antes, JSON_UNESCAPED_UNICODE);
    $jsonDespues = $despues === null ? null : json_encode($despues, JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare('INSERT INTO bitacora (usuario_id, accion, entidad, entidad_id, valores_antes, valores_despues, ip)
                           VALUES (:usuario_id, :accion, :entidad, :entidad_id, :antes, :despues, :ip)');
    $stmt->execute([
        'usuario_id' => $usuarioId,
        'accion' => $accion,
        'entidad' => $entidad,
        'entidad_id' => $entidadId,
        'antes' => $jsonAntes,
        'despues' => $jsonDespues,
        // La IP la pone el servidor, no se toma de la cabecera que el cliente puede falsificar
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}
