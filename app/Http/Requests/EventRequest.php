<?php

namespace App\Http\Requests;

use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware "organizer"
    }

    protected function prepareForValidation(): void
    {
        // Los checkbox no llegan si están destildados
        $this->merge([
            'is_seated'       => $this->boolean('is_seated'),
            'is_nominative'   => $this->boolean('is_nominative'),
            'allow_transfers' => $this->boolean('allow_transfers'),
        ]);
    }

    public function rules(): array
    {
        $organizer = app('currentOrganizer');
        $creating = $this->isMethod('post');

        return [
            'title'       => ['required', 'string', 'max:150'],
            'category'    => ['nullable', Rule::in(array_keys(config('geventos.categorias')))],
            'venue_id'    => [
                'nullable',
                Rule::exists('venues', 'id')
                    ->where('organizer_id', $organizer->id)
                    ->whereNull('deleted_at'),
            ],
            'summary'     => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:20000'],
            'cover'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_cover' => ['nullable', 'boolean'],

            'starts_at'      => array_filter(['required', 'date', $creating ? 'after:now' : null]),
            'ends_at'        => ['nullable', 'date', 'after:starts_at'],
            'doors_open_at'  => ['nullable', 'date', 'before_or_equal:starts_at'],
            'sales_start_at' => ['nullable', 'date', 'before:starts_at'],
            'sales_end_at'   => ['nullable', 'date', 'after_or_equal:sales_start_at'],

            'is_seated'             => ['boolean'],
            'is_nominative'         => ['boolean'],
            'allow_transfers'       => ['boolean'],
            'max_tickets_per_order' => ['required', 'integer', 'min:1', 'max:' . config('geventos.max_entradas_por_orden')],
            'capacity'              => ['nullable', 'integer', 'min:1', 'max:500000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->boolean('is_seated')) {
                return;
            }

            $venue = $this->filled('venue_id') ? Venue::find($this->input('venue_id')) : null;

            if (! $venue || ! $venue->has_seat_map) {
                $v->errors()->add('is_seated', 'Para vender con butacas numeradas elegí un lugar que tenga mapa de butacas.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'title'                 => 'título',
            'category'              => 'categoría',
            'venue_id'              => 'lugar',
            'summary'               => 'resumen',
            'description'           => 'descripción',
            'cover'                 => 'imagen de portada',
            'starts_at'             => 'fecha y hora de inicio',
            'ends_at'               => 'fecha y hora de fin',
            'doors_open_at'         => 'apertura de puertas',
            'sales_start_at'        => 'inicio de venta',
            'sales_end_at'          => 'fin de venta',
            'max_tickets_per_order' => 'máximo de entradas por compra',
            'capacity'              => 'capacidad',
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.after' => 'La fecha de inicio tiene que ser futura.',
        ];
    }

    /** Datos listos para guardar en el modelo (sin la imagen) */
    public function eventData(): array
    {
        return collect($this->validated())
            ->except(['cover', 'remove_cover'])
            ->all();
    }
}
