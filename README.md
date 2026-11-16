# SIGEL

Sistema de Gestión de Préstamo de Equipos de Laboratorio. Proyecto 1 de ISW-613.

Permite al administrador gestionar usuarios, categorías y equipos (con imágenes) y consultar el reporte de equipos más prestados, y al operador registrar y avanzar los préstamos de equipos a los profesores.

## Funciones

- **Administrador:** CRUD de usuarios, categorías y equipos con búsqueda y paginación, eliminación lógica, imágenes de los equipos y reporte de equipos más prestados por rango de fechas con exportación a CSV.
- **Operador:** consulta de equipos (con sus imágenes y los consultados recientemente), registro de préstamos de uno o varios equipos, y cambio de estado: solicitado, aprobado, entregado, devuelto o cancelado.
- **Reglas de negocio:** un equipo no se puede prestar en un intervalo que se traslape con otro préstamo activo, la duración no puede superar el máximo de la categoría, y al devolver se marca cada equipo como bueno o dañado (un equipo dañado solo lo reactiva el administrador).
- **Cookies:** "Recordarme" (selector:validador), cantidad de registros por página y equipos consultados recientemente.

## Requisitos

- XAMPP con PHP 8.2 o superior (extensiones `pdo_mysql` y `fileinfo`) y MariaDB 10.4 o superior
- Apache con soporte para virtual hosts
- Git

## Instalación (desde cero)

1. Clonar el repositorio dentro de `htdocs`:

   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs
   git clone <url-del-repositorio> sigel
   cd sigel
   ```

2. Crear la configuración local copiando la plantilla, y ajustar el usuario y la contraseña de la base de datos (en XAMPP el usuario es `root` y la contraseña está vacía):

   ```bash
   cp config/config.example.php config/config.php
   ```

3. Crear la base de datos con las tablas y los datos de prueba (MariaDB debe estar encendido):

   ```bash
   /Applications/XAMPP/xamppfiles/bin/mysql -uroot < sql/schema.sql
   /Applications/XAMPP/xamppfiles/bin/mysql -uroot < sql/seed.sql
   ```

   `seed.sql` se puede ejecutar varias veces sin duplicar datos.

4. Dar permiso de escritura a las carpetas donde Apache guarda las imágenes subidas y el registro de errores (Apache corre con otro usuario):

   ```bash
   chmod 777 storage/equipos logs
   ```

5. Configurar el virtual host. En `/Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf` agregar (ajustando la ruta si el proyecto está en otro lugar):

   ```apache
   <VirtualHost *:80>
       DocumentRoot "/Applications/XAMPP/xamppfiles/htdocs/sigel/public"
       ServerName sigel.local
       <Directory "/Applications/XAMPP/xamppfiles/htdocs/sigel/public">
           Require all granted
       </Directory>
   </VirtualHost>
   ```

   Verificar que en `/Applications/XAMPP/xamppfiles/etc/httpd.conf` esté activa la línea `Include etc/extra/httpd-vhosts.conf`.

6. Agregar el dominio local al archivo `/etc/hosts`:

   ```
   127.0.0.1 sigel.local
   ```

7. Reiniciar Apache y abrir `http://sigel.local`.

El `DocumentRoot` apunta solo a `public/`: el código (`utils/`), la configuración (`config/`), los scripts SQL y las imágenes subidas (`storage/`) no se pueden abrir por URL.

## Credenciales de prueba

| Rol           | Correo                 | Contraseña    |
|---------------|------------------------|---------------|
| Administrador | `admin@sigel.local`    | `Admin123`    |
| Operador      | `operador@sigel.local` | `Operador123` |

Los profesores (`ana.mora@sigel.local` y `luis.rojas@sigel.local`) son quienes reciben los equipos, no inician sesión y por eso no tienen contraseña. Aparecen en la lista al registrar un préstamo.

## Recorrido sugerido

1. Entrar como **administrador**: revisar usuarios, categorías y equipos, abrir un equipo (clic en su código) y subir una imagen, y abrir **Reportes** para ver y exportar los equipos más prestados.
2. Entrar como **operador**: abrir **Préstamos**, crear uno nuevo, aprobarlo, entregarlo y devolverlo marcando cada equipo como bueno o dañado.
3. Probar un traslape: pedir el mismo equipo en fechas que se cruzan con un préstamo activo, el sistema lo rechaza.

## Estructura del proyecto

```
sigel/
├── config/     config.example.php (config.php local no se versiona)
├── logs/       app.log con los errores del sistema (no se versiona)
├── public/     único directorio accesible por URL
│   ├── index.php     formulario de inicio de sesión
│   ├── pages/        pantallas (listados, formularios, reportes)
│   └── actions/      scripts que procesan formularios (con CSRF y redirección)
├── sql/        schema.sql y seed.sql
├── storage/    imágenes de los equipos, fuera de public (el contenido no se versiona)
├── utils/      lógica reutilizable: base de datos, autenticación, CSRF, validaciones y consultas de cada módulo
└── README.md
```

## Seguridad en resumen

- Consultas con sentencias preparadas (PDO con `ERRMODE_EXCEPTION` y `EMULATE_PREPARES` desactivado).
- Contraseñas con `password_hash()` / `password_verify()`, mensajes genéricos al fallar el inicio de sesión y bloqueo temporal tras 3 intentos fallidos.
- Sesión con `session_regenerate_id(true)`, cookie `HttpOnly` y `SameSite=Lax` (más `Secure` cuando se sirve por HTTPS), expiración por inactividad y cierre que destruye la sesión.
- Token CSRF por sesión en todo formulario que modifica datos, validado con `hash_equals()`.
- Salida escapada con `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- Rol verificado en el servidor en cada petición, incluido el acceso directo por URL.
- Archivos: límite de 2 MB, lista blanca de extensiones, tipo real con `finfo`, nombre generado por el servidor y almacenamiento fuera de `public/`.
- Errores solo al archivo `logs/app.log`, nunca en pantalla.
- CSV con neutralización de fórmulas.