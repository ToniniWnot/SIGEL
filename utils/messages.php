<?php
// Mensajes de la interfaz, las páginas reciben solo un código por la URL
// Parámetros error o success y aquí se traduce a un texto fijo
// Para que así nunca se imprima texto que venga de la URL

const MESSAGES = [
    'error' => [
        'invalid_input' => 'Complete todos los campos requeridos de manera correcta.',
        'invalid_credentials' => 'Credenciales inválidas.',
        'login_required' => 'Debe iniciar sesión para acceder a esta página.',
        'session_expired' => 'Su sesión ha expirado. Inicie sesión nuevamente.',
        'account_locked' => 'Su cuenta ha sido bloqueada temporalmente. Intente en unos minutos.',
        'forbidden' => 'No tiene permisos para realizar esa acción.',
        'csrf_invalid' => 'La solicitud no es válida. Intente nuevamente.',
        'server_error' => 'Ocurrió un error. Inténtelo más tarde.',
        'invalid_name' => 'El nombre debe tener entre 2 y 100 caracteres.',
        'invalid_email' => 'Ingrese un correo válido de máximo 150 caracteres.',
        'invalid_role' => 'Seleccione un rol válido.',
        'invalid_password' => 'La contraseña debe tener entre 8 y 72 caracteres.',
        'email_exists' => 'Ya existe un usuario con el mismo correo.',
        'user_not_found' => 'El usuario no existe o ya fue eliminado del sistema.',
        'cannot_modify_self' => 'No puede desactivar, eliminar ni tampoco cambiar el rol de su propia cuenta.',
    ],
    'success' => [
        'logged_out' => 'Sesión cerrada exitosamente.',
        'user_created' => 'Usuario creado correctamente.',
        'user_updated' => 'Usuario actualizado correctamente.',
        'user_deleted' => 'Usuario eliminado correctamente.',
    ],
];

// Muestra una alerta de bootstrap si la URL trae un código conocido
function renderAlert() {
    
    foreach (['error' => 'danger', 'success' => 'success'] as $type => $style) {
        $code = $_GET[$type] ?? null;
        if (is_string($code) && isset(MESSAGES[$type][$code])) {
            echo '<div class="alert alert-' . $style . '" role="alert">'
                . htmlspecialchars(MESSAGES[$type][$code], ENT_QUOTES, 'UTF-8')
                . '</div>';
        }
    }
}