<?php
// Panel de inicio, solo puedo verlo si estoy logueado
require_once __DIR__ . '/../../utils/auth.php';

requireLogin();

$pageTitle = 'Inicio';
require_once __DIR__ . '/common/header.php';
?>
<h2>Bienvenido, <?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8')?></h2>
<p>Rol: <?= htmlspecialchars($_SESSION['user_role'], ENT_QUOTES, 'UTF-8')?></p>

<?php require_once __DIR__ . '/common/footer.php'; ?>