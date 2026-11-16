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
        'invalid_category_name' => 'El nombre de la categoría debe tener entre 2 y 80 caracteres.',
        'invalid_days' => 'Los días máximos de préstamo deben ser un número entre 1 y 365.',
        'category_exists' => 'Ya existe una categoría con el mismo nombre.',
        'category_not_found' => 'La categoría no existe o ya fue eliminada del sistema.',
        'category_in_use' => 'No se puede eliminar la categoría porque tiene equipos asociados.',
        'invalid_equipment_code' => 'El código del equipo debe tener de 2 a 30 caracteres: letras, números, punto, guion o guion bajo.',
        'invalid_equipment_name' => 'El nombre del equipo debe tener entre 2 y 100 caracteres.',
        'invalid_state' => 'Seleccione un estado válido para el equipo.',
        'invalid_category' => 'Seleccione una categoría válida.',
        'equipment_exists' => 'Ya existe un equipo con el mismo código.',
        'equipment_not_found' => 'El equipo no existe o ya fue eliminado del sistema.',
        'equipment_in_use' => 'No se puede eliminar el equipo porque está en un préstamo activo.',
        'invalid_professor' => 'Seleccione un profesor válido.',
        'invalid_dates' => 'Las fechas no son válidas, la fecha de fin debe ser posterior a la de inicio.',
        'date_in_past' => 'El préstamo no puede iniciar en una fecha que ya pasó.',
        'invalid_equipment_selection' => 'Seleccione entre 1 y 10 equipos.',
        'equipment_unavailable' => 'Alguno de los equipos no existe o no está disponible para préstamo.',
        'loan_too_long' => 'La duración del préstamo supera el máximo de días que permite la categoría de alguno de los equipos.',
        'loan_overlap' => 'Alguno de los equipos ya está prestado o solicitado en ese horario.',
        'loan_not_found' => 'El préstamo no existe.',
        'invalid_transition' => 'Ese cambio de estado no está permitido para el préstamo.',
        'invalid_condition' => 'Indique la condición, buena o dañada, de cada equipo devuelto.',
        'no_image_selected' => 'Seleccione al menos una imagen.',
        'invalid_image' => 'Solo se permiten imágenes JPG, PNG o WEBP válidas.',
        'image_too_large' => 'Cada imagen debe pesar máximo 2 MB.',
        'image_limit' => 'Un equipo puede tener como máximo 5 imágenes.',
        'image_not_found' => 'La imagen no existe o ya fue eliminada.',
        'upload_failed' => 'No se pudo guardar la imagen. Inténtelo más tarde.',
        'invalid_date_range' => 'El rango de fechas no es válido, revise que sea de máximo 366 días y que la fecha inicial no sea posterior a la final.',
        'cannot_modify_self' => 'No puede desactivar, eliminar ni tampoco cambiar el rol de su propia cuenta.',
    ],
    'success' => [
        'logged_out' => 'Sesión cerrada exitosamente.',
        'user_created' => 'Usuario creado correctamente.',
        'user_updated' => 'Usuario actualizado correctamente.',
        'user_deleted' => 'Usuario eliminado correctamente.',
        'category_created' => 'Categoría creada correctamente.',
        'category_updated' => 'Categoría actualizada correctamente.',
        'category_deleted' => 'Categoría eliminada correctamente.',
        'equipment_created' => 'Equipo creado correctamente.',
        'equipment_updated' => 'Equipo actualizado correctamente.',
        'equipment_deleted' => 'Equipo eliminado correctamente.',
        'loan_created' => 'Préstamo registrado correctamente.',
        'loan_approved' => 'Préstamo aprobado.',
        'loan_delivered' => 'Equipos entregados.',
        'loan_cancelled' => 'Préstamo cancelado.',
        'loan_returned' => 'Devolución registrada correctamente.',
        'image_uploaded' => 'Imágenes subidas correctamente.',
        'image_deleted' => 'Imagen eliminada correctamente.',
    ],
];

// Muestra una alerta de bootstrap si la URL trae un código conocido
function renderAlert() {
    
    foreach (['error' => 'danger', 'success' => 'success'] as $type => $style) {
        $code = $_GET[$type] ?? null;
        if (is_string($code) && isset(MESSAGES[$type][$code])) {
            echo '<div class="alert alert-' . $style . '" role="alert">'
                . htmlspecialchars(MESSAGES[$type][$code], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</div>';
        }
    }
}