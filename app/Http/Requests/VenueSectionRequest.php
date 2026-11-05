<?php

namespace App\Http\Requests;

use App\Models\VenueSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VenueSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware "organizer"
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:80'],
            'type'     => ['required', Rule::in([VenueSection::TYPE_GENERAL, VenueSection::TYPE_SEATED])],
            'color'    => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'capacity' => ['nullable', 'required_if:type,general', 'integer', 'min:1', 'max:200000'],
            'pos_x'    => ['nullable', 'integer', 'min:0', 'max:500'],
            'pos_y'    => ['nullable', 'integer', 'min:0', 'max:500'],
            'width'    => ['nullable', 'integer', 'min:2', 'max:200'],
            'height'   => ['nullable', 'integer', 'min:2', 'max:200'],
            'sort'     => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'     => 'nombre del sector',
            'type'     => 'tipo',
            'capacity' => 'capacidad',
            'pos_x'    => 'posición horizontal',
            'pos_y'    => 'posición vertical',
            'width'    => 'ancho',
            'height'   => 'alto',
        ];
    }

    public function messages(): array
    {
        return [
            'capacity.required_if' => 'Indicá la capacidad del sector general.',
            'color.regex'          => 'El color no es válido.',
        ];
    }
}
