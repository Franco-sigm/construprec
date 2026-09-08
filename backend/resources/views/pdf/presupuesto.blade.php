{{--
    Presupuesto en PDF, pensado para imprimir y llevar a la barraca.

    Sin colores de fondo ni texturas: el papel no es la pantalla, y una trama de
    madera detrás del texto sólo gasta tóner y hace más difícil leer los números,
    que es a lo que se viene. Todo el peso visual se deja en la tipografía.
--}}
<!DOCTYPE html>
<html lang="es-CL">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 2cm 1.8cm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #1c1c1c;
            line-height: 1.45;
        }

        h1 { font-size: 17pt; margin: 0 0 2pt; }
        h2 { font-size: 11pt; margin: 18pt 0 6pt; text-transform: uppercase; letter-spacing: .5pt;
             border-bottom: 1.5pt solid #1c1c1c; padding-bottom: 3pt; }

        .sub { color: #555; font-size: 9pt; margin: 0 0 14pt; }

        .obra { border: .5pt solid #999; padding: 8pt 10pt; margin-bottom: 14pt; font-size: 9pt; color: #333; }
        .obra span { display: inline-block; margin-right: 16pt; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .4pt;
             color: #555; border-bottom: .5pt solid #999; padding: 4pt 3pt; }
        td { padding: 5pt 3pt; border-bottom: .5pt solid #ddd; vertical-align: top; }

        /* Los números van a la derecha y con cifras de ancho fijo: es lo que
           permite recorrer una columna con el dedo y detectar el que no cuadra. */
        .num { text-align: right; white-space: nowrap; }

        .detalle { color: #666; font-size: 8.5pt; }

        .subtotal td { border-top: .5pt solid #999; border-bottom: none; font-weight: bold; }

        .total { margin-top: 16pt; border-top: 2pt solid #1c1c1c; padding-top: 8pt;
                 font-size: 14pt; font-weight: bold; }
        .total .der { float: right; }

        .pie { margin-top: 26pt; padding-top: 8pt; border-top: .5pt solid #ccc;
               font-size: 8pt; color: #777; }
    </style>
</head>
<body>

<h1>{{ $presupuesto->nombre }}</h1>
<p class="sub">
    Presupuesto n.º {{ $presupuesto->id }} ·
    {{ $presupuesto->fecha?->format('d/m/Y') }} ·
    {{ $presupuesto->estado === 'emitido' ? 'Emitido' : 'Borrador' }}
    @if ($proyecto)· Proyecto: {{ $proyecto->nombre }}@endif
</p>

@if ($obra)
    <div class="obra">
        <span><strong>{{ number_format($obra['superficie_bruta_m2'], 2, ',', '.') }} m²</strong> de muro</span>
        <span>menos <strong>{{ number_format($obra['superficie_vanos_m2'], 2, ',', '.') }} m²</strong> de vanos</span>
        <span>= <strong>{{ number_format($obra['superficie_neta_m2'], 2, ',', '.') }} m²</strong> netos</span>
        <span><strong>{{ $obra['piezas'] }}</strong> piezas de madera a cortar</span>
    </div>
@endif

@foreach ($etapas as $etapa => $lineas)
    <h2>{{ $rotulos[$etapa] ?? $etapa }}</h2>

    <table>
        <thead>
            <tr>
                <th>Material</th>
                <th class="num">Cantidad</th>
                <th class="num">Precio unitario</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lineas as $linea)
                <tr>
                    <td>
                        {{ $linea->nombre_material }}
                        <div class="detalle">
                            {{-- De dónde salió la cantidad: es lo que permite revisar el
                                 número con una calculadora en vez de tener que confiar. --}}
                            {{ $etiquetaMagnitud($linea->unidad_magnitud) }}
                            {{ number_format((float) $linea->magnitud, 2, ',', '.') }}
                            {{ $unidadMagnitud($linea->unidad_magnitud) }}
                            {{-- El rendimiento sólo se muestra en las capas. En la
                                 madera sería "metros por tira", que es cierto pero
                                 no dice nada útil: lo que importa ahí es el
                                 despiece, no un rendimiento. --}}
                            @if ($linea->origen === 'capa' && (float) $linea->rendimiento > 0)
                                · rinde {{ rtrim(rtrim(number_format((float) $linea->rendimiento, 4, ',', '.'), '0'), ',') }}
                                m² por {{ $linea->unidad_venta }}
                            @endif
                            @if ((float) $linea->merma_pct > 0)
                                · {{ (float) $linea->merma_pct }} % de descarte
                            @endif
                        </div>
                    </td>
                    <td class="num">
                        {{ number_format((float) $linea->cantidad_comprar, 0, ',', '.') }}
                        {{ $linea->unidad_venta }}
                    </td>
                    <td class="num">${{ number_format((float) $linea->precio_unitario, 0, ',', '.') }}</td>
                    <td class="num">${{ number_format((float) $linea->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach

            @if (count($etapas) > 1)
                <tr class="subtotal">
                    <td colspan="3">Subtotal {{ mb_strtolower($rotulos[$etapa] ?? $etapa) }}</td>
                    <td class="num">${{ number_format($lineas->sum(fn ($l) => (float) $l->subtotal), 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endforeach

<div class="total">
    Total
    <span class="der">${{ number_format((float) $presupuesto->total, 0, ',', '.') }} {{ $presupuesto->moneda_destino }}</span>
</div>

<div class="pie">
    Las cantidades incluyen el descarte indicado en cada partida. Los precios son
    los cargados el {{ $presupuesto->fecha?->format('d/m/Y') }} y quedaron congelados
    en este documento: si el material sube después, este presupuesto no cambia.
    <br>
    Generado por Construprec el {{ now()->format('d/m/Y H:i') }}.
</div>

</body>
</html>
