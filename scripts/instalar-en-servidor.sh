#!/usr/bin/env bash
#
# Se ejecuta EN EL SERVIDOR, dentro de la carpeta del backend, una vez subido y
# descomprimido el paquete.
#
#   cd /domains/api.construprec.surcode.cl/laravel
#   bash scripts/instalar-en-servidor.sh
#
# Detecta si es la primera instalación o una actualización y hace lo que
# corresponde. Se puede correr de nuevo sin romper nada.
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

# El binario de PHP del sitio no siempre es el que responde a `php` en la
# consola: en CloudLinux la consola suele traer una versión más vieja. Se busca
# el 8.4 y se avisa si no aparece, en vez de fallar a la mitad con un error de
# sintaxis incomprensible.
for candidato in /usr/local/php84/bin/php /usr/local/bin/php84 php84 php; do
    if command -v "$candidato" >/dev/null 2>&1; then
        VERSION="$("$candidato" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "0.0")"
        if [ "${VERSION%%.*}" -ge 8 ] && [ "${VERSION#*.}" -ge 4 ]; then
            PHP="$candidato"
            break
        fi
    fi
done

if [ -z "${PHP:-}" ]; then
    echo "No encontré PHP 8.4." >&2
    echo "Míralo en el PHP Selector del panel y pon la ruta a mano:" >&2
    echo "  PHP=/ruta/al/php bash scripts/instalar-en-servidor.sh" >&2
    exit 1
fi

echo "PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

if [ ! -f .env ]; then
    echo >&2
    echo "Falta el archivo .env en $(pwd)" >&2
    echo "Súbelo con los datos de la base antes de correr esto." >&2
    exit 1
fi

# Sin esto la aplicación no puede escribir logs ni cachés y responde 500 sin
# explicar por qué. Son las dos únicas carpetas que necesita escribir.
echo "==> Permisos"
chmod -R 755 storage bootstrap/cache

# La aplicación queda en mantenimiento mientras se toca la base: si alguien entra
# justo cuando falta media migración, ve datos a medias.
if [ -f artisan ] && "$PHP" artisan inspire >/dev/null 2>&1; then
    "$PHP" artisan down --render="errors::503" >/dev/null 2>&1 || true
    ENCENDER=1
fi

echo "==> Migraciones"
"$PHP" artisan migrate --force

# El catálogo sólo se carga si está vacío. Volver a sembrarlo sobre datos ya
# cargados duplicaría escuadrías y productos.
if [ "$("$PHP" artisan tinker --execute='echo App\Models\Escuadria::count();' 2>/dev/null | tail -1)" = "0" ]; then
    echo "==> Catálogo (primera vez)"
    "$PHP" artisan db:seed --force
else
    echo "==> Catálogo ya cargado, se deja como está"
fi

# Las cachés se limpian antes de rehacerse: si quedó una vieja de un despliegue
# anterior, `config:cache` la sobreescribe pero `route:cache` puede fallar.
echo "==> Cachés"
"$PHP" artisan config:clear >/dev/null
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

[ "${ENCENDER:-0}" = "1" ] && "$PHP" artisan up >/dev/null 2>&1 || true

echo
echo "Listo."
"$PHP" artisan tinker --execute='
printf("  usuarios: %d · escuadrías: %d · productos: %d\n",
    App\Models\User::count(), App\Models\Escuadria::count(), App\Models\ProductoCapa::count());' 2>/dev/null | tail -1

if [ "$("$PHP" artisan tinker --execute='echo App\Models\User::count();' 2>/dev/null | tail -1)" = "0" ]; then
    echo
    echo "Todavía no hay ningún usuario. Créalo con:"
    echo "  $PHP artisan construprec:usuario"
fi
