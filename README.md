# SIGEL

Sistema de Gestión de Préstamo de Equipos de Laboratorio. Proyecto 1 de ISW-613.

## Requisitos

- XAMPP con PHP 8.2 y MariaDB
- Apache con virtual hosts

## Instalación

1. Clonar el repositorio dentro de `htdocs`.
2. Copiar `config/config.example.php` como `config/config.php` y ajustar las credenciales de la base de datos.
3. Configurar un virtual host `sigel.local` cuyo `DocumentRoot` apunte a la carpeta `public/`.
4. Agregar `127.0.0.1 sigel.local` al archivo `/etc/hosts`.
5. Crear la base de datos (pendiente: `sql/schema.sql` y `sql/seed.sql`).

## Credenciales de prueba

Pendiente (una por rol: administrador, operador y profesor).

## Estructura del proyecto

- `public/`: único directorio accesible por URL.
- `config/`: configuración local (`config.php` no se versiona).