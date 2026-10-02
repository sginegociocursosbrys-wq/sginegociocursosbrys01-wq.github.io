# SIRBE · Registro de Bienes Muebles Escolares (PHP + MySQL)

Portal web para controlar el ciclo de vida de mobiliario, equipo tecnológico y material didáctico de un colegio: **alta → traslado → mantenimiento → baja**, con historial por bien y código de inventario automático (`BM-00001`).

**Stack:** PHP 8.0+ (PDO) · MySQL 5.7+/MariaDB 10.3+ · interfaz HTML/CSS/JS sin compilación. Funciona en hosting compartido (cPanel, Hostinger, etc.).

## Roles
| Rol | Puede |
|---|---|
| admin | Todo, incluida la baja de bienes |
| encargado | Registrar, editar y trasladar bienes |
| docente / auditor | Consultar inventario e historial |

## Instalación en tu hosting
1. En cPanel crea una base MySQL, un usuario y asígnalo a la base con todos los privilegios.
2. Sube **todo el contenido** de esta carpeta a `public_html` (o a un subdirectorio).
3. Copia `config.example.php` como `config.php` y completa host, base, usuario, contraseña y `INSTALL_KEY`.
4. Abre `https://tu-dominio/instalar.php`: crea las tablas y tu administrador.
5. **Borra `instalar.php` del servidor.** Entra a `https://tu-dominio/`.

Alternativa: importa `schema.sql` desde phpMyAdmin y crea el administrador desde `instalar.php`.

## Estructura
```
index.html        Interfaz (login, resumen, inventario, ficha, traslados, bajas)
api.php           API JSON (sesiones PHP, PDO con consultas preparadas)
schema.sql        Tablas y datos base (áreas y categorías)
instalar.php      Instalador de una sola vez
config.example.php  Plantilla de conexión (config.php no se sube a Git)
.htaccess         Bloquea config.php, schema.sql y archivos internos (Apache/LiteSpeed)
```

## Flujo de trabajo colaborativo (GitHub/GitLab)
1. Rama protegida `main`; trabaja en `feature/<tema>` o `fix/<tema>`.
2. Commits con formato Conventional Commits (`feat:`, `fix:`, `docs:`).
3. Pull/Merge Request con 1 revisión y el CI en verde (revisa la sintaxis PHP; ya incluye `.github/workflows/ci.yml` y `.gitlab-ci.yml`).
4. `config.php` está en `.gitignore`: las credenciales nunca van al repositorio.

## Seguridad
Usa HTTPS, borra `instalar.php`, elige contraseñas largas y verifica que `https://tu-dominio/config.php` no muestre contenido (si tu hosting usa Nginx, el `.htaccess` no aplica: mueve `config.php` fuera de `public_html` y ajusta el `require` de `api.php` e `instalar.php`).
