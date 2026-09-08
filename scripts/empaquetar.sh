#!/usr/bin/env bash
#
# Arma los dos paquetes que se suben al hosting.
#
# No sube nada: deja dos .tar.gz en dist-despliegue/ para subir por el
# administrador de archivos de DirectAdmin o por FTP. Subir desde acá exigiría
# guardar credenciales del servidor, y eso no va en el repositorio.
#
#   ./scripts/empaquetar.sh
#
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SALIDA="$RAIZ/dist-despliegue"
FECHA="$(date +%Y%m%d-%H%M)"

rm -rf "$SALIDA" && mkdir -p "$SALIDA"

# ------------------------------------------------------------------ backend

echo "==> Backend"
cd "$RAIZ/backend"

# Sin dev y con el autoloader optimizado: en el servidor no se corren tests ni
# análisis estático, y el mapa de clases precalculado ahorra recorrer carpetas
# en cada petición.
composer install --no-dev --optimize-autoloader --no-interaction --quiet

# El .env, los tests y las herramientas no viajan. El .env del servidor es otro
# y se escribe allá una sola vez.
tar czf "$SALIDA/backend-$FECHA.tar.gz" \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='tests' \
    --exclude='.git' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/data/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='phpstan.neon' \
    --exclude='phpunit.xml' \
    .

# Se devuelven las dependencias de desarrollo: si no, la próxima corrida de
# tests en local falla y no se entiende por qué.
composer install --no-interaction --quiet

# ----------------------------------------------------------------- frontend

echo "==> Frontend"
cd "$RAIZ/frontend"

if [ ! -f .env.production ]; then
    echo "    FALTA frontend/.env.production" >&2
    echo "    Cópialo de .env.production.example y pon la URL real de la API." >&2
    echo "    Vite incrusta esa URL en el bundle al compilar: sin ella el sitio" >&2
    echo "    publicado va a llamar a 127.0.0.1 y no va a responder nada." >&2
    exit 1
fi

npm ci --silent
npm run build

# Se empaqueta el CONTENIDO de dist/, no la carpeta: lo que se sube va directo a
# public_html, sin un nivel de más.
tar czf "$SALIDA/frontend-$FECHA.tar.gz" -C dist .

echo
echo "Listo. Para subir:"
ls -lh "$SALIDA" | tail -n +2 | awk '{printf "  %-34s %s\n", $9, $5}'
echo
echo "Sigue los pasos de DESPLIEGUE.md."
