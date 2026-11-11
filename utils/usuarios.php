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

// Lee un campo del formulario como texto
// Y si no llegó o llegó un como arreglo, devuelve vació para que no pase nada raro
function textoPost(array $post, string $clave): string {
    $valor = $post[$clave] ?? '';
    return is_string($valor) ? $valor : '';
}

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
