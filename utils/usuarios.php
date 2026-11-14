<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bitacora.php';
require_once __DIR__ . '/helpers.php';

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

// Buscar un usuario y listar roles

// Busca un usuario por su id, devuelve la fila o null si no existe o está eliminado
function buscarUsuario(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT u.id, u.rol_id, u.nombre, u.correo, u.activo, r.nombre AS rol
                           FROM usuarios u
                           INNER JOIN roles r ON r.id = u.rol_id
                           WHERE u.id = :id AND u.eliminado_en IS NULL
                           LIMIT 1');
    $stmt->execute([':id' => $id]);
    $fila = $stmt->fetch();
    return $fila === false ? null : $fila;
}

// Trae los roles para llenar el select del formulario
function listarRoles(PDO $pdo): array {
    return $pdo->query('SELECT id, nombre FROM roles ORDER BY id')->fetchAll();
}

// Limpiar lo que llega del formulario

// Tomamos lo que llegó del formulario $_POST y lo deja limpio con el tipo correto
function datosUsuarioDesdePost(array $post): array {
    return [
        'nombre' => trim(textoPost($post, 'nombre')),
        'correo' => strtolower(trim(textoPost($post, 'correo'))),
        // Solo un entero limpio sirve, cualquier otra cosa queda en cero y se rechaza como rol invalido
        'rol_id' => filter_var(textoPost($post, 'rol_id'), FILTER_VALIDATE_INT) ?: 0,
        // La contrasela no se recorta
        'password' => textoPost($post, 'password'),
        'activo' => isset($post['activo']) ? 1 : 0,
    ];
}

// Validar

// Valida los datos  de un usuario, devolvidendo un codigo 
// de error o null si todo está bien
// $idActual es el id del usuario que se edita o null si es nuevo
function validarUsuario(PDO $pdo, array $datos, ?int $idActual = null): ?string {
    // Nombre, entre 2 y 100 caracteres
    $largoNombre = mb_strlen($datos['nombre']);
    if ($largoNombre < 2 || $largoNombre > 100) {
        return 'invalid_name';
    }

    // Correo, con formato valido y de maximo 150 caracteres
    if (mb_strlen($datos['correo']) > 150 || filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) === false) {
        return 'invalid_email';
    }

    // Rol, tiene que existir en la tabla roles
    $stmt = $pdo->prepare('SELECT nombre FROM roles WHERE id = :id');
    $stmt->execute([':id' => $datos['rol_id']]);
    $rol = $stmt->fetchColumn();
    if ($rol === false) {
        return 'invalid_role';
    }

    // Contrasena, los profesores no inicias sesion, asi que no lleva contrasena
    // Para lo demas es obligatorio al crear y al editar solo se valida si escribieron una
    if ($rol !== 'profesor') {
        $largo = strlen($datos['password']); // strlen cuenta bytes, que es lo que usa bcrypt(maximo 72)
        $esNuevo = $idActual === null;
        $escribioClave = $datos['password'] !== '';
        if (($esNuevo || $escribioClave) && ($largo < 8 || $largo > 72)) {
            return 'invalid_password';
        }
    }

    // Correo repetido, puede ser de otro usuario, incluso uno eliminado, porque la columna es UNIQUE
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE correo = :correo AND id <> :id');
    $stmt->execute([':correo' => $datos['correo'], ':id' => $idActual ?? 0]);
    if ((int) $stmt->fetchColumn() > 0) {
        return 'email_exists';
    }

    return null;
}

// Devuelve el nombre de un rol a partir de su id, o null si no existe
function nombreRol(PDO $pdo , int $rolId): ?string {
    $stmt = $pdo->prepare('SELECT nombre FROM roles WHERE id = :id');
    $stmt->execute([':id' => $rolId]);
    $nombre = $stmt->fetchColumn();
    return $nombre === false ? null : $nombre;
}

// Crear un usuario y nos devuelve su id
// $actorId es el administrador que lo está creando, para registrar la acción en la bitácora
// Los datos ya deben venir validados con el metodo validarUsuario()
function crearUsuario(PDO $pdo, array $datos, int $actorId): int {
    // Los profesores no inician sesion, asi que no llevan contrasena
    // Para los demas roles, se guarda el hash, nunca la contrasena en texto plano
    $hash = nombreRol($pdo, $datos['rol_id']) === 'profesor' ? null
    : password_hash($datos['password'], PASSWORD_DEFAULT);

    // La transaccion, se guarda el usuario y la bitacora juntos o simplemente no se guarda nada
    $pdo->beginTransaction();
    try { 
        $stmt = $pdo->prepare('INSERT INTO usuarios (rol_id, nombre, correo, password_hash, activo)
                               VALUES (:rol_id, :nombre, :correo, :hash, :activo)');
        $stmt->execute([
            ':rol_id' => $datos['rol_id'],
            ':nombre' => $datos['nombre'],
            ':correo' => $datos['correo'],
            ':hash' => $hash,
            ':activo' => $datos['activo'],   
        ]);
        $id = (int) $pdo->lastInsertId();

        // En la bitacora solo van los datos del usuario, nunca lo contraseña
        registrarBitacora($pdo, $actorId, 'crear', 'usuario', $id, null, [
            'nombre' => $datos['nombre'],
            'correo' => $datos['correo'],
            'rol_id' => $datos['rol_id'],
            'activo' => $datos['activo'],
        ]);

        $pdo->commit();
        return $id;

    } catch (Throwable $e) {
        // Si algo falla, se deschace de todo y el error sigue en
        $pdo->rollBack();
        throw $e;
    }
}

// Actualiza un usuario y devuelve false si no existe o ya estaba eliminado
// Los datos ya deben venir validados con el metodo validarUsuario()
function actualizarUsuario(PDO $pdo, int $id, array $datos, int $actorId): bool {
    $pdo->beginTransaction();
    try {
        // Como estaba antes, para la bitacora
        $antes = buscarUsuario($pdo, $id);
        if ($antes === null) {
            $pdo->rollBack();
            return false;
        }

        $sql = 'UPDATE usuarios SET nombre = :nombre, correo = :correo, rol_id = :rol_id, activo = :activo';
        $params = [
            ':nombre' => $datos['nombre'],
            ':correo' => $datos['correo'],
            ':rol_id' => $datos['rol_id'],
            ':activo' => $datos['activo'],
            ':id' => $id,
        ];
        
        // Que pasa con la contrasena
        $cambiaClave = false;
        if (nombreRol($pdo, $datos['rol_id']) === 'profesor') {
            // Un profesor no lleva contrasena
            $sql .= ', password_hash = NULL';
            $cambiaClave = true;
        } elseif ($datos['password'] !== '') {
            // Escribieron una contrasena nueva
            $sql .= ', password_hash = :hash';
            $params[':hash'] = password_hash($datos['password'], PASSWORD_DEFAULT);
            $cambiaClave = true;
        }

        // Si la dejaron vacía, no se toca la contrase
        $stmt = $pdo->prepare($sql . ' WHERE id = :id AND eliminado_en IS NULL');
        $stmt->execute($params);

        // Si cambió la contrasena, lasc ocokies de "Recordarme" viejas ya no sirven, asi que se borran
        if ($cambiaClave) {
            $stmt = $pdo->prepare('DELETE FROM auth_tokens WHERE usuario_id = :id');
            $stmt->execute([':id' => $id]);
        }

        // Registramos la acción en la bitacora, con los datos antes y despues
        registrarBitacora($pdo, $actorId, 'editar', 'usuario', $id, [
            'nombre' => $antes['nombre'],
            'correo' => $antes['correo'],
            'rol_id' => (int) $antes['rol_id'],
            'activo' => (int) $antes['activo'],
        ], [
            'nombre' => $datos['nombre'],
            'correo' => $datos['correo'],
            'rol_id' => $datos['rol_id'],
            'activo' => $datos['activo'],
        ]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Elimina un usuario de forma lógica, no se bora la fila, solo se marca con la fecha
// Devuelve false si no existe o ya estaba eliminado
function eliminarUsuario(PDO $pdo, int $id, int $actorId): bool {
    $pdo->beginTransaction();
    try {
        $antes = buscarUsuario($pdo, $id);
        if ($antes === null) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare('UPDATE usuarios SET eliminado_en = NOW() WHERE id = :id AND eliminado_en IS NULL');
        $stmt->execute([':id' => $id]);

        // Sus cookies de recordarme se borran, ya no puede entrar por ahí
        $stmt = $pdo->prepare('DELETE FROM auth_tokens WHERE usuario_id = :id');
        $stmt->execute([':id' => $id]);

        registrarBitacora($pdo, $actorId, 'eliminar', 'usuario', $id, [
            'nombre' => $antes['nombre'],
            'correo' => $antes['correo'],
            'rol_id' => (int) $antes['rol_id'],
            'activo' => (int) $antes['activo'],
        ], null);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
