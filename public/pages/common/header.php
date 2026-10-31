<?php

// Cabecera común de la pagina
// Antes de incluirla la pagina puede definir
// $pageTitle para cambiar el titulo de la pagina

require_once __DIR__ . '/../../../utils/messages.php';

$pageTitle = $pageTitle ?? 'SIGEL';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | SIGEL</title>
    <!-- Bootstrap 4.5.2 desde CDN -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark mb-4">
        <a class="navbar-brand" href="/index.php">SIGEL</a>
    </nav>
    <div class="container">
        <?php renderAlert(); ?>