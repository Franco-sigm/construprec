# Desplegar en DirectAdmin

Para el hosting de **surcode.cl**: DirectAdmin sobre CloudLinux, servidor
LiteSpeed, PHP 8.4.

Dos dominios:

| | |
|---|---|
| `api.construprec.surcode.cl` | El backend Laravel |
| `construprec.surcode.cl` | El frontend compilado |

> **Nada de esto se probó en el servidor.** El proyecto corre y está verificado
> en local; los pasos vienen de la configuración documentada del hosting. Lo que
> falle en la primera subida hay que anotarlo acá.

---

## Antes de empezar

En el panel, una vez por cada dominio:

1. **PHP 8.4 en el PHP Selector.** No en MultiPHP Manager: eso es de cPanel y
   este panel es DirectAdmin. Verificar con un `phpinfo()` propio o por SSH, no
   con phpMyAdmin — phpMyAdmin reporta el PHP del panel, no el de la cuenta.

2. **Extensiones**: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`,
   `ctype`, `json`, `fileinfo`, `gd`. La última la necesita dompdf para las
   imágenes; sin ella el PDF sale igual pero falla si algún día se agrega un
   logo.

3. **Base de datos** MySQL con su usuario. Anotar nombre, usuario y contraseña:
   van al `.env` del servidor y a ninguna otra parte.

4. **Certificado SSL** en los dos dominios. La API se llama desde un sitio en
   HTTPS, así que si va en HTTP el navegador bloquea las peticiones.

---

## ¿Terminal o administrador de archivos?

Se puede hacer casi todo subiendo carpetas comprimidas, como siempre. Pero con
Laravel hay dos cosas que el administrador de archivos no resuelve bien.

**Descomprimir `vendor/`.** Son unos 10.000 archivos. El extractor del navegador
suele agotar el tiempo de espera a la mitad y deja el árbol incompleto sin
avisar, lo que después se ve como errores de "clase no encontrada" que no llevan
a ninguna parte. Por consola, `tar xzf` lo hace en segundos.

**Los comandos posteriores.** Crear las tablas, cachear la configuración y crear
el usuario no se hacen copiando archivos. La contraseña, en particular, se cifra
en PHP: no sale de un INSERT.

Comparado honestamente:

| | Administrador de archivos | Consola |
|---|---|---|
| Subir el paquete | Igual de rápido | Igual (se sube por el mismo panel) |
| Descomprimir | Lento y puede cortarse | Segundos |
| Migrar y cachear | No se puede | Un comando |
| Crear el usuario | No se puede | Un comando |
| La primera vez | Familiar | Hay que aprender seis comandos |
| Las siguientes | Igual de lento siempre | Mucho más rápido |

**La primera vez la consola no es más simple; de ahí en adelante sí.** Y para
esto no hay atajo real: sin ella quedan trabajos manuales que se olvidan.

Para bajar la barrera, el repositorio trae `scripts/instalar-en-servidor.sh`, que
hace migraciones, catálogo, permisos y cachés en **un solo comando**.

### Dónde está la consola

En DirectAdmin, según el plan:

- **SSH**, en *Advanced Features → SSH Keys*, y se entra con un cliente
  (`ssh usuario@surcode.cl`). Es lo mejor.
- **Terminal del navegador**, si el panel la trae, en *Advanced Features →
  Terminal*.

Si no aparece ninguna, hay que pedírsela al proveedor. Sin consola queda el
camino de importar el SQL por phpMyAdmin, que está más abajo, pero es más
trabajo cada vez.

---

## 1. Empaquetar

En tu máquina:

```bash
cp frontend/.env.production.example frontend/.env.production
# editar la URL de la API si el dominio cambia

./scripts/empaquetar.sh
```

Deja dos archivos en `dist-despliegue/`.

**El frontend se compila con la URL de la API adentro.** Vite incrusta las
variables en el bundle al compilar, no las lee al ejecutar: si mañana cambia el
dominio de la API, hay que volver a compilar y volver a subir.

---

## 2. Backend

### Dónde va

```
/domains/api.construprec.surcode.cl/
├── laravel/              ← acá se descomprime todo
│   ├── app/  config/  vendor/  ...
│   ├── .env              ← fuera del alcance de la web
│   └── public/
└── public_html/          ← el document root
```

**El document root tiene que apuntar a `public/`, nunca a la raíz.** Si apunta a
la raíz, quedan expuestos el `.env` con la contraseña de la base, `storage/` con
los logs y `vendor/` entero.

Dos formas, en orden de preferencia:

**a) Cambiar el document root en el panel.** En *Domain Setup* → el dominio →
`Document Root`, poner `/domains/api.construprec.surcode.cl/laravel/public`. Es
lo más limpio: no queda nada raro en el árbol.

**b) Reemplazar `public_html` por un enlace.** Si el panel no deja cambiarlo:

```bash
cd /domains/api.construprec.surcode.cl
mv public_html public_html.viejo
ln -s laravel/public public_html
```

Si tampoco se puede crear el enlace, queda copiar el contenido de `public/` a
`public_html` y corregir las dos rutas de `index.php` — pero eso hay que rehacerlo
en cada despliegue y se olvida. Vale la pena insistir con las otras dos.

### Subir e instalar

Subir el `.tar.gz` con el administrador de archivos del panel, a
`/domains/api.construprec.surcode.cl/`. Después, en la consola:

```bash
cd /domains/api.construprec.surcode.cl
mkdir -p laravel && tar xzf backend-AAAAMMDD-HHMM.tar.gz -C laravel
cd laravel
```

Subir también el `.env` (el que se preparó con los datos de la base) a esa misma
carpeta `laravel/`. Y entonces:

```bash
bash scripts/instalar-en-servidor.sh
php artisan construprec:usuario      # pide la contraseña de forma oculta
```

El script busca el PHP 8.4 correcto, arregla los permisos, corre las
migraciones, carga el catálogo sólo si está vacío y rehace las cachés. Se puede
volver a correr sin romper nada.

Si prefieres hacerlo paso a paso:

```bash
chmod -R 755 storage bootstrap/cache
php artisan migrate --force
php artisan db:seed --force          # monedas, escuadrías y catálogo
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan construprec:usuario
```

**No corras `php artisan key:generate`**: la APP_KEY ya viene en el `.env`.
Regenerarla después de tener datos deja los tokens de sesión sin poder
descifrarse.

`--force` va porque Laravel pregunta antes de migrar en producción y por SSH esa
pregunta puede quedar esperando sin que se note.

#### Si el hosting no da consola

Algunos planes de DirectAdmin sólo traen phpMyAdmin. En ese caso las tablas se
crean importando un volcado, que se genera en tu máquina:

```bash
cd backend
php artisan construprec:esquema-sql      # deja docs/esquema.sql
```

En phpMyAdmin: elegir la base y usar **Importar**. Crea las tablas y carga el
catálogo —monedas, escuadrías y productos— dejando vacías las de usuarios,
proyectos y presupuestos.

El volcado incluye el registro de migraciones ya aplicadas. Sin él Laravel
creería que no se ha migrado nada, y el día que sí haya consola un `migrate`
intentaría crear tablas que ya existen.

El archivo se regenera con ese comando y **no se versiona**: la fuente de verdad
son las migraciones, y una copia guardada en el repositorio terminaría quedando
vieja sin que nadie lo note. Hay que volver a generarlo cada vez que cambie el
esquema.

Lo que ese camino no resuelve es crear el usuario, porque la contraseña se cifra
en PHP. Si no hay consola, hay que agregar temporalmente una ruta que lo cree y
borrarla después.

### Permisos

```bash
chmod -R 755 storage bootstrap/cache
```

Sólo esas dos: son las únicas que la aplicación escribe. Dar permisos de
escritura al resto no hace falta y agranda lo que un archivo subido por error
podría tocar.

### Cachés

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Después de `config:cache`, el `.env` deja de leerse en cada petición.** Es lo
que se quiere en producción, pero significa que cambiar una variable no tiene
efecto hasta correr `php artisan config:clear` y volver a cachear. Es la causa
más común de "cambié el .env y no pasa nada".

### Comprobar

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://api.construprec.surcode.cl/up
curl -s https://api.construprec.surcode.cl/api/catalogo | head -c 200
```

`/up` tiene que dar 200 y el catálogo devolver JSON con escuadrías y productos.

Y comprobar que lo que no debe verse, no se vea:

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://api.construprec.surcode.cl/.env
curl -s -o /dev/null -w '%{http_code}\n' https://api.construprec.surcode.cl/docs/api
```

El `.env` tiene que dar **403 o 404**. Si devuelve 200, el document root está mal
apuntado: hay que corregirlo antes de seguir, porque la contraseña de la base ya
es pública. La documentación de la API da **403** sola cuando `APP_ENV` es
`production`.

---

## 3. Frontend

```bash
cd /domains/construprec.surcode.cl/public_html
tar xzf ~/frontend-AAAAMMDD-HHMM.tar.gz
```

El paquete trae el contenido de `dist/` sin la carpeta, así que se descomprime
directo en `public_html`. Incluye su `.htaccess` con la reescritura a
`index.html`, las cabeceras de seguridad y el cacheo.

Los archivos con hash en el nombre se cachean para siempre —si cambia el
contenido cambia el nombre— y el `index.html` se revalida siempre. Sin eso, un
despliegue tarda en verse.

---

## 4. Cron

En *Cron Jobs* del panel, una entrada:

```
* * * * * /opt/alt/php84/usr/bin/php /home/surcodec/domains/api.construprec.surcode.cl/laravel/artisan schedule:run >> /dev/null 2>&1
```

**La ruta completa al binario, no `php` a secas.** El cron no hereda tu `PATH`,
así que `php` puede no existir ahí o ser otra versión, y el comando falla en
silencio.

En CloudLinux las versiones viven en `/opt/alt/phpXX/usr/bin/php`. Ojo con que
`which php` engaña: devuelve algo como `/usr/local/php81/bin/php`, que es un
enlace que el PHP Selector apunta a la versión elegida. La ruta real se ve con
`readlink -f $(which php)`, y conviene usar esa: no cambia si alguien mueve el
selector.

Hoy no hay nada programado. Cuando lo haya —actualizar tasas de cambio, por
ejemplo— y si se usan colas, va además:

```
*/5 * * * * /opt/alt/php84/usr/bin/php /home/surcodec/domains/api.construprec.surcode.cl/laravel/artisan queue:work --stop-when-empty
```

Con `--stop-when-empty` porque no hay proceso supervisado: el worker vacía la
cola y se muere en vez de quedar corriendo.

---

## 5. Comprobar de punta a punta

Abrir `https://construprec.surcode.cl` y recorrer el ciclo completo: entrar,
dibujar una planta, poner precios, guardar y descargar el PDF.

Si algo no responde, mirar en este orden:

| Síntoma | Dónde mirar |
|---|---|
| La pantalla carga pero no aparecen materiales | La consola del navegador. Un error de CORS significa que falta el dominio en `CORS_ORIGINS` |
| Todo da 500 | `laravel/storage/logs/laravel.log`. Con `APP_DEBUG=false` el navegador no dice nada, a propósito |
| 404 en todas las rutas menos la raíz | El `.htaccess` no se está leyendo, o el document root apunta mal |
| Un cambio del `.env` no surte efecto | Falta `php artisan config:clear` y volver a cachear |
| El PDF sale vacío o da 500 | Permisos de `storage/`, o falta la extensión `gd` |

---

## Volver a desplegar

```bash
./scripts/empaquetar.sh          # en local

# en el servidor, tras subir el paquete nuevo
cd /domains/api.construprec.surcode.cl/laravel
tar xzf ../backend-NUEVO.tar.gz
bash scripts/instalar-en-servidor.sh
```

El script pone la aplicación en mantenimiento mientras toca la base y la vuelve
a levantar al terminar, para que nadie vea datos a medias.

El `.env` no se toca: el paquete lo excluye a propósito.

Para el frontend basta descomprimir encima. Los nombres con hash hacen que el
navegador pida los archivos nuevos solo.

---

## Lo que no está resuelto

- **No hay respaldo automático de la base.** Conviene programar el que trae
  DirectAdmin antes de cargar datos que importen.
- **No hay despliegue por Git.** Se sube un paquete a mano; con cuatro o cinco
  despliegues al mes alcanza, pero si esto crece conviene automatizarlo.
- **Los logs no rotan solos más allá de lo que hace Laravel** con `LOG_STACK=daily`,
  que guarda catorce días. En hosting compartido la cuota de disco se llena sin
  avisar.
