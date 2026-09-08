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

# El script de instalación vive en la raíz del repositorio, pero el paquete se
# arma desde backend/: hay que copiarlo dentro o en el servidor no existe.
mkdir -p scripts
cp "$RAIZ/scripts/instalar-en-servidor.sh" scripts/

# El .env, los tests y las herramientas no viajan. El .env del servidor es otro
# y se escribe allá una sola vez. Tampoco viajan los archivos de desarrollo del
# frontend que Laravel trae de fábrica y este proyecto no usa: el frontend vive
# aparte, con su propio package.json.
tar czf "$SALIDA/backend-$FECHA.tar.gz" \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='tests' \
    --exclude='.git' \
    --exclude='docs' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/data/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='phpstan.neon' \
    --exclude='phpunit.xml' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='vite.config.js' \
    --exclude='CLAUDE.md' \
    --exclude='.editorconfig' \
    --exclude='.npmrc' \
    --exclude='.gitattributes' \
    .

rm -rf scripts

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

# Comprobación de que la URL de producción quedó realmente incrustada.
#
# Vite mete las variables en el bundle al compilar. Si falta .env.production, cae
# en el valor por defecto —127.0.0.1— y el sitio publicado no llega a ninguna
# parte. El error aparece recién en el navegador de quien entra, con un
# "Failed to fetch" que no dice por qué, así que conviene detectarlo acá.
URL_API="$(grep -oE '^VITE_API_URL=.*' .env.production | cut -d= -f2-)"
BUNDLE="$(ls dist/assets/index-*.js | head -1)"

if grep -q '127\.0\.0\.1\|localhost' "$BUNDLE"; then
    echo >&2
    echo "    El bundle quedó apuntando a 127.0.0.1 o localhost." >&2
    echo "    Revisa frontend/.env.production: debe tener la URL pública de la API." >&2
    exit 1
fi

if ! grep -qF "$URL_API" "$BUNDLE"; then
    echo >&2
    echo "    No encontré '$URL_API' dentro del bundle compilado." >&2
    echo "    Algo salió mal en la compilación: no subas esto." >&2
    exit 1
fi

echo "    API incrustada: $URL_API"

# Se empaqueta el CONTENIDO de dist/, no la carpeta: lo que se sube va directo a
# public_html, sin un nivel de más.
tar czf "$SALIDA/frontend-$FECHA.tar.gz" -C dist .

echo
echo "Listo. Para subir:"
ls -lh "$SALIDA" | tail -n +2 | awk '{printf "  %-34s %s\n", $9, $5}'
echo
echo "Sigue los pasos de DESPLIEGUE.md."
