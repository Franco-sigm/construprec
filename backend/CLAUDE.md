# Construprec — backend

API en Laravel 13. El frontend vive aparte, en `../frontend/`.

## Entorno

**Local (WSL2, Ubuntu 24.04):**

- PHP 8.4.25 (PPA de Ondřej; los repos de Ubuntu solo llegan a 8.3)
- Composer en `~/.local/bin/composer`
- MySQL 8.0.46 en el **puerto 3307**, no el 3306: está así en
  `/etc/mysql/mysql.conf.d/mysqld.cnf`
- Base `construprec`, `utf8mb4_unicode_ci`, usuario `root`

**Hosting (surcode.cl — DirectAdmin + CloudLinux, servidor LiteSpeed):**

- PHP 8.4.24 con OPcache activo
- Dominio `api.construprec.surcode.cl`, raíz en
  `/domains/api.construprec.surcode.cl/public_html`
- Certificado ZeroSSL con renovación automática
- La versión de PHP se cambia en el *PHP Selector*, no en MultiPHP Manager
  (eso es de cPanel y este panel es DirectAdmin)
- phpMyAdmin reporta el PHP del panel, no el de la cuenta: no sirve para
  verificar la versión

`composer.json` fija `config.platform.php = 8.4.24` para que Composer resuelva
dependencias ejecutables en el servidor y no en la máquina local, que va una
versión más arriba. Si el hosting sube de versión, hay que actualizar ese valor
y correr `composer update`.

El árbol de dependencias exige **PHP >= 8.4.1**: 17 componentes de Symfony 8.1
lo declaran. Bajar de ahí rompe la instalación.

## Restricciones de hosting compartido

Condicionan decisiones de arquitectura, no son detalles de despliegue:

- **No hay Redis ni procesos permanentes.** Sesión, caché y colas van a la base
  de datos (`SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` = `database`).
- **Las colas no tienen worker supervisado.** Corren por cron con
  `queue:work --stop-when-empty`, usando la ruta completa al binario de PHP:
  el `php` del cron suele ser una versión distinta a la del sitio.
- **No instalar Horizon ni Telescope.** El primero necesita Redis; el segundo
  llena la base de datos y revienta la cuota de disco.
- **El document root debe apuntar a `public/`**, nunca a la raíz del proyecto:
  si no, quedan expuestos `.env`, `storage/` y `vendor/`.
- LiteSpeed lee `.htaccess`, así que el que trae Laravel en `public/` funciona
  sin traducir reglas.

## Herramientas

| Qué | Comando |
|---|---|
| Tests | `./vendor/bin/pest` |
| Análisis estático | `./vendor/bin/phpstan analyse` (Larastan, nivel 5) |
| Formato | `./vendor/bin/pint` |
| Logs en vivo | `php artisan pail` |
| Documentación OpenAPI | `/docs/api` (Scramble, desde los type hints) |

Los tests corren contra SQLite en memoria, configurado en `phpunit.xml`. Eso
requiere la extensión `php8.4-sqlite3`. SQLite y MySQL no se comportan igual en
todo, así que conviene una corrida contra MySQL antes de desplegar.

La autenticación es por tokens de Sanctum (`php artisan install:api` ya se
ejecutó; `User` tiene el trait `HasApiTokens`).

## Convenciones

- Capas: `Models → Http/Requests` (validación de entrada) `→ Services` (lógica,
  lanza las excepciones) `→ Http/Controllers` (solo delegan) `→ Http/Resources`
  (forma de la salida).
- **Las migraciones son la fuente de verdad del esquema.** Eloquent no declara
  columnas y no existe autogeneración: las migraciones se escriben a mano.
- Laravel 13 usa atributos de PHP en los modelos (`#[Fillable]`, `#[Hidden]`)
  en vez de las propiedades `$fillable` y `$hidden` de versiones anteriores.
  Casi todos los tutoriales en línea usan la sintaxis vieja.
- Comentarios y mensajes de commit en español, explicando el porqué.
