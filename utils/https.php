<?php
// Verifica HTTPS directamente desde el servidor, ignora cabeceras falsificables como X-Forwarded-Proto
function esHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}