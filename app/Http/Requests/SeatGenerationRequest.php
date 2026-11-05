<?php

namespace App\Http\Requests;

use App\Services\SeatMapService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SeatGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware "organizer"
    }

    public function rules(): array
    {
        return [
            'rows'           => ['required', 'integer', 'min:1', 'max:100'],
            'seats_per_row'  => ['required', 'integer', 'min:1', 'max:150'],
            'row_label_type' => ['required', Rule::in(['letters', 'numbers'])],
            'row_start'      => ['required', 'string', 'max:3'],
            'numbering'      => ['required', Rule::in([
                SeatMapService::NUMBERING_ASC,
                SeatMapService::NUMBERING_DESC,
                SeatMapService::NUMBERING_ODD_EVEN,
            ])],
            'start_number'   => ['nullable', 'integer', 'min:1', 'max:999'],
            'aisles'         => ['nullable', 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $start = trim((string) $this->input('row_start'));

            if ($this->input('row_label_type') === 'letters' && ! preg_match('/^[A-Za-z]{1,2}$/', $start)) {
                $v->errors()->add('row_start', 'La fila inicial debe ser una o dos letras (ej: A).');
            }
            if ($this->input('row_label_type') === 'numbers' && ! ctype_digit($start)) {
                $v->errors()->add('row_start', 'La fila inicial debe ser un número (ej: 1).');
            }
            if ((int) $this->input('rows') * (int) $this->input('seats_per_row') > 10000) {
                $v->errors()->add('rows', 'Un sector no puede superar las 10.000 butacas. Dividilo en varios sectores.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'rows'           => 'cantidad de filas',
            'seats_per_row'  => 'butacas por fila',
            'row_label_type' => 'tipo de fila',
            'row_start'      => 'fila inicial',
            'numbering'      => 'numeración',
            'start_number'   => 'número inicial',
            'aisles'         => 'pasillos',
        ];
    }

    public function messages(): array
    {
        return [
            'aisles.regex' => 'Los pasillos se indican con números separados por coma (ej: 10,20).',
        ];
    }
}
