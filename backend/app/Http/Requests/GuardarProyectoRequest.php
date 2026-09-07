<?php

namespace App\Http\Requests;

/**
 * Guardar un proyecto pide lo mismo que calcularlo, más un nombre.
 *
 * Hereda las reglas en vez de copiarlas: son las mismas medidas con los mismos
 * rangos, y tener dos listas paralelas garantiza que tarde o temprano una acepte
 * lo que la otra rechaza.
 */
class GuardarProyectoRequest extends CalcularRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:2000'],

            // Medidas de la planta. Se guardan para poder regenerar las caras si
            // se corrige el rectángulo, pero el cálculo lee las caras.
            'planta.ancho_mm' => ['nullable', 'integer', 'min:1'],
            'planta.largo_mm' => ['nullable', 'integer', 'min:1'],
            'planta.alto_mm' => ['nullable', 'integer', 'min:1'],
            'planta.unidad_ingreso' => ['nullable', 'string', 'max:4'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'nombre.required' => 'El proyecto necesita un nombre para poder guardarlo.',
        ];
    }
}
