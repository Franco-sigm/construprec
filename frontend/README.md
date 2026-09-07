# Construprec — frontend

Asistente de presupuestos de tabiquería en madera. React 19 + Vite.

La API vive aparte, en `../backend/`.

## Correr

```bash
npm install
npm run dev      # servidor de desarrollo
npm run build    # compilar a dist/
npm run lint     # oxlint
```

## Cómo está armado

```
src/
  estilos/      tema.css con las texturas, componentes.css con las piezas
  componentes/  panel, botón, barras, lista de materiales, plano isométrico
  App.jsx       la pantalla de presupuesto
```

**Las texturas son gradientes CSS, no imágenes.** Pesan cero bytes de red,
escalan sin pixelarse en cualquier densidad de pantalla, y se recoloran
cambiando una variable en vez de reexportar archivos.

**Las tipografías son auto-hospedadas** (`@fontsource`). En hosting compartido
conviene no depender de un pedido a Google en cada carga, y así el sitio
funciona sin conexión a terceros.

**El dibujo isométrico se genera desde la geometría**, no es una ilustración:
los pies derechos que se ven son los que se van a comprar. Si cambia la
separación, cambia el dibujo, y eso hace visible un error de tipeo que en una
tabla de números pasaría inadvertido.

## Pendiente

Los datos de `App.jsx` son de muestra —los que devuelve `CalculoProyectoService`
para una planta de 6 x 4— mientras no exista la API. Falta el asistente de
ingreso de medidas y las llamadas al backend.
