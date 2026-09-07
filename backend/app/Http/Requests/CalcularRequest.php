<?php

namespace App\Http\Requests;

use App\Services\Capas\AplicacionCapa;
use App\Services\Tabiqueria\TipoVano;
use App\Support\Medida;
use App\Support\Unidad;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Valida la geometría y la configuración que llegan del asistente.
 *
 * Toda medida se acepta con su unidad, porque la regla del producto es que cada
 * campo deje elegir: se puede escribir la separación en pulgadas aunque el muro
 * esté en metros. La conversión a milímetros la hace el controlador, no aquí.
 */
class CalcularRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $unidades = Rule::in(array_column(Unidad::cases(), 'value'));

        return [
            'caras' => ['required', 'array', 'min:1', 'max:40'],
            'caras.*.nombre' => ['nullable', 'string', 'max:60'],
            'caras.*.largo' => ['required', 'numeric', 'gt:0'],
            'caras.*.alto' => ['required', 'numeric', 'gt:0'],
            'caras.*.unidad' => ['nullable', $unidades],
            'caras.*.es_exterior' => ['nullable', 'boolean'],

            'caras.*.vanos' => ['nullable', 'array', 'max:20'],
            'caras.*.vanos.*.tipo' => ['required', Rule::enum(TipoVano::class)],
            'caras.*.vanos.*.ancho' => ['required', 'numeric', 'gt:0'],
            'caras.*.vanos.*.alto' => ['required', 'numeric', 'gt:0'],
            'caras.*.vanos.*.antepecho' => ['nullable', 'numeric', 'min:0'],
            'caras.*.vanos.*.cantidad' => ['nullable', 'integer', 'min:1', 'max:99'],
            // Desde qué pie derecho arranca el marco, contando desde 1. Nulo deja
            // que el cálculo los reparta parejo.
            'caras.*.vanos.*.desde_tramo' => ['nullable', 'integer', 'min:1', 'max:500'],
            'caras.*.vanos.*.unidad' => ['nullable', $unidades],

            'tabiqueria.escuadria_id' => ['required', 'integer', 'exists:escuadrias,id'],
            'tabiqueria.escuadria_dintel_id' => ['nullable', 'integer', 'exists:escuadrias,id'],
            'tabiqueria.largo_comercial_mm' => ['nullable', 'integer', 'min:1000', 'max:12000'],
            'tabiqueria.separacion' => ['required', 'numeric', 'gt:0'],
            'tabiqueria.separacion_unidad' => ['nullable', $unidades],
            'tabiqueria.solera_inferior' => ['nullable', 'boolean'],
            'tabiqueria.soleras_superiores' => ['nullable', 'integer', 'min:1', 'max:3'],
            'tabiqueria.filas_cadenetas' => ['nullable', 'integer', 'min:0', 'max:5'],
            'tabiqueria.merma_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Piezas en el encuentro de dos muros, contando las dos que ya aporta
            // cada cara. Tres es el armado habitual; cero desactiva el refuerzo.
            'tabiqueria.piezas_por_esquina' => ['nullable', 'integer', 'min:0', 'max:6'],
            // Si las caras forman un contorno cerrado hay tantas esquinas como
            // caras. Una pared suelta no tiene ninguna.
            'tabiqueria.contorno_cerrado' => ['nullable', 'boolean'],

            'capas' => ['nullable', 'array', 'max:12'],
            'capas.*.producto_capa_id' => ['required', 'integer', 'exists:productos_capa,id'],
            'capas.*.aplicacion' => ['nullable', Rule::enum(AplicacionCapa::class)],
            'capas.*.merma_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'capas.*.descuenta_vanos' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Rangos razonables, en milimetros.
     *
     * Se verifican sobre la medida ya convertida y no sobre el numero escrito,
     * porque el numero solo no significa nada: "16" son 16 pulgadas de separacion,
     * que es correcto, o 16 metros, que no lo es. Poner el tope sobre el crudo
     * rompia justo la promesa de dejar elegir unidad en cada campo.
     *
     * @var array<string, array{int, int, string}>
     */
    private const RANGOS = [
        'largo' => [100, 200_000, 'El largo de una cara debe estar entre 10 cm y 200 m.'],
        'alto' => [500, 20_000, 'El alto de una cara debe estar entre 50 cm y 20 m.'],
        'vano' => [100, 20_000, 'Las medidas de un vano deben estar entre 10 cm y 20 m.'],
        'antepecho' => [0, 20_000, 'El antepecho no puede pasar de 20 m.'],
        'separacion' => [100, 2_000, 'La separacion entre pies derechos debe estar entre 10 cm y 2 m.'],
    ];

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $datos = $this->all();

            foreach ($datos['caras'] ?? [] as $i => $cara) {
                $unidad = $cara['unidad'] ?? 'm';

                $this->enRango($v, "caras.{$i}.largo", $cara['largo'] ?? null, $unidad, 'largo');
                $this->enRango($v, "caras.{$i}.alto", $cara['alto'] ?? null, $unidad, 'alto');

                foreach ($cara['vanos'] ?? [] as $j => $vano) {
                    $u = $vano['unidad'] ?? $unidad;

                    $this->enRango($v, "caras.{$i}.vanos.{$j}.ancho", $vano['ancho'] ?? null, $u, 'vano');
                    $this->enRango($v, "caras.{$i}.vanos.{$j}.alto", $vano['alto'] ?? null, $u, 'vano');
                    $this->enRango($v, "caras.{$i}.vanos.{$j}.antepecho", $vano['antepecho'] ?? 0, $u, 'antepecho');
                }
            }

            $this->enRango(
                $v,
                'tabiqueria.separacion',
                $datos['tabiqueria']['separacion'] ?? null,
                $datos['tabiqueria']['separacion_unidad'] ?? 'm',
                'separacion',
            );
        });
    }

    private function enRango(Validator $v, string $campo, mixed $valor, string $unidad, string $rango): void
    {
        if (! is_numeric($valor)) {
            return;
        }

        [$min, $max, $mensaje] = self::RANGOS[$rango];

        try {
            $mm = Medida::de((float) $valor, $unidad)->mm;
        } catch (InvalidArgumentException) {
            // La unidad ya la valido la regla `in`; si igual llega mal, no hay
            // nada que comprobar aca.
            return;
        }

        if ($mm < $min || $mm > $max) {
            $v->errors()->add($campo, $mensaje);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'caras.required' => 'Hay que definir al menos una cara para poder calcular.',
            'caras.*.largo.gt' => 'El largo de la cara tiene que ser mayor que cero.',
            'caras.*.alto.gt' => 'El alto de la cara tiene que ser mayor que cero.',
            'tabiqueria.escuadria_id.required' => 'Falta elegir la escuadría de la tabiquería.',
            'tabiqueria.escuadria_id.exists' => 'La escuadría elegida no existe en el catálogo.',
            'tabiqueria.separacion.required' => 'Falta la separación entre pies derechos.',
            'capas.*.producto_capa_id.exists' => 'Uno de los productos elegidos no existe en el catálogo.',
            'caras.*.vanos.*.desde_tramo.min' => 'Los pies derechos se cuentan desde 1.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tabiqueria.separacion' => 'separación entre pies derechos',
            'tabiqueria.filas_cadenetas' => 'filas de cadenetas',
            'tabiqueria.soleras_superiores' => 'soleras superiores',
        ];
    }
}
