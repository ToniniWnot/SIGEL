<?php
// Pagina de inicio: Este es el formulario de login
require_once __DIR__ . '/../utils/csrf.php';

$pageTitle = 'Iniciar sesión';
require_once __DIR__ . '/pages/common/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header text-center">
                <h4>Iniciar sesión</h4>
            </div>
            <div class="card-body">
                <form action="/actions/login.php" method="POST">
                    <?php csrfField(); ?>
                    <div class="form-group">
                        <label for="correo">Correo</label>
                        <input type="email" class="form-control" id="correo" name="correo" maxlength="150" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Iniciar sesión</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/pages/common/footer.php';
?>